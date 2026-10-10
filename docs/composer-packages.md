# Composer packages in this repository

Status: scaffold, 2026-10-10. The first package exists; the split below is the
intended shape as it grows.

The Go tool in the plan supervises the server. The PHP side of the same product
belongs in Composer packages, so a project can take the runtime integration
without the tool, and so each piece can be tested on its own (including by the
benchmarks in `freeunitorg/benchmarks-drupal`).

## Layout

| Path | Composer name | Type | Purpose |
|---|---|---|---|
| `packages/freeunit` | `freeunitorg/freeunit` | `drupal-module` | The Drupal integration: the Symfony Runtime runner, the post-response queue/cron worker, the control-socket status. |
| `packages/freeunit-runtime` (planned) | `freeunitorg/freeunit-runtime` | `library` | The runner classes without Drupal, so Symfony, Laravel, WordPress and a plain `APP_RUNTIME` can use them too. The Drupal module then depends on it. |
| `packages/ddev-freeunit` (planned) | `ddev/ddev-freeunit` | `ddev-addon` | The DDEV add-on tracked as T1 in the plan, for teams that keep Docker. |

## The Drupal package

`packages/freeunit` targets Drupal 11.5+ and 12, where core scaffolds
`web/autoload_runtime.php` and defaults `APP_RUNTIME` to
`Drupal\Core\Runtime\DrupalRuntime`. The module replaces that value with
`Drupal\freeunit\Runtime\FreeUnitRuntime`, which chooses between:

* a per-request runner that does bounded queue and cron work **after**
  `fastcgi_finish_request()` has detached the request (FreeUnit 1.37, today);
* a worker loop that boots Drupal once per process, for FreeUnit 1.38's
  `"worker": true` and `unit_handle_request()` (issue 596).

Because `APP_RUNTIME` is read before modules load, the value is set in the FreeUnit
**application environment**, not in Drupal. See the package README.

## Consuming it from a project

```sh
composer config repositories.freeunit path ../freeunit4drupal/packages/freeunit
composer require freeunitorg/freeunit:@dev
dr en freeunit
```

## Consuming it from the benchmarks

`freeunitorg/benchmarks-drupal` builds the Drupal sites with Composer, so the same
two commands make the module part of a measured site. The benchmark's Unit
application config then sets `APP_RUNTIME`, which is the only difference between a
stock run and a run through the module — which is what makes module overhead
measurable on the same cells as everything else.

## Drupal 11.5

Drupal 11.5 is expected to require **PHP 8.5**, which the benchmark images already
carry (8.5.11); older PHP lines are a separate decision and nothing here depends on
them. The package's own `php` constraint stays at 8.1 so the runtime classes can be
lifted into the framework-agnostic package without a requirement change.
