<?php

declare(strict_types=1);

namespace Drupal\freeunit\Runtime;

use Symfony\Component\Runtime\RunnerInterface;

/**
 * Runs post-response work after the client has been served.
 *
 * Symfony's HttpKernelRunner, which the parent runtime returns, already sends the
 * response, calls fastcgi_finish_request() (FreeUnit implements it as a detached
 * request) and terminates the kernel. Wrapping it means everything here runs with
 * the connection closed, inside the same worker, within the application's
 * "limits.timeout" budget -- which is the whole testing ground for FreeUnit's
 * not-yet-shipped schedules and queues.
 *
 * Nothing in here may change the response: it runs after the bytes have left, and a
 * failure must never surface as a 500.
 */
final class FreeUnitRunner implements RunnerInterface {

  public function __construct(
    private readonly RunnerInterface $runner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): int {
    $status = $this->runner->run();
    $this->postResponse();
    return $status;
  }

  private function postResponse(): void {
    if (!class_exists(\Drupal::class) || !\Drupal::hasContainer()) {
      return;
    }

    try {
      $container = \Drupal::getContainer();
      if (!$container->has('freeunit.post_response_worker')) {
        return;
      }
      $container->get('freeunit.post_response_worker')->runAfterResponse();
    }
    catch (\Throwable $e) {
      // The response is already sent; log and let the worker end normally.
      try {
        \Drupal::logger('freeunit')->warning(
          'Post-response work failed: @message',
          ['@message' => $e->getMessage()],
        );
      }
      catch (\Throwable) {
        // Nothing left to report through.
      }
    }
  }

}
