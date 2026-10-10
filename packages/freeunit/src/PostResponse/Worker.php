<?php

declare(strict_types=1);

namespace Drupal\freeunit\PostResponse;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Bounded cron and queue work after the response has left the server.
 *
 * This is the pragmatic half of FreeUnit's missing schedules and queues: until the
 * router can run occurrences on its own (1.38, issue 596), the work is attached to
 * a normal request, after fastcgi_finish_request() has detached it. The budget is
 * what keeps that honest -- the worker holds a Unit application process, and
 * "limits.timeout" (300s in the benchmark config) is the hard ceiling.
 *
 * The lock is non-blocking on purpose: with several workers, one drains and the
 * others keep serving. Overlapping drains of the same queue would double-process
 * items, since claimItem() leases are per-queue, not per-worker.
 */
final class Worker {

  public const LOCK = 'freeunit.post_response';

  /**
   * Queues already drained in this process, to avoid a second drain per request.
   */
  private bool $ran = FALSE;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
    private readonly LockBackendInterface $lock,
    private readonly StateInterface $state,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Runs the configured post-response work if the budget allows.
   *
   * @return array{drained: int, seconds: float, reason: string}
   *   What happened, also stored in state for the status report.
   */
  public function runAfterResponse(): array {
    if ($this->ran) {
      return ['drained' => 0, 'seconds' => 0.0, 'reason' => 'already ran in this request'];
    }
    $this->ran = TRUE;

    $config = $this->configFactory->get('freeunit.settings');
    if (!$config->get('post_response.enabled')) {
      return $this->record(0, 0.0, 'disabled');
    }

    $budgetMs = max(0, (int) $config->get('post_response.budget_ms'));
    if ($budgetMs === 0) {
      return $this->record(0, 0.0, 'zero budget');
    }

    if (!$this->lock->acquire(self::LOCK, 0.0)) {
      return $this->record(0, 0.0, 'another worker holds the lock');
    }

    $started = microtime(TRUE);
    $deadline = $started + $budgetMs / 1000;
    $drained = 0;

    try {
      $drained += $this->drainQueues($deadline, (array) $config->get('post_response.queues'));

      if ($config->get('post_response.cron') && microtime(TRUE) < $deadline) {
        // hook_cron() is Drupal's own scheduled work; running it here is the
        // stand-in until FreeUnit schedules exist.
        \Drupal::moduleHandler()->invokeAll('cron');
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Post-response work stopped: @message', ['@message' => $e->getMessage()]);
    }
    finally {
      $this->lock->release(self::LOCK);
    }

    return $this->record($drained, microtime(TRUE) - $started, 'ok');
  }

  /**
   * Processes queue items until the deadline, one worker plugin at a time.
   *
   * @param float $deadline
   *   Unix time after which no new item is started.
   * @param string[] $only
   *   Queue worker plugin ids to drain; empty means every one marked for cron.
   *
   * @return int
   *   Items processed.
   */
  private function drainQueues(float $deadline, array $only): int {
    $drained = 0;

    foreach ($this->queueWorkerManager->getDefinitions() as $id => $definition) {
      if (microtime(TRUE) >= $deadline) {
        break;
      }
      $wanted = $only === [] ? (bool) ($definition['cron'] ?? FALSE) : in_array($id, $only, TRUE);
      if (!$wanted) {
        continue;
      }

      $queueName = (string) ($definition['queue'] ?? $id);
      $queue = \Drupal::queue($queueName);
      $worker = $this->queueWorkerManager->createInstance($id);

      while (microtime(TRUE) < $deadline) {
        $item = $queue->claimItem();
        if ($item === FALSE) {
          break;
        }
        try {
          $worker->processItem($item->data);
          $queue->deleteItem($item);
          $drained++;
        }
        catch (\Throwable $e) {
          $queue->releaseItem($item);
          $this->logger->error(
            'Queue @id item failed in post-response work: @message',
            ['@id' => $id, '@message' => $e->getMessage()],
          );
          // One broken item must not hold the worker for the whole budget.
          break;
        }
      }
    }

    return $drained;
  }

  /**
   * Records the outcome for the status report and the assertions in tests.
   */
  private function record(int $drained, float $seconds, string $reason): array {
    $this->state->set('freeunit.post_response.last', [
      'drained' => $drained,
      'seconds' => round($seconds, 4),
      'reason' => $reason,
      'time' => \Drupal::time()->getRequestTime(),
    ]);

    return ['drained' => $drained, 'seconds' => $seconds, 'reason' => $reason];
  }

}
