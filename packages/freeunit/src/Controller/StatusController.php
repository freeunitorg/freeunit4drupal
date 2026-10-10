<?php

declare(strict_types=1);

namespace Drupal\freeunit\Controller;

use Drupal\freeunit\Runtime\FreeUnitRuntime;
use Drupal\freeunit\Status\UnitClient;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The status report page.
 */
class StatusController extends ControllerBase {

  public function __construct(
    protected readonly UnitClient $unitClient,
    protected readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('freeunit.unit_client'),
      $container->get('state'),
    );
  }

  /**
   * Reports the runtime mode, the last post-response run and the server state.
   */
  public function report(): array {
    $rows = [
      [$this->t('PHP SAPI'), \PHP_SAPI],
      [
        $this->t('Worker mode'),
        FreeUnitRuntime::workerMode()
          ? $this->t('available (unit_handle_request)')
          : $this->t('not available; a FreeUnit 1.38 feature'),
      ],
      [
        $this->t('Detached request'),
        FreeUnitRuntime::detachedRequestsSupported() ? $this->t('available') : $this->t('unavailable'),
      ],
      [$this->t('Control socket'), $this->unitClient->socket()],
      [
        $this->t('Control API'),
        $this->unitClient->isAvailable() ? $this->t('reachable') : $this->t('not reachable'),
      ],
    ];

    $last = $this->state->get('freeunit.post_response.last');
    $rows[] = [
      $this->t('Last post-response work'),
      is_array($last)
        ? $this->t('@count item(s) in @seconds s (@reason)', [
          '@count' => $last['drained'] ?? 0,
          '@seconds' => $last['seconds'] ?? 0,
          '@reason' => $last['reason'] ?? '?',
        ])
        : $this->t('never'),
    ];

    $build['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Item'), $this->t('Value')],
      '#rows' => $rows,
      '#caption' => $this->t('FreeUnit runtime'),
    ];

    $applications = $this->unitClient->get('applications');
    if (is_array($applications)) {
      $build['applications'] = [
        '#type' => 'table',
        '#header' => [$this->t('Application'), $this->t('Type'), $this->t('Processes')],
        '#rows' => array_map(
          static fn (string $name, array $app): array => [
            $name,
            $app['type'] ?? '?',
            is_array($app['processes'] ?? NULL) ? json_encode($app['processes']) : '-',
          ],
          array_keys($applications),
          $applications,
        ),
        '#caption' => $this->t('Applications in the running server configuration'),
      ];
    }

    return $build;
  }

}
