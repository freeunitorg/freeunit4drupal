# freeunit (Drupal module)

FreeUnit is a community fork of NGINX Unit. Drupal 11.5 and 12 ship the Symfony
Runtime component and scaffold `web/autoload_runtime.php` with
`APP_RUNTIME=Drupal\Core\Runtime\DrupalRuntime`. That is the seam this module uses.

Two things are true about FreeUnit today, and they decide what the module does:

* **1.37 serves one request per worker request cycle**, and implements
  `fastcgi_finish_request()`, which Unit turns into a *detached request*. Anything
  that runs after it reaches the client first. That is where queue and cron work
  belongs until FreeUnit ships scheduling.
* **1.38 plans PHP worker mode** (`"worker": true`, a `unit_handle_request()` loop
  in which one process serves many requests and keeps state). When that exists, the
  module switches to a worker loop that boots Drupal once.

Symfony's `RunnerInterface` is the supported seam for both, so the module is a
runtime plus its runners — not a patch to Drupal.

## What it does

| Piece | Purpose |
|---|---|
| `Runtime\FreeUnitRuntime` | `APP_RUNTIME` target; chooses the runner from the server's capabilities. |
| `Runtime\FreeUnitRunner` | Wraps Symfony's `HttpKernelRunner`; runs bounded post-response work after the request was detached. |
| `Runtime\FreeUnitWorkerRunner` | The 1.38 worker loop (`unit_handle_request()`), rewritten after Symfony's `FrankenPhpWorkerRunner`; unreachable on 1.37. |
| `PostResponse\Worker` | Queue draining and optional `hook_cron()` inside a time budget, guarded by a non-blocking lock. |
| `Status\UnitClient` | Reads the running server's configuration over the Unit control socket. |
| `dr freeunit:status` / `/admin/reports/freeunit` | Reports SAPI, worker-mode availability, detach support, the last post-response run and the server's applications. |

## Install

```sh
composer require freeunitorg/freeunit
dr en freeunit
```

Then point the application at the runtime. The value is read before any module
loads, so it belongs in the server configuration:

```json
{
    "applications": {
        "drupal": {
            "type": "php",
            "environment": {
                "APP_RUNTIME": "Drupal\\freeunit\\Runtime\\FreeUnitRuntime",
                "UNIT_POST_RESPONSE": "1"
            },
            "limits": { "timeout": 300, "requests": 1500 },
            "processes": { "max": 20, "spare": 2, "idle_timeout": 120 }
        }
    }
}
```

The post-response budget must stay well under `limits.timeout`: the worker is
holding one of the application's processes while it drains.

## Configuration

`/admin/config/development/freeunit`:

* **enabled** — drain after the response.
* **budget_ms** — stop starting new items after this many milliseconds (default 2000).
* **queues** — queue worker plugin ids, one per line; empty means every worker
  Drupal marks for cron.
* **cron** — also invoke `hook_cron()` if the budget allows.
* **control_socket** — the `--control` unix socket of the running `unitd`.

## Why this is the testing ground for schedules and queues

FreeUnit's router cannot run a scheduled occurrence yet (1.38, issue 596), and
Drupal's queue workers assume a cron run. Attaching the work to the detached tail
of a normal request exercises the same questions the server feature will have to
answer, with the answers measurable today:

* how long a waiting worker may hold a process (budget vs `limits.timeout`),
* what happens when several workers race (the lock, and whether one drains),
* whether queue items are processed exactly once under detach,
* how `limits.requests` interacts with a worker that also does background work,
* what the server reports about a process that is serving a request and draining.

`dr freeunit:status` and the state entry `freeunit.post_response.last` are the
observability for all of it.

## Requirements

* Drupal 11.5+ or 12 (the Symfony Runtime scaffolding), PHP 8.5 for Drupal 11.5.
* `symfony/runtime` (already a Drupal dependency).
* FreeUnit 1.37+ for detached requests; 1.38+ for worker mode.
