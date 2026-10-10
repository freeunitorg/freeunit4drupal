<?php

declare(strict_types=1);

namespace Drupal\freeunit\Runtime;

use Drupal\Core\Runtime\DrupalRuntime;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * The FreeUnit runtime: Drupal's runtime with server-aware runners.
 *
 * Drupal 11.5 and later (and 12.0 from alpha1) scaffold web/autoload_runtime.php
 * with `APP_RUNTIME` defaulting to Drupal\Core\Runtime\DrupalRuntime, which extends
 * Symfony's SymfonyRuntime. That value is read before any module loads, so the only
 * way to replace the runtime is an environment variable, and the natural place to
 * set one is the FreeUnit application:
 *
 *   {
 *       "applications": {
 *           "drupal": {
 *               "type": "php",
 *               "environment": {
 *                   "APP_RUNTIME": "Drupal\\freeunit\\Runtime\\FreeUnitRuntime",
 *                   "UNIT_POST_RESPONSE": "1"
 *               },
 *               "options": { "admin": { "memory_limit": "512M" } }
 *           }
 *       }
 *   }
 *
 * This class only decides *which* runner runs; the runners hold the behaviour.
 *
 * Two FreeUnit realities shape it:
 *
 * - Today (1.37) the PHP SAPI serves one request per worker request cycle and
 *   implements fastcgi_finish_request(), which Unit turns into a detached request
 *   (nxt_unit_request_done_detached). Symfony's HttpKernelRunner already calls it
 *   after send() and before terminate(), so anything after that point reaches the
 *   client first. That is where post-response cron and queue work belongs.
 * - FreeUnit 1.38 plans PHP worker mode: a `"worker": true` target member and a
 *   unit_handle_request() loop in which one PHP process serves many requests and
 *   keeps state between them. When that function exists, this runtime hands back a
 *   worker loop that boots Drupal once, which is the point of the exercise: a
 *   Symfony Runtime RunnerInterface is the supported seam for exactly this.
 *
 * @see https://github.com/freeunitorg/freeunit/issues/596
 * @see https://github.com/freeunitorg/freeunit/pull/452
 */
class FreeUnitRuntime extends DrupalRuntime {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $options = []) {
    // Read the FreeUnit-specific switches from the environment, because the
    // application that selects this class is the application that sets them.
    $options['freeunit_worker_loop_max'] ??= (int) ($_SERVER['UNIT_WORKER_LOOP_MAX'] ?? 0);
    $options['freeunit_post_response'] ??= (bool) filter_var($_SERVER['UNIT_POST_RESPONSE'] ?? '1', \FILTER_VALIDATE_BOOL);
    parent::__construct($options);
  }

  /**
   * {@inheritdoc}
   */
  public function getRunner(?object $application): RunnerInterface {
    if ($application instanceof HttpKernelInterface) {
      if (self::workerMode()) {
        return new FreeUnitWorkerRunner($application, (int) ($this->options['freeunit_worker_loop_max'] ?? 0));
      }

      $runner = parent::getRunner($application);

      if (($this->options['freeunit_post_response'] ?? TRUE) === TRUE && self::detachedRequestsSupported()) {
        // parent::getRunner() returns Symfony's HttpKernelRunner, which sends the
        // response, finishes the request and terminates the kernel. Wrapping it
        // puts post-response work after all three, with the client already served.
        return new FreeUnitRunner($runner);
      }

      return $runner;
    }

    return parent::getRunner($application);
  }

  /**
   * Whether the server is running this process as a worker (FreeUnit 1.38+).
   */
  public static function workerMode(): bool {
    return \function_exists('unit_handle_request');
  }

  /**
   * Whether finishing the request before it has been fully handled is possible.
   *
   * Without it the client stays connected while post-response work runs, which is
   * worse than not doing the work at all.
   */
  public static function detachedRequestsSupported(): bool {
    return \function_exists('fastcgi_finish_request') || \function_exists('unit_finish_request');
  }

}
