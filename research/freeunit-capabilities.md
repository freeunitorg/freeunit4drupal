# FreeUnit capabilities and gaps for a local Drupal development tool (freeunit4drupal)

What the FreeUnit server offers a Docker-free local Drupal environment today, what is missing, and what the tool must do itself. Researched on 2026-10-07.

How to read the anchors and sources:

- Code anchors are `path:line@872bf041`. 872bf041 is freeunitorg/freeunit `origin/master` on 2026-10-07 (commit 872bf04170756e919211799ec5574000d628d521). Every anchor resolves to `https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/<path>#L<line>`. Example: [src/nxt_php_sapi.c:302@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_php_sapi.c#L302).
- "CHANGES:N" means line N of the CHANGES file at 872bf041: [CHANGES](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/CHANGES).
- The latest release is 1.37.0, tagged 2026-10-04 ([release](https://github.com/freeunitorg/freeunit/releases/tag/1.37.0)). CHANGES on master already has a 1.37.1 block dated 04 Oct 2026, but no 1.37.1 tag existed on 2026-10-07.
- Other pinned sources: php-src master `fb602cc680ae34baa4f0a45933d8825d1f97577a` (8.6.0-dev); Xdebug master `64007df3a0925808022fb87b6c6f06febf058ee2`; the Drupal howto as served from freeunitorg/docs `main` on 2026-10-07 (newer than docs commit a81d80f); Drupal core `main` as of October 2026 (quoted code, because drupal.org branch lines move); Drush 13.6.2.
- Method: code reading at the pinned revisions, the offline archives of nginx/unit issues (complete to its archive date 2025-10-08) and freeunitorg/freeunit issues (snapshot of 2026-09-11), and a few live GitHub reads. Nothing was built or run for this note. A claim is a code reading unless it says "measured".
- The briefing had several stale claims. They are marked CHANGED or WRONG where they come up. The largest: conditional GET, byte ranges and the Last-Modified time zone fix all shipped in 1.37.0; the terminate-phase capacity bug is fixed in 1.37.0; the TrueAsync code is gone; packages.freeunit.org has no Linux packages; the Homebrew tap still builds NGINX Unit 1.34.2.

## 1. Capability matrix: developer need, FreeUnit today, gap, effort

### Takeaway
FreeUnit 1.37.0 can already serve a Drupal site locally with clean URLs, HTTPS from a local CA, several PHP versions side by side, a per-project php.ini, Xdebug by trigger, cache validators and byte ranges for static files, a JSON access log and a status API, all from one JSON configuration. The gaps a dev tool hits are mostly outside the request path: no native packages for macOS or most Linux distributions, a broken application path on Apple Silicon in 1.37.0 (fixed only in the untagged 1.37.1), no supervision of helper processes, no file watching, one global access log, per-app log files ignored in foreground mode, and a bare "Error 503." page. Two defaults bite Drupal developers: one PHP worker per application, and an 8 MiB request body limit.

### Cited Findings

| Developer need | FreeUnit today (verified) | Gap | Effort and where |
|---|---|---|---|
| Clean URLs and the .htaccess protections | Routes with `match`, `share` and `fallback` to `index.php`. The published howto translates Drupal's .htaccess ([howto](https://github.com/freeunitorg/docs/blob/main/source/howto/drupal.rst) lines 43-120). | Unit does not read .htaccess. Globs are case-sensitive, so uppercase variants pass on a case-insensitive file system (howto lines 181-201). | Tool generates the route set per project. No server change. |
| One PHP version per project | Every `*.unit.so` in the modules directory is discovered (glob at src/nxt_application.c:268@872bf041, dlopen at :413). `"type": "php 8.4"` matches by version prefix and `"php"` takes the highest version (src/nxt_application.c:1674-1724@872bf041). | Modules are found by a discovery process at startup (src/nxt_application.c:142-149@872bf041), so a newly installed module needs a unitd restart (inference). | Tool installs modules and restarts unitd. |
| Per-project php.ini and extensions | `options.file` becomes `php_ini_path_override` before PHP starts (src/nxt_php_sapi.c:436, :760@872bf041). The app `environment` is set before PHP starts (src/nxt_application.c:599 before :607@872bf041), so `PHP_INI_SCAN_DIR` works. | `options.admin` and `options.user` are applied after `php_module_startup()` (src/nxt_php_sapi.c:445, :452, :455@872bf041), so they cannot load `zend_extension` and fail for startup-only settings. | Server feature: pass admin values as INI entries before startup, src/nxt_php_sapi.c, about 60-120 lines plus tests. |
| Xdebug on demand | A second application with Xdebug enabled through `PHP_INI_SCAN_DIR` or `XDEBUG_MODE`, selected by a route on a trigger argument or cookie. This pattern is published in [nginx/unit#969](https://github.com/nginx/unit/issues/969). Each application has its own PHP startup, so the extension set differs per app. | No built-in toggle. A paused breakpoint holds one worker. | Tool generates the twin app and route. No server change. |
| Code edits are picked up | opcache shared memory lives in the per-app prototype and workers fork from it. php-src defaults: `opcache.validate_timestamps=1`, `opcache.revalidate_freq=2`. | Up to 2 seconds of lag with defaults. No file watcher in the server. | Tool sets `opcache.revalidate_freq` to 0 through `options.admin`. |
| Restart one app, clear opcache | `GET /control/applications/<name>/restart` (src/nxt_controller.c:2917-2942@872bf041). The router sends QUIT to the prototype and bumps the generation (src/nxt_router.c:2096, :2081@872bf041). | None. | None. |
| See PHP errors | With no php.ini, PHP 8.3 to 8.5 default to `display_errors=1`, `html_errors=1`, `log_errors=0`. PHP log messages go to the unit log at notice level as "php message: ..." (src/nxt_php_sapi.c:1811-1835@872bf041). | Server-generated error pages are only "Error NNN." (src/nxt_http_error.c:19-22@872bf041). No configurable error page. | Server feature: opt-in reason text, src/nxt_http_error.c plus validator and router, about 150-300 lines. |
| Logs in the terminal | `--no-daemon` keeps unitd in the foreground (src/nxt_runtime.c:1334-1335@872bf041). The log is opened with O_APPEND and duplicated onto stderr (src/nxt_runtime.c:1497-1517@872bf041). The PHP Docker image links the log file to /dev/stderr (pkg/docker/Dockerfile.php-8.5:106@872bf041). | The per-app `stdout` and `stderr` files are ignored in foreground mode (src/nxt_application.c:1601@872bf041). | Server change: honour them in foreground mode, src/nxt_application.c:1601-1603, a few lines plus a test. |
| A request log per project | One global `access_log` (src/nxt_conf_validation.c:352@872bf041), as a string or a JSON object format with variables such as `$host` and `$status` (src/nxt_conf_validation.c:1615-1624@872bf041). | No per-listener, per-route or per-app access log. | Tool splits on `$host`. A server feature would be about 200-400 lines. |
| Why did a URL hit this route | `settings.http.log_route` logs each route step, "selected" at notice and "discarded" at info (src/nxt_http_route.c:1631-1639@872bf041). | No runtime log level setting. | None. |
| Media uploads | Bodies over `body_buffer_size` (16 KiB) go to an unlinked temp file under `body_temp_path`, default the `--tmpdir` (src/nxt_http_request.c:684-746, src/nxt_router.c:3420, :3466-3470@872bf041). `max_body_size` defaults to 8 MiB (src/nxt_router.c:3421@872bf041). | Uploads over 8 MiB get 413 until raised. PHP's own `post_max_size` 8M and `upload_max_filesize` 2M also apply. | Tool raises both limits together. |
| Long requests (batch, update.php, migrate) | `limits.timeout` defaults to none (src/nxt_router.c:3171@872bf041). When set, it answers 503 and does not stop the worker (src/nxt_router.c:8687, :8769@872bf041). | None for development. | None. |
| Parallel requests (BigPipe, AJAX, debugger) | With `processes` omitted, an app gets max 1 and spare 1 (src/nxt_router.c:3168-3173 with :3221-3224@872bf041). | One PHP request at a time per app by default. | Tool sets `processes` explicitly. |
| Drupal cron and queues | automated_cron runs after `fastcgi_finish_request()`. Since 1.37.0 the worker stays counted busy ("detached") until the script ends (CHANGES:478). | No scheduler in 1.37.0. "schedules" is an open PR ([PR 452](https://github.com/freeunitorg/freeunit/pull/452)) with milestone 1.37.1 (due 2026-10-30), while its description says it is for 1.38.0. | Tool runs `drush cron` on a timer until then. |
| Correct browser caching of assets | ETag, Last-Modified in GMT, 304, 412, single byte ranges with 206 and 416, `Accept-Ranges` (src/nxt_http_static.c:711-1084@872bf041; CHANGES:588, :623). | None. CHANGED from the 2026-09-12 probe. | None. |
| MIME types for front-end assets | svg, svgz (with `Content-Encoding: gzip`), webp, avif, woff, woff2, json, js, mjs, css, ico (src/nxt_http_static.c:2054-2125@872bf041). Extendable with `settings.http.static.mime_types`. | No `.wasm`, `.map` or `.webmanifest`. A file with an unknown extension gets no Content-Type (src/nxt_http_static.c:834-844@872bf041). | Server: 3 to 6 table lines plus a test. |
| HTTPS on `*.localhost` and 127.0.0.1 | Any PEM bundle can be uploaded to `/certificates/<name>`. SNI chooses an exact DNS name, then a one-level wildcard (src/nxt_openssl.c:1012-1029, :1113-1125@872bf041). | The tool must create and trust the CA. | Tool. |
| HTTP/2 | Absent. No ALPN and no h2 code in src/ at 872bf041. | HTTP/1.1 only. | Large. Not worth it for development. |
| Ports 80 and 443 without root | unitd runs as a normal user, and apps then run as that user (src/nxt_isolation.c:96-120@872bf041). The main process binds listeners (src/nxt_main_process.c:1763-1838@872bf041). | Ports below 1024 need an OS rule. | Tool picks high ports or sets the OS rule. |
| Several projects at once | One instance with host-based routes and one app per project, each app with its own prototype and opcache. Several instances under one user should also work (code reading, not run): shared memory names include the pid (src/nxt_port_memory.c:493-537@872bf041). | Each instance needs its own control socket, pid file, log, state dir, tmp dir and ports. | Tool. |
| Safe control API | Unix socket with a peer uid check (src/nxt_controller.c:891-906@872bf041), mode 0600 by default (src/nxt_listen_socket.c:133@872bf041). | A TCP control socket has no authentication (same function returns OK for non-AF_UNIX). | Tool uses a unix socket only. |
| Change config, keep state | PUT, POST and DELETE under `/config`. An app whose JSON did not change keeps its processes (src/nxt_router.c:3152-3154@872bf041). The config is stored as `conf.json` in the state dir (src/nxt_runtime.c:1012@872bf041). | No reload on file change. | Tool watches files and calls the API. |
| Health and status | `GET /status`: modules, connections, request totals, per-app processes (running, starting, unaccounted, idle, detached) and active requests (src/nxt_status.c:27-46, :220-229@872bf041). | None. | None. |
| Run MariaDB, Mailpit next to the site | `external` apps must link libunit and call `nxt_unit_init()`, which sends READY (src/nxt_unit.c:568, :698@872bf041). | FreeUnit cannot supervise ordinary daemons. | Tool supervises them. |
| Install without Docker | Debian 13 amd64 `.deb` files are attached to the 1.37.0 GitHub release. | No apt or rpm repository, no macOS formula for FreeUnit, FreeBSD port deleted. | Tool builds from source or bundles binaries. |

### Inferences
- The request path is ready for local Drupal work on 1.37.0. The weak points are installation, process supervision and developer feedback (logs, error pages), which a tool can cover.
- The default `processes` value is the most likely source of "the site hangs while I debug" reports. A tool should always write an explicit `processes` object.
- On macOS, the default volume format is case-insensitive, so the howto's case caveat matters most there. This is general platform knowledge, not checked in this research.

### Gaps
- No row in this matrix was exercised on a running server. The open questions in section 13 list the experiments.

## 2. PHP module: SAPI identity, php.ini, Xdebug, opcache, errors, uploads, versions

### Takeaway
The PHP module reports `PHP_SAPI` as "cli-server". The name most likely exists so that opcache starts on PHP 8.4 and older (an inference, see Gaps), and it changes nothing on Drupal's web paths, which only test for "cli". php.ini control is good when done through an ini file or `PHP_INI_SCAN_DIR`; `options.admin` is too late for extensions, JIT and Xdebug's mode. opcache is shared per application through the prototype. The capacity bug behind automated_cron is fixed in 1.37.0. Worker mode is planned for 1.38.0 and is not something a dev tool needs.

### Cited Findings
SAPI registration and the name "cli-server":
- The SAPI struct registers name "cli-server" and pretty name "unit" (src/nxt_php_sapi.c:300-303@872bf041). The internal extension is named "unit" (src/nxt_php_sapi.c:184-187@872bf041). CONFIRMED.
- Why "cli-server": until PHP 8.4, opcache only started for SAPI names on a fixed list, which included "cli-server" and not "unit". php-src removed that list in commit 3088d640684 "Remove the Opcache SAPI whitelist" (2025-08-02), first released in PHP 8.5.0. The removed list held "cli-server", "cgi-fcgi", "litespeed", "uwsgi", "fuzzer" and "frankenphp" among others ([php-src commit](https://github.com/php/php-src/commit/3088d640684)). This explanation is inferred from php-src; I found no maintainer statement about it in the nginx/unit archive.
- On php-src master, opcache treats only "cli" and "phpdbg" as CLI for `opcache.enable_cli` (ext/opcache/ZendAccelerator.c:3071-3072@fb602cc680a), so "cli-server" gets opcache by default ([source](https://github.com/php/php-src/blob/fb602cc680ae34baa4f0a45933d8825d1f97577a/ext/opcache/ZendAccelerator.c#L3071)).
- PHP looks for `php-<sapi name>.ini` in its search path, so FreeUnit's PHP reads a `php-cli-server.ini` if one exists there, before falling back to php.ini (main/php_ini.c:566-580@fb602cc680a, [source](https://github.com/php/php-src/blob/fb602cc680ae34baa4f0a45933d8825d1f97577a/main/php_ini.c#L566)).
- `executable_location` is NULL (src/nxt_php_sapi.c:337@872bf041), so `PHP_BINARY` is an empty string (php-src main/main_arginfo.h:86@fb602cc680a). Symfony's `PhpExecutableFinder` returns `PHP_BINARY` only for "cli", "cli-server" and "phpdbg" when it is non-empty; otherwise it uses `PHP_PATH` or searches `PATH` (symfony/process `PhpExecutableFinder.php` line 50 in v8.1.6, [source](https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Process/PhpExecutableFinder.php)). So code that spawns PHP from a web request (for example Composer runs started by Drupal's package manager) uses the app's `PATH` or `PHP_PATH`.
- Drupal core tests only for "cli" on web paths: `core/includes/errors.inc` (`if (PHP_SAPI === 'cli')` before printing a plain-text fatal error), `core/includes/install.inc`, `core/includes/utility.inc`, `DrupalKernel` (trusted host patterns are enforced when `PHP_SAPI !== 'cli'`), `CommandLineOrUnsafeMethod` (page cache policy), `SessionManager`, `StackMiddleware/Session`, `InstallerRedirectTrait` ([Drupal core](https://git.drupalcode.org/project/drupal/-/blob/main/core/includes/errors.inc)). Under FreeUnit all of these take the web branch, exactly as under php-fpm. The briefing's concern about errors.inc is WRONG in effect: nothing changes.
- The only Drupal file that tests for "cli-server" is the router script for PHP's built-in server, `.ht.router.php` (and its scaffold copy): `if (PHP_SAPI !== 'cli-server')` ([source](https://git.drupalcode.org/project/drupal/-/blob/main/core/assets/scaffold/files/ht.router.php)). It must not be used as a FreeUnit `script`.
- Drush refuses to run unless `php_sapi_name() == 'cli'` or `$_SERVER['argc'] > 0` (`Environment::verifyCLI()`, src/Config/Environment.php, Drush 13.6.2, [source](https://github.com/drush-ops/drush/blob/13.x/src/Config/Environment.php)). Drush therefore runs under a separate PHP CLI, never inside FreeUnit.

php.ini handling:
- `options.file` is resolved against `working_directory` when relative and set as `php_ini_path_override` before `php_module_startup()` (src/nxt_php_sapi.c:727-760, :436, :445@872bf041). PHP then loads only that file (or `php-cli-server.ini`/`php.ini` inside it if it is a directory), and still scans `PHP_INI_SCAN_DIR` or the compiled scan dir (main/php_ini.c:425-436, :618-632@fb602cc680a).
- `options.admin` is applied with `ZEND_INI_SYSTEM` and `options.user` with `ZEND_INI_USER`, both after startup (src/nxt_php_sapi.c:445, :452, :455@872bf041). Each value goes through the setting's `on_modify` handler at stage ACTIVATE, then replaces the entry's current value and its modifiable level (src/nxt_php_sapi.c:817-841@872bf041), so every request starts from it. A name that is not a registered INI directive fails with "setting PHP option ... failed" at error level (src/nxt_php_sapi.c:786, :822@872bf041). `zend_extension` and `extension` are php.ini parser tokens (php-src main/php_ini.c:159-160@fb602cc680a), not registered settings, so the lookup at src/nxt_php_sapi.c:822@872bf041 fails for them.
- Upstream report: `"opcache.jit": "1235"` in options fails with "setting PHP option "opcache.jit: 1235" failed" while the same line works in php.ini ([nginx/unit#785](https://github.com/nginx/unit/issues/785), open when the repository was archived). The code path is unchanged at 872bf041.
- Working pattern for extra ini files: `"environment": {"PHP_INI_SCAN_DIR": ":config/"}` loads the default php.ini and scan dir plus the extra directory ([nginx/unit#969 comment](https://github.com/nginx/unit/issues/969)). This works because the environment is set before PHP starts (src/nxt_application.c:599, :607@872bf041).
- `disable_functions` and `disable_classes` in options get special handling; `disable_classes` is skipped on PHP 8.5 and later (src/nxt_php_sapi.c:790-804@872bf041).
- PHP members: `root`, `script`, `index`, `targets`, `options` with `file`, `admin`, `user` (src/nxt_conf_validation.c:1062-1113@872bf041). The index default is "index.php" (src/nxt_php_sapi.c:555@872bf041). Without `script`, the URI maps to a file under `root`, and PATH_INFO is split at the first ".php/" (src/nxt_php_sapi.c:1158-1171@872bf041). `HTTPS=on` is set on TLS listeners (src/nxt_php_sapi.c:1699@872bf041).

Xdebug:
- PHP starts once per application, in the prototype: `nxt_proto_setup()` sets the environment, calls the module setup, then changes root and directory (src/nxt_application.c:569, :599, :607, :622, :632@872bf041). The module setup calls `php_module_startup()` (src/nxt_php_sapi.c:445, :1400-1405@872bf041). A `zend_extension` in php.ini is therefore loaded once in the prototype and inherited by every worker.
- Xdebug reads its mode once, in MINIT: `xdebug_lib_set_mode()` runs in `PHP_MINIT_FUNCTION(xdebug)`, and the `XDEBUG_MODE` environment variable overrides `xdebug.mode` (xdebug.c:498-517 and src/lib/lib.c:211-213 at 64007df3, [xdebug.c](https://github.com/xdebug/xdebug/blob/64007df3a0925808022fb87b6c6f06febf058ee2/xdebug.c#L498), [lib.c](https://github.com/xdebug/xdebug/blob/64007df3a0925808022fb87b6c6f06febf058ee2/src/lib/lib.c#L211)). `xdebug.mode` is `PHP_INI_SYSTEM` (xdebug.c:276). So `XDEBUG_MODE` in the app `environment` or `xdebug.mode` in an ini file works; `xdebug.mode` in `options.admin` arrives after MINIT and most likely has no effect (inference, see section 13).
- Per-request triggers: Xdebug checks `XDEBUG_TRIGGER`, then falls back to `XDEBUG_SESSION` (src/lib/lib.c:506-525 at 64007df3). FreeUnit fills `$_GET`, `$_POST` and `$_COOKIE` per request, so cookie and query triggers work per request; an environment trigger would be per application.
- Xdebug in any mode other than "off" replaces `zend_execute_ex` (src/base/base.c:1174-1187 at 64007df3; MINIT returns early when the mode is off, xdebug.c:512-513). php-src then disables JIT with the warning "JIT is incompatible with third party extensions that override zend_execute_ex(). JIT disabled." (ext/opcache/jit/zend_jit.c:3758-3766@fb602cc680a). This is PHP behaviour, not FreeUnit's. Because each FreeUnit app starts its own PHP, a JIT app and an Xdebug app can run side by side.
- Archive search: two nginx/unit issues mention Xdebug, and neither is about it: [nginx/unit#969](https://github.com/nginx/unit/issues/969) (the routing recipe) and [nginx/unit#1546](https://github.com/nginx/unit/issues/1546) (an open prototype start failure in an image that installs Xdebug). The fork snapshot (items filed up to 2026-09-11) has none.

opcache:
- Measured on 2026-09-12 (FreeUnit 1.36.0, PHP 8.5.4): opcache hits rose monotonically across different worker pids, and `num_cached_scripts` stayed at 629 across two worker deaths ([finding](https://github.com/andypost/freeunit-drupal/blob/master/docs/findings/opcache-shm-lives-in-prototype.md)). The code anchors above still show the same structure at 872bf041.
- php-src defaults: `opcache.enable=1`, `opcache.validate_timestamps=1`, `opcache.revalidate_freq=2`, `opcache.jit=disable` (ext/opcache/zend_accelerator_module.c:292, :294, :308, :349@fb602cc680a). `revalidate_freq` and `validate_timestamps` are `PHP_INI_ALL`, so `options.admin` can set them.
- The restart endpoint quits the prototype (src/nxt_router.c:2096@872bf041), so the next start has a cold opcache. A config change to the app's JSON also creates a new app and prototype (src/nxt_router.c:3152-3154@872bf041), as the howto says ([howto "Opcache and idle processes"](https://github.com/freeunitorg/docs/blob/main/source/howto/drupal.rst), line 314 onward).
- Upstream fixed per-process opcache state in 2021: [nginx/unit#559](https://github.com/nginx/unit/issues/559) reported that `opcache_reset()` and `opcache_invalidate()` worked per process, and the maintainer pointed to commit e207415a, "Introducing application prototype processes" (2021-11-09), as the fix.

Errors and PHP logging:
- The SAPI has no `ini_defaults` and no `ini_entries` (src/nxt_php_sapi.c:352, :355@872bf041). Without a php.ini, PHP's built-in defaults apply: `display_errors=1`, `display_startup_errors=1`, `html_errors=1`, `log_errors=0`, `error_log` unset, `max_execution_time=30` (main/main.c:803-845@fb602cc680a; the same three error defaults in the php-8.3.0, php-8.4.0 and php-8.5.0 tags). So a fresh setup shows errors in the HTML response, which suits development.
- PHP log messages go through the SAPI `log_message` callback to libunit at notice level as "php message: %s" (src/nxt_php_sapi.c:1811-1835@872bf041). libunit writes to fd 2 of the worker (src/nxt_application.c:1809, src/nxt_unit.c:8491-8526@872bf041), which is the unit log unless a daemon-mode `stderr` file is set (section 6). Drupal's `errors.inc` calls `error_log()` for fatal errors, so they reach the unit log even with `log_errors=0`.

Request bodies and uploads (PHP side):
- `read_post` calls `nxt_unit_request_read()` (src/nxt_php_sapi.c:1555-1578@872bf041). libunit reads the buffered part, then the rest from the file descriptor the router passed (src/nxt_unit.c:3545-3546@872bf041). PHP's multipart parser then writes each file to `upload_tmp_dir` (default unset, so the system temp dir). php-src defaults: `post_max_size=8M`, `upload_max_filesize=2M` (main/main.c:849-851@fb602cc680a).

fastcgi_finish_request and the capacity bug:
- `fastcgi_finish_request()` is defined at src/nxt_php_sapi.c:231@872bf041 and now calls `nxt_unit_request_done_detached()` (src/nxt_php_sapi.c:282@872bf041, libunit at src/nxt_unit.c:3792@872bf041). CHANGED: before 1.37.0 it called plain `request_done`.
- CHANGES:478: "an application that answered a request and kept running was counted idle ... Such a worker now reports itself busy until the work ends, so "max" bounds live processes and "/status" reports them as "detached"."
- [freeunit#321](https://github.com/freeunitorg/freeunit/issues/321) was closed on 2026-09-18 in milestone 1.37. The published howto states "Unit 1.37.0 fixes this" and links [PR 405](https://github.com/freeunitorg/freeunit/pull/405) (howto line 261).
- Drupal 10 to 11.3 detach through `Response::send()`, which calls `fastcgi_finish_request()`. Drupal 11.4 and later detach through symfony/runtime's `HttpKernelRunner`, which calls it unless `APP_DEBUG` is on ([finding](https://github.com/andypost/freeunit-drupal/blob/master/docs/findings/drupal-version-boundary.md); howto line 433).

Worker mode and TrueAsync:
- [freeunit#596](https://github.com/freeunitorg/freeunit/issues/596) is open. It plans a PHP target member `"worker": true` and `unit_handle_request()` for 1.38.0, with wave 1 targeted at 2026-10-26.
- CHANGED: the TrueAsync code path was removed (CHANGES:869; commit b20aeb78, 2026-09-17). src/nxt_php_sapi.c is 1835 lines at 872bf041 with no async path.
- No `worker`, `async` or `entrypoint` member exists in the PHP validator tables (src/nxt_conf_validation.c:1062-1158@872bf041).

PHP versions and module loading:
- configure has no PHP version floor. It needs a working `php-config` (auto/modules/php:120-122@872bf041) and the embed SAPI (auto/modules/php:142-166@872bf041). Its only version branch adds `-Wno-write-strings` for PHP 5.0 to 5.3 (auto/modules/php:85-87@872bf041). PHP 8.5 code is gated by a configure probe (`NXT_PHP_PRE_REQUEST_INIT`, auto/modules/php:205-236 and src/nxt_php_sapi.c:358@872bf041).
- The module file name comes from `--module=NAME`, defaulting to the php-config base name (auto/modules/php:18, :56-58@872bf041). The Debian packages build `--module=php8.5` against `php-config8.5` (pkg/deb/Makefile.php85:2, :9@872bf041). The Docker images build the default name from the official `php:8.5-cli-trixie` base (pkg/docker/Dockerfile.php-8.5:1@872bf041).
- Several versions side by side: discovery globs every module, and each app selects one by type and version (section 1 anchors). The 1.37.0 release ships `freeunit-php8.3`, `freeunit-php8.4` and `freeunit-php8.5` packages for the same Debian release ([release assets](https://github.com/freeunitorg/freeunit/releases/tag/1.37.0)).

### Inferences
- Do not rename the SAPI. Renaming would disable opcache on PHP 8.4 and older and would not change any Drupal behaviour. Document the name instead.
- The cleanest per-project PHP setup for a tool is: one generated ini directory per project, passed through `PHP_INI_SCAN_DIR` with a leading ":" so the system ini still loads, plus `options.admin` only for runtime settings such as `opcache.revalidate_freq`.
- The Xdebug twin-app pattern gives the "Xdebug only when triggered" behaviour that DDEV and Lando users expect, with no slowdown on untriggered requests, because the untriggered app never loads Xdebug.
- A tool should set `PATH` or `PHP_PATH` in each app's `environment` to the matching PHP CLI, so Composer runs started from the web use the same PHP version as the module.
- Worker mode keeps application state between requests. A dev tool wants each request to see fresh code, so worker mode should stay off by default even after 1.38.0.

### Gaps
- Whether `xdebug.mode` in `options.admin` has any effect was not run. The source says it should not.
- The pcntl_fork re-entry trap from the 2026-09-12 coordination log (a forked child that calls `exit()` returns into the request loop and serves traffic) was measured on 1.36.0. I found no fork guard in src/nxt_php_sapi.c or src/nxt_unit.c at 872bf041 (searched for "fork" and "getpid"). Not re-tested.
- No source states why upstream chose "cli-server". The opcache list is the most likely reason, not a proven one.

## 3. Static files and HTTP features

### Takeaway
The briefing's static-file gaps are CHANGED: 1.37.0 added conditional requests, byte ranges, weak validators inside the modification second and a GMT Last-Modified. The remaining gaps are small: a few MIME types, no Content-Type for unknown extensions, and no HTTP/2.

### Cited Findings
- Share members: `share` (string or array, with variables), `index`, `types`, `fallback`, `chroot`, `follow_symlinks`, `traverse_mounts` (src/nxt_conf_validation.c:887-925@872bf041). `chroot`, `follow_symlinks` and `traverse_mounts` need `openat2` and are rejected as unsupported elsewhere, so they are Linux-only (src/nxt_conf_validation.c:907-925@872bf041). The share index default is "index.html" (src/nxt_http_static.c:241-242@872bf041). A directory without a trailing slash gets 301 (src/nxt_http_static.c:1189@872bf041).
- Last-Modified: CHANGED. 1.36.1 used `nxt_localtime()` (src/nxt_http_static.c:704 at tag 1.36.1). Commit fd2d1501 (2026-09-12, "static: send Last-Modified in GMT, not local time") switched to `nxt_gmtime()` (src/nxt_http_static.c:718@872bf041), and it is in the 1.37.0 tag. `nxt_http_date()` still appends a literal " GMT" (src/nxt_http.h:431-445@872bf041), which is now correct. CHANGES has no entry for this fix.
- Conditional requests: CHANGED. CHANGES:623: a share answers `If-None-Match` or `If-Modified-Since` with 304, compares entity tags weakly, and evaluates `If-Match` and `If-Unmodified-Since` first with 412. Code: preconditions at src/nxt_http_static.c:904@872bf041.
- Byte ranges: CHANGED. CHANGES:588: single ranges "A-B", "A-" and "-N" get 206 with `Content-Range`; unsatisfiable ones get 416; multi-range requests get the full 200; `If-Range` is honoured; `Accept-Ranges: bytes` is sent on a 200. Code: src/nxt_http_static.c:948-1084@872bf041.
- Validators: the ETag and Last-Modified are weak while the request falls inside the second the file was last written (CHANGES:842; src/nxt_http_static.c:781@872bf041). This matters in development, where files change often.
- MIME table (src/nxt_http_static.c:2054-2125@872bf041): html, htm, css, svg, svgz, webp, png, apng, jpeg, jpg, gif, ico, avif, avifs, woff, woff2, otf, ttf, txt, md, rst, js, mjs (both `application/javascript`), json, xml, rss, atom, pdf, zip, mp3, ogg, midi, mid, flac, aac, wav, mpeg, mpg, mp4, webm, avi, several `application/octet-stream` types, and php. Missing: `.wasm`, `.map`, `.webmanifest`, `.csv`. A `.svgz` file is served with `Content-Encoding: gzip` since 1.37.0 (CHANGES:372-390).
- Unknown extensions: the Content-Type field is added only when the type is known (src/nxt_http_static.c:834-844@872bf041).
- A share serves any file its route reaches. The `.php` entry (src/nxt_http_static.c:2125@872bf041) only sets the Content-Type of such a file to `application/x-httpd-php`. [freeunit#323](https://github.com/freeunitorg/freeunit/issues/323) is open and is about the guards: a `types` denylist passes unmapped extensions, and `index` skips the `types` check. The issue retracted its earlier framing that blamed the MIME table.
- Compression: `settings.http.compression` with `types` and `compressors` (`encoding`, `level`, `min_length`) (src/nxt_conf_validation.c:517-542@872bf041). Encoders are gzip, zstd and brotli when built with `--zlib`, `--zstd`, `--brotli` (auto/options:28-30, :117-119@872bf041). `Vary: Accept-Encoding` is merged into the response (src/nxt_http_compression.c:1085-1116@872bf041). Since 1.37.0 a content-coded response carries a weak ETag (CHANGES:634).
- HTTP/2: absent. A search for "alpn", "h2" and "http/2" in src/ finds only a comment and Cargo lock files at 872bf041. Upstream [nginx/unit#388 "HTTP/2 support"](https://github.com/nginx/unit/issues/388) was open when the repository was archived.

### Inferences
- A dev tool can rely on FreeUnit's validators for asset caching. Hard refresh and devtools "disable cache" behave as with nginx.
- Missing `.wasm` breaks `WebAssembly.instantiateStreaming()`, which requires `application/wasm`. Missing `.map` and `.webmanifest` only produce browser warnings. All three are a few table lines.
- HTTP/1.1 limits browsers to a handful of parallel connections per host. With CSS and JS aggregation off in development, page loads may be slower than behind an HTTP/2 server. This is unmeasured.

### Gaps
- No measurement of asset-heavy page loads over HTTP/1.1 against HTTP/2 for a Drupal admin page.

## 4. Routing and the published Drupal howto

### Takeaway
Routing covers everything Drupal's .htaccess does on the request path, and the howto already ships a careful translation with security warnings and a cron section. It does not cover TLS, upload limits, multi-site host routing or local development. It explains `limits.timeout` and `idle_timeout` only inside its cron section and gives no development values.

### Cited Findings
- Match members: `method`, `scheme`, `host`, `source`, `destination`, `uri`, `query`, `arguments`, `headers`, `cookies`, `if` (src/nxt_conf_validation.c:786-834@872bf041).
- Actions: `pass` (src/nxt_conf_validation.c:862@872bf041), `return` with `location` (:874, :878), `share` (:889), `proxy` (:934). Every action also takes `rewrite` and `response_headers` (:845, :850).
- Variables: `$dollar`, `$request_time`, `$method`, `$request_uri`, `$uri`, `$host`, `$remote_addr`, `$time_local`, `$request_line`, `$request_id`, `$status`, `$body_bytes_sent`, `$header_referer`, `$header_user_agent`, `$response_header_connection`, `$response_header_content_length`, `$response_header_transfer_encoding` (src/nxt_http_variables.c:57-127@872bf041), plus the prefixes `header_`, `arg_`, `cookie_` and `response_header_` (src/nxt_http_variables.c:138-200@872bf041). njs template literals and `settings.js_module` exist only in builds with `NXT_HAVE_NJS` (src/nxt_conf_validation.c:378-388@872bf041).
- Listener members include `forwarded` and `client_ip` for proxies (src/nxt_conf_validation.c:550-577@872bf041).
- The howto ([freeunitorg/docs source/howto/drupal.rst](https://github.com/freeunitorg/docs/blob/main/source/howto/drupal.rst), live on 2026-10-07, 440 lines) has four steps:
  1. A deny list answered with `"return": 404`. It starts with `"!*/.well-known/*"` and `"/vendor/*"` and covers `*.engine`, `*.inc`, `*.install`, `*.make`, `*.module`, `*.po`, `*.profile`, `*.sh`, `*.theme`, `*.tpl`, `*.twig`, `*.xtmpl`, `*.yml`, `*/.*`, `*/Entries*`, `*/Repository`, `*/Root`, `*/Tag`, `*/Template`, `*/composer.json`, `*/composer.lock`, `*/web.config`, `*sql`, `*.bak`, `*.orig`, `*.save`, `*.swo`, `*.swp`, `*~` (lines 43-74).
  2. A list of PHP entry points passed to the `direct` target: `/core/authorize.php`, `/core/install.php`, `/core/rebuild.php`, `/index.php`, `/index.php/*`, `/update.php`, `/update.php/*` and a few more (lines 84-100).
  3. The catch-all: `"uri": ["!/index.php", "!/index.php/*", "*.php"]` answered with 404 (lines 104-115).
  4. A `share` of the `web/` directory with `"Cache-Control": "max-age=31536000"` and a `fallback` to the `index` target, whose `script` is `index.php` (lines 116-141).
- The howto warns that route order matters, that `types` is only a safety net, and that the globs are case-sensitive, so uppercase variants such as `/core/composer.JSON` pass (lines 142-201). It also says the configuration came from the archived upstream docs (line 201).
- The howto has a "Cron and background work" section with the 1.37.0 fix, automated_cron, opcache, bounding a job with `pcntl_alarm()`, and deploy behaviour (lines 235-440).
- The deny list mirrors Drupal's `FilesMatch` and `RewriteRule "/\.|^\.(?!well-known/)" - [F]` rules in core's .htaccess ([Drupal .htaccess](https://git.drupalcode.org/project/drupal/-/blob/main/.htaccess)). Core's .htaccess also serves pre-compressed `css_*.css.gz` and `js_*.js.gz` aggregates with `Content-Encoding: gzip` when the client accepts gzip, and sets `AddEncoding gzip svgz`.

### Inferences
- What the howto does not cover, and a dev tool must add: TLS listeners and certificates; `settings.http.max_body_size` for uploads; PHP upload limits; `processes` sizing; several sites in one instance (host matching per project); `trusted_host_patterns` in `settings.php` for `*.localhost` names (Drupal enforces them on every web request, see section 2); the `forwarded` object when the tool puts a proxy in front; local Xdebug routing; and logging per project.
- The pre-compressed aggregate rule has no equivalent in the howto. FreeUnit serves the plain `.css` and `.js` aggregates, and response compression can replace the `.gz` files if configured. This is a size difference only, not a functional one.
- Image style derivatives and private files work through the `fallback` to `index.php`, because the derivative file does not exist on the first request and `/system/files/...` is not a file.

### Gaps
- The howto's route set has not been checked against Drupal 11.4 or 12 contributed modules that add their own PHP entry points.

## 5. TLS and certificates

### Takeaway
A locally generated CA chain can be uploaded and served. SNI handles exact names and one-level wildcards, and the first bundle is the default when there is no SNI, as for `https://127.0.0.1`. 1.37.0 added in-place replacement and fingerprints. ACME support is an external-client recipe and does not apply to local names.

### Cited Findings
- `PUT`, `GET` and `DELETE` on `/certificates` are handled by the controller (src/nxt_controller.c:1391-1399, and `nxt_controller_process_cert()` at :2074-2224@872bf041). Bundles are stored under `certs/` in the state dir (src/nxt_runtime.c:1027@872bf041).
- At upload the private key must match the first certificate (`X509_check_private_key`, src/nxt_cert.c:354@872bf041; CHANGES:458). The upload path has no `X509_verify` or `X509_STORE` call, so the chain is not checked against a trust store. `GET` returns `validity` and a SHA-256 `fingerprint` (src/nxt_cert.c:793-955@872bf041; CHANGES:412).
- `PUT /certificates/<name>` replaces a bundle in place; new handshakes get the new certificate; a bundle over 1 MiB gets 413 (CHANGES:416-427).
- Listener `tls` members: `certificate` (string or array), `conf_commands`, `session` with `cache_size`, `timeout`, `tickets` (src/nxt_conf_validation.c:630-666@872bf041).
- SNI: names come from the DNS entries of subjectAltName (src/nxt_openssl.c:857-867@872bf041). The CN is used only when the certificate has no subjectAltName extension (src/nxt_openssl.c:898-907@872bf041), so a certificate whose SANs are all IP addresses registers no name. A `*.x` name is stored as `.x` and must have a dot after the star (src/nxt_openssl.c:1012-1029@872bf041). Lookup tries the exact name, then the name with its first label removed (src/nxt_openssl.c:1113-1125@872bf041), so a wildcard covers one level only.
- Default bundle: "If there's no match or no server name was sent, Unit uses the first bundle on the list" ([docs source/configuration/index.rst](https://github.com/freeunitorg/docs/blob/main/source/configuration/index.rst), SNI section). In code, the router requests the bundles from the tail of its queue (src/nxt_router.c:2415@872bf041) and prepends each reply (:4507-4508), so the first array element becomes `conf->bundle`, which `SSL_new()` uses (src/nxt_openssl.c:1214-1216@872bf041).
- ACME: docs/acme.md at 872bf041 is titled "ACME certificates with an external client". certbot or lego obtains the certificate, and a deploy hook PUTs the bundle; renewal needs no restart ([docs/acme.md](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/docs/acme.md)). On the docs site, `source/certificates.rst` covers renewal with an ACME client, including lego 5 ([certificates.rst](https://github.com/freeunitorg/docs/blob/main/source/certificates.rst)), and `source/howto/certbot.rst` covers Certbot.
- `HTTPS=on` reaches PHP on TLS listeners (src/nxt_php_sapi.c:1699@872bf041).

### Inferences
- For `https://<project>.localhost`, a tool can upload one bundle per project or one bundle with several SANs. A single `*.localhost` wildcard is accepted by FreeUnit's SNI code, but browsers may reject a wildcard directly under a reserved top-level name. Use per-project names or `*.<project>.localhost`.
- ACME does not apply: public CAs do not issue for `.localhost`.

### Gaps
- Browser acceptance of `*.localhost` wildcard certificates was not tested.

## 6. Listeners, control socket, CLI, instances, status and logging

### Takeaway
unitd runs well as an unprivileged foreground process with all paths on the command line, and several instances can coexist. The control API should stay on a unix socket. Logging is one server log plus one access log; the per-app log files only work in daemon mode. Privileged ports need an OS-specific rule.

### Cited Findings
- CLI options parsed at 872bf041: `--control`, `--control-mode`, `--control-user`, `--control-group`, `--user`, `--group`, `--pid`, `--log`, `--modulesdir`, `--statedir`, `--tmpdir`, `--no-daemon`, `--version`, `--help`, plus the deprecated `--modules`, `--state`, `--tmp` (src/nxt_runtime.c:1168-1344@872bf041). Defaults come from configure (auto/options:75-100@872bf041); the default control address is `unix:$runstatedir/control.unit.sock` (auto/help:39@872bf041) and the default socket mode is 0600 (src/nxt_listen_socket.c:133@872bf041; help text at src/nxt_runtime.c:1126-1127).
- There is no `--foreground` flag; `--no-daemon` is the foreground mode. The log file is opened `O_WRONLY | O_APPEND | O_CREAT` and duplicated onto stderr in both modes (src/nxt_runtime.c:352, :1497-1517@872bf041). Daemon mode sends stdin and stdout to /dev/null (src/nxt_process.c:1424-1437@872bf041). The PHP Docker image runs `unitd --no-daemon` with its log file linked to `/dev/stderr` (pkg/docker/Dockerfile.php-8.5:106, :116@872bf041). The image reaches the terminal by linking the log path to /dev/stderr. `--log /dev/stderr` does the same directly, because the log file is opened by name and then duplicated onto stderr (src/nxt_file.c:856-862@872bf041).
- Per-app `stdout` and `stderr` members exist (src/nxt_conf_validation.c:1367-1375@872bf041), but `nxt_app_set_logs()` returns early when not a daemon (src/nxt_application.c:1586-1635, check at :1601-1603@872bf041). It runs only in processes forked by the prototype (src/nxt_process.c:843-845@872bf041), so the prototype, where `php_module_startup()` runs, never gets the redirect, even in daemon mode. A maintainer confirmed "The stdout/stderr options only take effect when running as a daemon" ([nginx/unit#915](https://github.com/nginx/unit/issues/915)).
- No runtime log level: the settings object has only `listen_threads`, `http`, `telemetry` and `js_module` (src/nxt_conf_validation.c:361-388@872bf041).
- `log_route` output is in the normal log, at notice and info (src/nxt_http_route.c:1631-1639@872bf041).
- Access log: `path`, `format` (string or JSON object) and `if` (src/nxt_conf_validation.c:1615-1624@872bf041). The object form replaces invalid UTF-8 with U+FFFD since 1.37.0 (CHANGES:532). `if` is a truthiness test with an optional leading `!` and no regex operator (src/nxt_tstr.c:204-211@872bf041).
- Peer-credential gate: `nxt_controller_check_peer_cred()` returns `NXT_OK` early when the peer is not `AF_UNIX` (src/nxt_controller.c:891-906@872bf041). CONFIRMED: a TCP control socket has no authentication.
- Listening sockets are created and bound by the main process (src/nxt_main_process.c:1763, bind at :1814, `EACCES` mapped at :1838@872bf041).
- Unprivileged unitd: apps must run as unitd's own user and group, otherwise "cannot set user ... missing capabilities" (src/nxt_isolation.c:96-120@872bf041).
- Several instances: shared memory is anonymous or named with the pid (src/nxt_port_memory.c:493-537@872bf041), and the state, log, pid, control and tmp paths are all flags.
- State dir layout: `version`, `conf.json`, `certs/`, `scripts/` (src/nxt_runtime.c:997-1043@872bf041). 1.37.1 (untagged) fixes a startup failure when the `version` file ends with a newline (CHANGES:19).
- `/status` fields: `modules` (`version`, `lib`), `connections` (`accepted`, `active`, `idle`, `closed`), `requests.total`, and per application `processes` (`running`, `starting`, `unaccounted`, `idle`, `detached`) and `requests.active`; `telemetry` when built with OpenTelemetry (src/nxt_status.c:27-46@872bf041).
- Event engines: kqueue where available (macOS, FreeBSD), epoll on Linux (src/nxt_service.c:12-32@872bf041).
- Linux: `ip_unprivileged_port_start` "defines the first unprivileged port in the network namespace. Privileged ports require root or CAP_NET_BIND_SERVICE", default 1024 ([kernel ip-sysctl docs](https://docs.kernel.org/networking/ip-sysctl.html)).
- macOS: since Mojave, non-root processes may bind ports below 1024. Apple DTS called it "an intentional change". The same thread reports that binding a specific address, as opposed to the wildcard address, still fails with error 13 for non-root ([Apple developer forums thread 674179](https://developer.apple.com/forums/thread/674179)).

### Inferences
- A dev tool should start unitd as `unitd --no-daemon --control unix:<dir>/control.sock --pid <dir>/unitd.pid --log /dev/stderr --statedir <dir>/state --tmpdir <dir>/tmp --modulesdir <dir>/modules`, under a user service manager (systemd user unit or launchd agent).
- On macOS, a listener on `127.0.0.1:443` started by a non-root unitd will probably fail, while `*:443` will work but listens on every interface. Without root, the safe default is high ports on 127.0.0.1.
- Per-project PHP error files need daemon mode today, or a server change (section 11).

### Gaps
- The macOS port behaviour with unitd was not tested (section 13).

## 7. Application types and the `external` type

### Takeaway
FreeUnit runs language apps, not arbitrary daemons. `external` needs a libunit program. MariaDB, PostgreSQL, Mailpit, Redis and Solr stay outside.

### Cited Findings
- Accepted types: `external` (alias `go`), `python`, `php`, `perl`, `ruby`, `java`, `wasm-wasi-component`, `wasm` (src/nxt_application.c:1727-1757@872bf041; validator table at src/nxt_conf_validation.c:3659-3667@872bf041). Node.js apps use `external`.
- `external` members: `executable`, `arguments`, plus the common members (src/nxt_conf_validation.c:943-951@872bf041).
- The child reads its start parameters from the `NXT_UNIT_INIT` environment variable (src/nxt_unit.c:1012-1023@872bf041), and `nxt_unit_init()` sends READY (src/nxt_unit.c:568, :698@872bf041). libunit installs as a static library only (auto/make:582-585@872bf041).
- `limits.start_timeout` exists, maps to milliseconds, and defaults to 0, which means no deadline (src/nxt_conf_validation.c:1387, src/nxt_router.c:3172, :888-891@872bf041; `NXT_APP_START_TIMEOUT` is 0 in src/nxt_router.h:144@872bf041). When set and missed, the router logs that "The process, if it is still running, never called nxt_unit_init()" (src/nxt_router.c:1052-1072@872bf041). CHANGED from the 2026-09-12 note: the option now exists in the validator; the "no default" part still holds.

### Inferences
- A daemon that never calls `nxt_unit_init()` is never ready. With the default no-deadline start, requests routed to it wait indefinitely. With a deadline, the start fails. Either way FreeUnit gives no restart, health check or log plumbing for it.

### Gaps
- What the router does with such a process after a failed start (is it killed or left running) was not traced.

## 8. Configuration lifecycle

### Takeaway
Configuration is one JSON document behind an API. Changes are validated before they apply, unchanged apps keep their processes, and the state is persisted. There is no reload on file change.

### Cited Findings
- The controller validates a new configuration with `nxt_conf_validate()` before sending it to the router (src/nxt_controller.c:437, :1728, :1828@872bf041) and answers "Invalid configuration." on failure (:1884). GET, POST, PUT and DELETE are handled for `/config` paths (src/nxt_controller.c:1607-1763@872bf041).
- An app whose JSON is byte-identical to the previous one is reused with its processes (src/nxt_router.c:3152-3154@872bf041). Any change to the app's JSON creates a new app and prototype.
- Apps are started at config apply when `nxt_router_app_need_start()` says so, whether or not a route reaches them (src/nxt_router.c:2449-2452, :2371-2377@872bf041). With `processes` omitted, spare is 1, so one worker starts at apply time.
- Since 1.37.0 the main process writes the state files in a short-lived child process, so a slow fsync no longer delays application starts after a reconfiguration (CHANGES:121).
- No watcher: the event engines can watch files (`enable_file` and `close_file` in src/nxt_event_engine.h:156-162@872bf041), but nothing outside the engine code calls them.
- Upstream asked for an auto-reload workflow in unitctl: [nginx/unit#1437](https://github.com/nginx/unit/issues/1437), open at archive time.

### Inferences
- A dev tool should own the source of truth (for example a per-project file), render the full config, and PUT it. Changing routes or listeners keeps PHP warm; changing anything inside an app restarts that app.

### Gaps
- Whether a PUT fails when a newly started PHP prototype fails (for example a missing `options.file`) was not tested. [nginx/unit#1546](https://github.com/nginx/unit/issues/1546) suggests it does when a worker starts at apply time.

## 9. Ecosystem and platforms

### Takeaway
Docker images and a Debian 13 package set are the only binary channels. macOS has no FreeUnit formula and its CI does not test modules; 1.37.0 is broken for applications on Apple Silicon until 1.37.1 is tagged. A Docker-free tool must plan to build or bundle FreeUnit itself on macOS and on Linux distributions other than Debian 13.

### Cited Findings
- unitctl subcommands: `instances` (list running unitd processes; `instances new` deploys a Docker container), `edit`, `import`, `execute`, `status`, `listeners`, `apps` (`list`, `restart`), `export`; global flags `--control-socket-address`, `--wait-timeout-seconds`, `--wait-max-tries` (tools/unitctl/unitctl/src/unitctl.rs:10-204@872bf041; Docker deploy in tools/unitctl/unitctl/src/cmd/instances.rs:7@872bf041). unitctl 1.37.0 needs Rust 1.95 (tools/unitctl/unitctl/Cargo.toml:4, :7@872bf041). It cannot start or stop a local unitd process. Since 1.37.0 it reads JSON only, not JSON5, hjson or YAML (CHANGES lines 608-622, 807).
- [freeunit-php-sdk](https://github.com/freeunitorg/freeunit-php-sdk): a PHP client for the control API, Apache-2.0, created and last pushed on 2026-05-31, 1 star. Its README says PHP 8.3 or later; its composer.json requires ^8.4. Its README says "This project in development. Don't use it in production." It supports FreeUnit 1.33.0 and later in 0.9.x.
- [homebrew-unit](https://github.com/freeunitorg/homebrew-unit): formulas `unit`, `unit-php`, `unit-python`, `unit-python3`, `unit-perl`, `unit-ruby`, `unit-java`, `unit-wasm` and `php-embed`. `unit.rb` and `unit-php.rb` build `https://github.com/nginx/unit.git` tag 1.34.2; `php-embed.rb` builds PHP 8.4.2. Last push 2025-10-08. WRONG in the briefing: the tap does not ship FreeUnit.
- packages.freeunit.org serves only contrib tarballs (njs, wasmtime, wasi-sysroot, libunit-wasm) and `SHA512SUMS` (fetched 2026-10-07). WRONG in the briefing: there is no apt or rpm repository there. The docs say "Official FreeUnit RPM/DEB packages are coming soon" and point to Docker and source builds ([installation.rst](https://github.com/freeunitorg/docs/blob/main/source/installation.rst)).
- The 1.37.0 release has these assets: `freeunit`, `freeunit-dev`, `freeunit-php8.3`, `freeunit-php8.4`, `freeunit-php8.5`, `freeunit-python3.13` (plus `-dbg`) for Debian 13 "trixie" amd64, and `SHA256SUMS` ([release](https://github.com/freeunitorg/freeunit/releases/tag/1.37.0)). The deb build uses the "freeunit" names: `/var/lib/freeunit`, `control.freeunit.sock`, `/var/log/freeunit.log` (pkg/deb/Makefile:16, :28-29, :163-168@872bf041), is built with `--njs` (pkg/deb/Makefile:177@872bf041), and only Debian 13 gets PHP 8.3, 8.4 and 8.5 modules (pkg/deb/Makefile:120-124@872bf041).
- Docker images: `ghcr.io/freeunitorg/freeunit:latest-php-8.5`, `-8.4`, `-8.3`, also on Docker Hub, for linux/amd64 and linux/arm64 (README.md:48-58@872bf041; .github/workflows/release-docker.yml:99-102, :140-142@872bf041). Version tags follow `<version>-<variant>`.
- macOS: CI builds and runs only the C test programs on arm64 macOS 15: "No modules, no pytest, no sudo" (.github/workflows/build-test-macos.yml:25-30@872bf041). 1.37.1 (untagged) fixes "on macOS with 16 KiB pages, every request to an application failed" (CHANGES:4-12; [freeunit#595](https://github.com/freeunitorg/freeunit/issues/595)). The same CI comment says arm64 macOS has 16 KiB pages.
- FreeBSD: the `www/unit` port, which built NGINX Unit, was deleted on 2025-12-31 after upstream was archived; its last version was 1.35.0_1, and `www/unit-php` was deleted with it ([FreshPorts](https://www.freshports.org/www/unit/)).
- Windows: not supported. The event engines in the tree are kqueue, epoll, eventport, /dev/poll and poll (src/nxt_service.c:12-32@872bf041).

### Inferences
- For macOS the tool must build unitd and the PHP module from source against the developer's PHP (for example Homebrew `php`, which needs the embed SAPI), or ship its own build. It must require 1.37.1 or later.
- On Linux other than Debian 13 the same applies. The Debian packages are a reference for paths and module names.

### Gaps
- Whether Homebrew's own `php` formula ships `libphp` (the embed SAPI) was not checked; the tap's separate `php-embed` formula suggests it does not.

## 10. Performance context for honest positioning

### Takeaway
The measured evidence supports selling simplicity and parity, not speed. For a Drupal page the server is a small fraction of the cost. Public numbers are few.

### Cited Findings
- [freeunit#596](https://github.com/freeunitorg/freeunit/issues/596) cites the public Yii3 benchmark of 2026-10-05 ([yii3-benchmarks](https://yii3-benchmarks.github.io/app-api/)): FreeUnit 1.37.0 at 11,445 requests per second on `/` with 20 PHP workers; about 85 percent of its 1.78 ms is framework bootstrap, and router plus IPC is 5 to 6 percent.
- Measured for Drupal on 2026-09-12: for a one-shot cron, opcache on without `file_cache` was 1.6 times slower than opcache off ([finding](https://github.com/andypost/freeunit-drupal/blob/master/docs/findings/opcache-shm-lives-in-prototype.md)).
- Not measured, per [needs-measurement.md](https://github.com/andypost/freeunit-drupal/blob/master/docs/findings/needs-measurement.md): MySQL or MariaDB behaviour (all runs used SQLite), non-trivial sites (memory per worker, opcache sizing, bootstrap cost), more than 2 cores, `limits.start_timeout` in practice, and whether `spare: 1` holds under sustained load.

### Inferences
- The honest claims for a dev tool are: one process tree with one JSON config instead of nginx plus php-fpm plus their configs; the same server in development and production; per-project PHP versions and ini without containers; and HTTPS, status and logs from one API.
- Speed claims should be limited to static files and many keep-alive connections, and only after a published, repeatable harness.

### Gaps
- Unpublished 2026-10 comparisons against Apache with mod_php reported a much lower server CPU cost per request for static files and small PHP responses, and no difference in wall time for Drupal's test suite. They have no public artifact, so they cannot be cited yet.

## 11. Server changes a dev tool would want, ordered by value for Drupal developers

### Takeaway
The most valuable items are a release (1.37.1 for Apple Silicon), two small logging and ini changes, and macOS packaging and CI. Conditional GET, ranges and the Last-Modified fix are done. Do not change `PHP_SAPI`.

### Cited Findings
| # | Change | Files to touch | Size estimate | Bug or feature | Why it matters for Drupal developers |
|---|---|---|---|---|---|
| 1 | Tag 1.37.1 | none (already on master, CHANGES:2-52) | release only | Bug fix | On Apple Silicon, 1.37.0 fails every application request (CHANGES:4-12). |
| 2 | Honour per-app `stdout`/`stderr` in `--no-daemon` mode | src/nxt_application.c:1586-1635 (the gate is :1601-1603) | 3-10 lines plus a pytest of about 40 lines; decide whether PHP startup messages from the prototype belong in the same files | Feature (behaviour change; documented as daemon-only in [nginx/unit#915](https://github.com/nginx/unit/issues/915)) | Per-project PHP error files while the tool runs unitd in the foreground. |
| 3 | Apply `options.admin` before PHP startup (as SAPI INI entries), so `zend_extension`, `opcache.jit` and `xdebug.mode` work | src/nxt_php_sapi.c:393-470 (startup at :445, `ini_entries` at :355) | 60-120 lines plus tests | Feature | Xdebug and JIT per project without writing ini files; closes [nginx/unit#785](https://github.com/nginx/unit/issues/785) and the use case of [nginx/unit#969](https://github.com/nginx/unit/issues/969). |
| 4 | Test the PHP module on macOS in CI | .github/workflows/build-test-macos.yml (now "No modules, no pytest") | 40-100 lines of workflow, unknown test fixes | CI | The 1.37.0 Apple Silicon breakage reached a release. |
| 5 | FreeUnit formulas in the Homebrew tap | freeunitorg/homebrew-unit Formula/*.rb | 150-250 lines of Ruby | Packaging | A Docker-free macOS install path. |
| 6 | A reason in server error pages, opt-in | src/nxt_http_error.c:19-22, validator (src/nxt_conf_validation.c:390-451), router socket conf | 150-300 lines plus tests | Feature; precedents [nginx/unit#489](https://github.com/nginx/unit/issues/489), [nginx/unit#1347](https://github.com/nginx/unit/issues/1347) | "Error 503." says nothing about a failed start, a timeout or a missing app. |
| 7 | Merge "schedules" | [PR 452](https://github.com/freeunitorg/freeunit/pull/452) | already written | Feature (milestone 1.37.1; the PR text says 1.38.0) | Drupal cron without an external timer. |
| 8 | Per-listener or per-route access log | src/nxt_router_access_log.c, src/nxt_conf_validation.c, src/nxt_router.c | 200-400 lines plus tests | Feature | One log per project in a shared instance. The `$host` workaround exists. |
| 9 | Add `.wasm`, `.map`, `.webmanifest` (and maybe `.csv`) to the MIME table | src/nxt_http_static.c:2054-2125 | 3-6 lines plus a test of about 20 lines | Bug fix | `.wasm` without `application/wasm` breaks streaming instantiation. |
| 10 | CHANGES entry for the Last-Modified GMT fix (commit fd2d1501, shipped in 1.37.0 without an entry) | CHANGES, docs/changes.xml | 6-10 lines | Docs | Tells 1.36.x users their Last-Modified was offset by the server's time zone. |
| 11 | Re-test, then guard the pcntl_fork exit re-entry | src/nxt_php_sapi.c around :1363-1384 | 10-30 lines plus a test | Bug fix | Rare in Drupal; matters only for code that forks in a request. |
| 12 | `--log -` or stderr by default in foreground mode | src/nxt_runtime.c:1262-1269, :1497-1517 | 10-20 lines | Feature | Convenience only; `--log /dev/stderr` already works. |
| 13 | Keep `PHP_SAPI` as "cli-server" and document it | docs only | about 10 lines of docs | Docs | Renaming would disable opcache on PHP 8.4 and older ([php-src 3088d640684](https://github.com/php/php-src/commit/3088d640684)). |

Done already, so not on the list: conditional GET and byte ranges (CHANGES:588, :623), the Last-Modified code fix (src/nxt_http_static.c:718@872bf041), the terminate-phase capacity fix (CHANGES:478), and the `limits.timeout` deadline while a request waits for a worker (CHANGES:490, src/nxt_router.c:8104-8108@872bf041).

### Inferences
- Items 1, 2 and 3 together remove most friction for a macOS or Linux developer: a working server, readable per-project logs, and per-project PHP extensions from config alone.
- HTTP/2 is deliberately absent from the list. It is large and gives a dev tool little.

### Gaps
- Size estimates are judgement from the code shape, not from a prototype.

## 12. What the tool must do outside the server

### Takeaway
FreeUnit is the web and PHP layer only. Everything else in a Drupal stack is the tool's job, and some of it must match FreeUnit's PHP exactly.

### Cited Findings
- PHP CLI for Drush and Composer: Drush requires the "cli" SAPI (Drush `Environment::verifyCLI()`, see section 2). The CLI must be the same PHP version and extension set as the FreeUnit module, and its path should be passed to apps as `PATH` or `PHP_PATH` (section 2, `PHP_BINARY` is empty under FreeUnit).
- Database and other daemons (MariaDB or PostgreSQL, Redis, Solr, Mailpit): `external` apps must speak libunit (section 7).
- Local CA and certificates: generate a CA, install it in the OS and browser trust stores, issue leaf certificates for project names, PUT them to `/certificates` (section 5). ACME cannot help for `.localhost`.
- Name resolution: RFC 6761 section 6.3 says names under `localhost.` should resolve to loopback ([RFC 6761](https://www.rfc-editor.org/rfc/rfc6761#section-6.3)), and browsers do this, but command-line tools and PHP's own resolver depend on the OS. Drupal also needs `trusted_host_patterns` for those names (section 2).
- Ports: choose high ports, or set `net.ipv4.ip_unprivileged_port_start` or `CAP_NET_BIND_SERVICE` on Linux; on macOS bind the wildcard address or use high ports (section 6).
- Supervision of unitd itself: start, stop, restart after a crash, and wait for the control socket (section 6).
- File watching and reload: watch the project config, `.env`, php.ini fragments and `settings.php`, then PUT the config or call the restart endpoint (section 8).
- Cron and queues: run `drush cron` and `drush queue:run` on a timer, and turn off `automated_cron` or accept that it runs in the terminate phase (section 2), until schedules land.
- Logs: collect the unit log and the access log, split by `$host` if needed (section 6).
- Installing FreeUnit and PHP builds with the embed SAPI on macOS and on Linux outside Debian 13 (section 9).
- Xdebug installation, the twin app and the IDE listener settings (section 2).
- Mail capture: point PHP's `sendmail_path` or Drupal's mail system at a capture tool.

### Inferences
- The tool's PHP story is the hardest part: the CLI PHP, the embed library behind the FreeUnit module, and the extension set must stay in step per project.

### Gaps
- None beyond those listed in the other sections.

## 13. Open questions and the experiment that answers each

### Takeaway
Ten questions decide the tool's defaults. Each has a short experiment on a 1.37.1 build (or 1.37.0 on Linux).

### Cited Findings
1. Does `options.admin` `xdebug.mode` work? Load Xdebug with `xdebug.mode=off` through `PHP_INI_SCAN_DIR`, set `"options": {"admin": {"xdebug.mode": "debug"}}`, run `nc -l 9003`, request a script with `XDEBUG_SESSION=1`. Expect `ini_get('xdebug.mode')` to print "debug" while `xdebug_info('mode')` stays empty and no connection arrives. Basis: Xdebug reads the mode in MINIT (xdebug.c:508 at 64007df3), FreeUnit applies admin after startup (src/nxt_php_sapi.c:445-455@872bf041).
2. Can a non-root unitd on macOS bind `127.0.0.1:443`? As a normal user on macOS 14 or 15, PUT a listener on `127.0.0.1:443`, then on `*:443`. Expect the first to fail with a bind error from the main process and the second to work. Basis: [Apple forum thread 674179](https://developer.apple.com/forums/thread/674179).
3. Does BigPipe stream? Install Drupal standard with `big_pipe`, request a page as a logged-in user with `curl -N --raw`, and timestamp each chunk. Compare 1.36.1 and 1.37.x. Basis: 1.37.0 fixed `flush()` not sending headers and the late end of a chunked header (CHANGES:95, :103).
4. Does a PUT fail when PHP cannot start? PUT an app with `options.file` pointing to a missing file, once with `processes` omitted (spare 1) and once with `{"spare": 0}`. Record the HTTP answer and the log. Upstream [nginx/unit#1546](https://github.com/nginx/unit/issues/1546) shows "failed to start prototype" together with "Failed to apply new configuration", which suggests the PUT fails when a worker must start at apply time.
5. Do two PHP versions load in one instance? Put `php8.4.unit.so` and `php8.5.unit.so` in one modules dir, start unitd, check `GET /status` modules, and serve two apps at once with `phpversion()` output. Watch the discovery process for symbol clashes (modules are opened with `RTLD_GLOBAL`, src/nxt_application.c:413@872bf041).
6. How many times is an upload written? Raise `max_body_size` and PHP limits, upload a 100 MB file to a Drupal file field, and trace writes with `strace -f -e trace=openat,write` on the router and the worker. Expect one write to the router's temp file and one to PHP's upload temp dir.
7. Does a routes-only PUT keep opcache warm? Read `opcache_get_status()['opcache_statistics']['start_time']` before and after a PUT that changes only a route. Expect no change. Then change one app member and expect a new start time.
8. Do browsers accept the certificates? Serve `https://<project>.localhost` and `https://a.<project>.localhost` with a mkcert-style CA through FreeUnit, in Chrome, Firefox and Safari; also try a single `*.localhost` leaf.
9. Is the pcntl_fork exit trap still there? In a PHP request, `pcntl_fork()`, then `exit()` in the child; count `unit: "<app>" application` processes and their parent pids before and after.
10. What does a real Drupal site cost in memory? Install a content-rich Drupal (for example the Umami demo with a few contrib modules), set `processes` to 4, and record the RSS and PSS of the prototype and each worker, and opcache memory, after a crawl of 100 pages. This also closes the "non-trivial sites" item in [needs-measurement.md](https://github.com/andypost/freeunit-drupal/blob/master/docs/findings/needs-measurement.md).

### Inferences
- Questions 1, 2 and 4 decide the tool's default config shape and should run first.

### Gaps
- All ten are unrun. Results should be recorded with the FreeUnit version, PHP version and OS.
