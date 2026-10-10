<?php

declare(strict_types=1);

namespace Drupal\freeunit\Command;

use Drupal\freeunit\Runtime\FreeUnitRuntime;
use Drupal\freeunit\Status\UnitClient;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;

/**
 * Reports how this site is running under FreeUnit.
 */
class FreeUnitStatusCommand extends DrushCommands {

  public function __construct(
    private readonly UnitClient $unitClient,
    private readonly StateInterface $state,
  ) {
    parent::__construct();
  }

  /**
   * Shows the FreeUnit runtime mode and the post-response work.
   *
   * @command freeunit:status
   * @aliases fus
   * @usage dr freeunit:status
   */
  public function status(): void {
    $rows = [
      ['SAPI', \PHP_SAPI],
      ['worker mode available', FreeUnitRuntime::workerMode() ? 'yes (unit_handle_request)' : 'no (FreeUnit 1.38 feature)'],
      ['detached request available', FreeUnitRuntime::detachedRequestsSupported() ? 'yes' : 'no'],
      ['control socket', $this->unitClient->socket()],
      ['control API reachable', $this->unitClient->isAvailable() ? 'yes' : 'no'],
    ];

    $last = $this->state->get('freeunit.post_response.last');
    if (is_array($last)) {
      $rows[] = ['last post-response', sprintf(
        '%d item(s) in %.3fs (%s)',
        $last['drained'] ?? 0,
        $last['seconds'] ?? 0,
        $last['reason'] ?? '?',
      )];
    }
    else {
      $rows[] = ['last post-response', 'never'];
    }

    $this->io()->table(['item', 'value'], $rows);

    $applications = $this->unitClient->get('applications');
    if (is_array($applications)) {
      $this->io()->writeln('');
      $this->io()->writeln('Applications in the server configuration:');
      $this->io()->listing(array_keys($applications));
    }
  }

}
