<?php

declare(strict_types=1);

namespace Drupal\freeunit\Runtime;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * A runner for FreeUnit's PHP worker mode.
 *
 * FreeUnit 1.38 plans a PHP target member `"worker": true` and a
 * unit_handle_request() loop in which one PHP process serves many requests and
 * keeps application state between them (issue 596). The shape follows Symfony's
 * FrankenPhpWorkerRunner, which solves the same problem for FrankenPHP: loop on a
 * handler, handle + send + terminate per iteration, and let the server decide when
 * the worker ends.
 *
 * This file exists so the module can be tested the day the function appears; it is
 * not reachable on 1.37, where FreeUnitRuntime::workerMode() is false and the
 * per-request runner is used instead.
 *
 * The one difference from the FrankenPHP runner matters for Drupal: the kernel is
 * NOT cloned per request by default. Drupal's kernel accumulates state (the
 * container, the request stack, the state service) and a clone would share it
 * anyway, so the safe default is one kernel for the worker and an explicit
 * "dev" assertion that a fresh request context is established per iteration. If
 * testing shows cross-request leaks, set UNIT_WORKER_RESET_KERNEL=1 to clone the
 * kernel after each request, exactly like FRANKENPHP_RESET_KERNEL.
 *
 * @see https://github.com/freeunitorg/freeunit/issues/596
 */
final class FreeUnitWorkerRunner implements RunnerInterface {

  public function __construct(
    private HttpKernelInterface $application,
    private readonly int $loopMax = 0,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(): int {
    if (!\function_exists('unit_handle_request')) {
      throw new \LogicException('FreeUnit worker mode is not available in this server.');
    }

    // A client disconnect must not end the worker.
    ignore_user_abort(TRUE);

    $resetKernel = (bool) filter_var($_SERVER['UNIT_WORKER_RESET_KERNEL'] ?? '0', \FILTER_VALIDATE_BOOL);
    $server = array_filter(
      $_SERVER,
      static fn (string $key): bool => !str_starts_with($key, 'HTTP_'),
      \ARRAY_FILTER_USE_KEY,
    );
    $server['APP_RUNTIME_MODE'] = $resetKernel ? 'web=1&worker=2' : 'web=1&worker=1';

    $request = NULL;
    $response = NULL;

    $handler = function () use ($server, &$request, &$response): void {
      // Each iteration starts from the server's own variables, not the previous
      // request's.
      $_SERVER += $server;
      $request = Request::createFromGlobals();
      $response = $this->application->handle($request);
      $response->send();
    };

    $loops = 0;
    do {
      unit_handle_request($handler);

      if ($this->application instanceof TerminableInterface && $request instanceof Request && $response instanceof Response) {
        $this->application->terminate($request, $response);
      }

      if ($resetKernel) {
        $this->application = clone $this->application;
      }

      $request = $response = NULL;
      $loops++;
    } while ($this->loopMax <= 0 || $loops < $this->loopMax);

    return 0;
  }

}
