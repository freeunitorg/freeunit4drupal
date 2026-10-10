<?php

declare(strict_types=1);

namespace Drupal\freeunit\Status;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * A small client for the FreeUnit control API over its unix socket.
 *
 * Unit exposes its whole configuration and its runtime state as JSON on a unix
 * socket (default /run/control.unit.sock). Reading it from inside a worker is how
 * the module reports what the server is doing with this application: how many
 * processes are running, how many requests each has served, whether the
 * application was restarted, and which limits apply.
 */
final class UnitClient {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * The configured socket path.
   */
  public function socket(): string {
    return (string) ($this->configFactory->get('freeunit.settings')->get('control_socket') ?: '/run/control.unit.sock');
  }

  /**
   * Whether the control socket is reachable from this process.
   */
  public function isAvailable(): bool {
    $socket = $this->socket();
    if (!is_readable($socket) && !file_exists($socket)) {
      return FALSE;
    }
    return $this->get('') !== NULL;
  }

  /**
   * GETs a path under /config, returning the decoded JSON or NULL.
   *
   * @param string $path
   *   A slash-separated path under /config, e.g. "applications/drupal".
   */
  public function get(string $path): mixed {
    if (!\function_exists('curl_init')) {
      return NULL;
    }
    $socket = $this->socket();
    if (!file_exists($socket)) {
      return NULL;
    }

    $url = 'http://localhost/config' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      \CURLOPT_UNIX_SOCKET_PATH => $socket,
      \CURLOPT_RETURNTRANSFER => TRUE,
      \CURLOPT_TIMEOUT => 2,
      \CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === FALSE || $code >= 400) {
      return NULL;
    }
    try {
      return json_decode((string) $body, TRUE, 32, \JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return NULL;
    }
  }

}
