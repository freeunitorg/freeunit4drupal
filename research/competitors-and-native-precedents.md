# Competitors and native precedents for a Docker-free local Drupal environment

State on 2026-10-07. These notes support the freeunit4drupal plan: a Docker-free local Drupal environment built on FreeUnit, a community fork of NGINX Unit that embeds PHP in its own application processes.

How to read these notes:

- Every finding carries a full URL and a date. "Page read 2026-10-07" means the page shows no date. "(historical)" marks facts from before 2025 that may have changed.
- GitHub numbers (stars, release dates, download counts, issue counts) come from the GitHub API, read on 2026-10-07. Release download counts include update checks, CI and repeat downloads, so they are an upper bound on people.
- Homebrew numbers are 365-day install counts from formulae.brew.sh, read on 2026-10-07. They only count installs through Homebrew by users who did not opt out of analytics. Tools that ship mainly as a DMG or installer (Herd, MAMP) are undercounted.
- Reddit could not be read directly (login wall and HTTP 403). Reddit opinions appear only as search snippets and are marked. Hacker News has almost no discussion of these tools.
- Labels: "Inference" is my reasoning, not a sourced fact. "Not verified" means I looked and found no primary source.

## 1. How each tool solves the hard problems, and which problems killed tools

### Takeaway

The tools that thrive own the whole stack behind one installer and hide it: Laravel Herd bundles nginx, its own PHP builds, DNS and a local CA, and is now the default install path in Laravel's docs. In Drupal the winner is the opposite pattern, one community-standard container recipe (DDEV). Tools died when a hosting vendor changed strategy (Acquia Dev Desktop in 2021, Bitnami installers in 2022, Pantheon Localdev in 2023) or when one maintainer ran out of time (Drupal VM, Docksal stalled, Valet for Windows faded). The hardest problems are Windows host names, antivirus and code signing, keeping PHP builds current, and supervising PHP processes.

### Cited Findings

#### Corrections to the briefing

- Valet for Windows is not archived. cretueusebiu/valet-windows had release 2.5.3 on 2025-03-01 and a commit on 2026-02-09. Source: [cretueusebiu/valet-windows](https://github.com/cretueusebiu/valet-windows), GitHub API 2026-10-07.
- FrankenPHP now runs natively on Windows. Pull request "feat: Windows support" merged on 2026-02-26, and v1.12.0 (2026-03-06) was the first release with a Windows archive. Static and embedded-app builds are still not available on Windows. Sources: [php/frankenphp #2119](https://github.com/php/frankenphp/pull/2119), 2026-02-26; [FrankenPHP releases](https://github.com/php/frankenphp/releases), GitHub API 2026-10-07; [#2501](https://github.com/php/frankenphp/pull/2501), 2026-07-03.
- php-cgi on Windows does support `PHP_FCGI_CHILDREN`. The Windows branch of php-cgi spawns up to 64 child processes with `CreateProcessW`, puts them in a job object and respawns them in a `while (parent)` loop. This contradicts a widely copied claim that php-cgi.exe has no concurrency and needs manual restarts. Sources: [php-src sapi/cgi/cgi_main.c at d854a54b5f](https://github.com/php/php-src/blob/d854a54b5f52c2ca43228184e2a468b19f2163d2/sapi/cgi/cgi_main.c), 2024-09-26 (`#define WIN32_MAX_SPAWN_CHILDREN 64`); contradicted claim: [nginx wiki mirror, windows php](https://nginx-wiki.getpagespeed.com/config/examples/windows-php/) (search snippet only).
- Herd for Windows has no documented "FPM-like" manager. User bug logs show the Herd Electron process spawning one `php-cgi.exe` per PHP version on a local port. Source: [herd-community #1675](https://github.com/beyondcode/herd-community/issues/1675), 2026-04-04.
- Acquia replaced Dev Desktop with its own Acquia Cloud IDE first, and offered Lando plus Acquia CLI for local work. DDEV was not named. Source: [Announcing Acquia Dev Desktop End of Life (archived)](https://web.archive.org/web/20220129114516/https://dev.acquia.com/blog/announcing-acquia-dev-desktop-end-life), 2021-02-24 (historical).
- Pantheon Localdev ended later than 2021-2022: Pantheon announced on 2023-02-01 that support ends in June 2023. Source: [Pantheon release note: Localdev](https://docs.pantheon.io/release-notes/2023/02/localdev), 2023-02-01.
- Bitnami native installers ended on 2022-11-30, not in 2021. Source: [Bitnami blog: Focusing on cloud native solutions](https://blog.bitnami.com/2022/11/focusing-on-cloud-native-solutions.html), 2022-11-09.
- The DDEV decision is worded as a community decision, not a Drupal Association one: "The Drupal community selected DDEV as the recommended environment for local site building as well as code development in June 2024." Source: [drupal.org: Install Drupal using DDEV](https://www.drupal.org/node/1513732), updated 2026-07-13.
- Gitpod Classic pay-as-you-go ended on 2025-10-15, not in April 2025. Source: [gitpod-io/gitpod README](https://github.com/gitpod-io/gitpod), page read 2026-10-07.
- Acquia Cloud IDE is not on Acquia's end-of-life schedule. No evidence that it was discontinued. Source: [Acquia software end-of-life schedule](https://docs.acquia.com/service-offerings/software-end-life-schedule), page read 2026-10-07.
- static-php-cli documents only the cli and micro SAPIs for Windows. Its Windows builder code also deploys a cgi binary. Embed on Windows is not documented. Sources: [build-on-windows.md](https://github.com/crazywhalecc/static-php-cli/blob/main/docs/en/guide/build-on-windows.md) and [WindowsBuilder.php](https://github.com/crazywhalecc/static-php-cli/blob/main/src/SPC/builder/windows/WindowsBuilder.php), main branch at 956fa13e7c, 2026-08-22.

#### Laravel Herd for macOS

- What it is: a native macOS app announced on 2023-07-20 by Marcel Pociot of Beyond Code, built "in partnership with Laravel", free to download. It bundled "PHP (versions 7.4 up to 8.3 Alpha), nginx and dnsmasq". Source: [Announcing Laravel Herd (archived)](https://web.archive.org/web/20231225135644/https://beyondco.de/blog/announcing-laravel-herd), 2023-07-20 (historical; the original URL now returns 404).
- PHP builds at launch: "Herd solves this problem by shipping PHP (both CLI and FPM) as statically compiled binaries." "Herd internally makes use of the static-cli-builder project, maintained by crazywhalecc." Vendor claim: "up to 100% faster than traditional PHP versions installed via Homebrew." The stated motive: Homebrew "adds a lot of overhead" and "a simple brew update command can break your system." Source: same post, 2023-07-20 (historical).
- Valet inside: "As Herd internally makes use of Valet itself". Site nginx configs live in `config/valet/Nginx`, and the log folder holds "nginx and php-fpm" logs. Sources: same post; [Herd docs: Directories and Files (macOS)](https://herd.laravel.com/docs/macos/advanced-usage/directories-and-files), page read 2026-10-07.
- Bundle and supervision: "It ships with PHP, nginx, dnsmasq and Node.js." A background service "needs admin permissions and is responsible for handling nginx and dnsmasq." Requires macOS 12.0 or newer. Source: [Herd docs: Installation (macOS)](https://herd.laravel.com/docs/macos/getting-started/installation), page read 2026-10-07.
- PHP versions side by side: default PHP 8.5. `herd use 8.2` sets the global version, `herd isolate` pins one site, `herd php:install 8.3` adds a version, and `herd php`, `composer`, `which-php` proxy to the site's version. Source: [Herd docs: Manage PHP (macOS)](https://herd.laravel.com/docs/macos/technology/php-versions), page read 2026-10-07.
- TLS: the HTTPS toggle or `herd secure` creates "a local TLS certificate"; macOS may ask to "confirm one or more permission related popups". The trust mechanism is not documented. Source: [Herd docs: Securing Sites (macOS)](https://herd.laravel.com/docs/macos/sites/securing-sites), page read 2026-10-07.
- Recent changes: 1.30.1 (2026-09-16) fixed PHP-FPM crashes on a new macOS release; 1.29.0 (2026-06-30) added X509v3 extensions so certificates pass OpenSSL strict mode; 1.25.0 (2026-01-19) accepts custom `.pem` certificates; 1.4.0 (2024-02-05) "Added optional Xdebug extension". Source: [Herd changelog (macOS)](https://herd.laravel.com/docs/macos/changelog), page read 2026-10-07.
- Drupal: Drupal is in Herd's list of supported CMSs. An early user report: "The provided host name is not valid for this server (Drupal site)". Sources: [Herd docs: Supported Frameworks](https://herd.laravel.com/docs/macos/extending-herd/supported-frameworks), page read 2026-10-07; [herd-community #78](https://github.com/beyondcode/herd-community/issues/78), 2023-07-31.
- Linux: "There are currently no plans for a Linux version". Source: [herd.laravel.com](https://herd.laravel.com/), page read 2026-10-07.

#### Laravel Herd for Windows

- Release: 1.0.0 on 2024-03-26; latest 1.30.0 on 2026-08-19. Source: [Herd changelog (Windows)](https://herd.laravel.com/docs/windows/changelog), page read 2026-10-07.
- Bundle and admin rights: installs PHP, nginx and Node.js, plus composer, the Laravel installer and Expose, and "the HerdHelper service that is responsible for updating your hosts file and map directories and linked sites to `.test` domains". The installer must run "with admin permissions so that the installer can add the HerdHelper service". Windows 10 or newer. Source: [Herd docs: Installation (Windows)](https://herd.laravel.com/docs/windows/getting-started/installation), page read 2026-10-07.
- Host names: HerdHelper is a Windows service (managed with `sc.exe`). The hosts file can be "locked by anti virus software or is simply read-only". Since 1.19.0 (2025-04-02) users can write the hosts entries by hand when the helper is not running. Sources: [Herd docs: HerdHelper](https://herd.laravel.com/docs/windows/troubleshooting/herdhelper), page read 2026-10-07; Windows changelog.
- TLS: since 1.19.0 (2025-04-02) "Newly created root certificate authorities are valid for 20 years instead of one", and Herd detects an expired root. Where the root is trusted is not documented. Source: Windows changelog.
- PHP process model (from user logs, not docs): "Starting PHP 84 on port 9084", one `php-cgi.exe` per PHP version, spawned by "the Herd Electron process"; nginx forwards to it and returns 504 when php-cgi dies; Herd loads its own `php_herd-X.X.dll` extension. Sources: [herd-community #1675](https://github.com/beyondcode/herd-community/issues/1675), 2026-04-04; [#1168](https://github.com/beyondcode/herd-community/issues/1168) ("spawn ...\php83\php-cgi.exe ENOENT"), 2024-11-29.
- Control plane: the Electron app runs an internal HTTP server on port 9005 that CLI commands use; HerdHelper listens on port 5001. Source: [herd-community #1578](https://github.com/beyondcode/herd-community/issues/1578), 2025-11-15.
- Windows friction: Defender scanning "decreases your Herd performance" and since 1.21.0 (2025-07-14) fresh installs exclude the Herd path from Defender "to improve PHP performance"; Windows 11 Smart App Control blocks Herd's PHP extension DLLs ("An Application Control policy has blocked this file"); Herd fails in domain-controlled corporate setups; nginx fails when IIS or Apache holds port 80 or 443. Sources: installation docs; Windows changelog; [#1757](https://github.com/beyondcode/herd-community/issues/1757), 2026-09-28; [#601](https://github.com/beyondcode/herd-community/issues/601), 2024-04-03 (still open); [Herd docs: Nginx troubleshooting](https://herd.laravel.com/docs/windows/troubleshooting/nginx), page read 2026-10-07.
- Issue volume: 1,294 issues in the public tracker, 79 with "windows" in the title, 54 open. Source: [beyondcode/herd-community issues](https://github.com/beyondcode/herd-community/issues), GitHub search API 2026-10-07.
- Migration guides target the tools Herd replaces: from Sail, XAMPP and Laragon on Windows, from Sail and MAMP on macOS. Source: [Herd docs index](https://herd.laravel.com/docs/llms.txt), page read 2026-10-07.

#### Herd Pro and Herd Lite (php.new)

- Prices: Herd is free. Herd Pro costs EUR 99 per year for one licence. Herd Teams costs EUR 299 per year for 10 Pro licences. 14-day money-back guarantee. Pro-only features: Dumps, Mail, Log Viewer, Services, Xdebug detection. Source: [herd.laravel.com](https://herd.laravel.com/), page read 2026-10-07.
- Herd Pro arrived with Herd 1.4.0: dump interception, a local mail server, log tracking, automatic Xdebug. Source: [Laravel News: Herd Pro](https://laravel-news.com/herd-pro), 2024-02-07.
- Services (Pro): MySQL, MariaDB, PostgreSQL, MongoDB, Redis, Valkey, Laravel Reverb, Typesense, Meilisearch, MinIO, RustFS. Source: [Herd docs index](https://herd.laravel.com/docs/llms.txt), page read 2026-10-07.
- Mail (Pro): SMTP on 127.0.0.1 port 2525; messages are saved "to an internal database" and grouped "by the username of the mail sender" (for example `APP_NAME`). Source: [Herd docs: Mail (Windows)](https://herd.laravel.com/docs/windows/herd-pro-services/mail), page read 2026-10-07.
- Xdebug (Pro): "Herd Pro is able to detect Xdebug headers in your requests and it enables the extension on demand for these requests so that all other requests bypass the extension and are super-fast." Such requests go to "a PHP process with an enabled Xdebug extensions". CLI: `herd debug`. Source: [Herd docs: Xdebug Detection (Windows)](https://herd.laravel.com/docs/windows/debugging/xdebug-detection), page read 2026-10-07.
- Herd Lite: the php.new scripts download one PHP 8.5 executable from `download.herdphp.com/herd-lite/...` (Linux x64 and arm64, Windows), plus Composer and the Laravel installer, into `~/.config/herd-lite/bin`. The site says "Made by your friends at BeyondCode and Laravel". Sources: [php.new Linux script](https://php.new/install/linux) and [Windows script](https://php.new/install/windows), read 2026-10-07; [php.new](https://php.new/), page read 2026-10-07.

#### Laravel Valet and its forks

- Valet (macOS): "Laravel Valet configures your Mac to always run Nginx in the background when your machine starts. Then, using DnsMasq, Valet proxies all requests on the `*.test` domain". "Roughly 7 MB of RAM". Requires macOS and Homebrew. Built-in drivers include Drupal. Per-site PHP with `valet isolate php@8.0`. Databases: the docs point to DBngin. PHP-FPM listens on `~/.config/valet/valet.sock` with the pool file `valet-fpm.conf`. The page now opens with "Check out Laravel Herd". Source: [Laravel 12.x docs: Valet](https://laravel.com/docs/12.x/valet), page read 2026-10-07.
- Valet TLS: Valet creates its own CA ("Laravel Valet CA Self Signed CN", file `LaravelValetCASelfSigned.pem`), trusts it on the Mac, and signs site certificates; the code notes that site certificates "SHOULD NOT have a validity period greater than 397 days". Source: [laravel/valet cli/Valet/Site.php](https://github.com/laravel/valet/blob/master/cli/Valet/Site.php), read 2026-10-07.
- Valet status: v4.12.0 (2026-03-10), last commit 2026-09-22, 2,604 stars, MIT. Source: [laravel/valet](https://github.com/laravel/valet), GitHub API 2026-10-07.
- Valet Linux: nginx plus DnsMasq like the original; v2.4.5 (2026-07-16), 1,466 stars. Valet Linux Plus: 2.0.3 (2025-03-10), 188 stars. Sources: [cpriego/valet-linux](https://github.com/cpriego/valet-linux); [valet-linux-plus](https://github.com/valet-linux-plus/valet-linux-plus), GitHub API 2026-10-07.
- Valet for Windows: runs nginx in the background and uses Acrylic DNS Proxy for `*.test`; install needs an administrator terminal; on Windows 10 and 11 "you may need to manually configure Windows to use the Acrylic DNS proxy". Most-discussed issues: "Could not install the nginxservice service" ([issue 13](https://github.com/cretueusebiu/valet-windows/issues/13), 2017-03-02), "Slow performance - TTFB up to 60" ([issue 106](https://github.com/cretueusebiu/valet-windows/issues/106), 2018-12-18), "Windows 10 reports as offline (is online)" ([issue 125](https://github.com/cretueusebiu/valet-windows/issues/125), 2019-10-01), "Valet has problems with executing commands from another drive" ([issue 12](https://github.com/cretueusebiu/valet-windows/issues/12), 2017-02-28) (historical). The yCodeTech fork is at v3.4.5 (2026-07-05) with 45 stars. Sources: [cretueusebiu/valet-windows README](https://github.com/cretueusebiu/valet-windows) and [its issues](https://github.com/cretueusebiu/valet-windows/issues), read 2026-10-07; [yCodeTech/valet-windows](https://github.com/yCodeTech/valet-windows).

#### Laravel Sail and Laravel Homestead (comparators)

- Sail (Docker Compose wrapper) is maintained: v1.68.0 on 2026-09-22, 1,921 stars. Source: [laravel/sail](https://github.com/laravel/sail), GitHub API 2026-10-07.
- Homestead (Vagrant box) is archived; last push 2024-11-06. Source: [laravel/homestead](https://github.com/laravel/homestead), GitHub API 2026-10-07.

#### Symfony CLI local web server

- What it is: one Go program, AGPL-3.0, first open-source release v5.0.0 on 2021-12-30, latest v5.22.0 on 2026-10-06, 608 stars. Source: [symfony-cli/symfony-cli](https://github.com/symfony-cli/symfony-cli), GitHub API 2026-10-07.
- How PHP runs: "Server represents a PHP server process (can be php-fpm, php-cgi, or php-cli)". With FPM it writes `fpm-<version>.ini` and runs `php-fpm --nodaemonize --fpm-config`. With CGI it runs `php-cgi -b <port>` from a temp directory. Otherwise it runs `php -S 127.0.0.1:<port>` with a generated router. Source: [local/php/php_server.go](https://github.com/symfony-cli/symfony-cli/blob/main/local/php/php_server.go), last changed 2026-10-05.
- The FPM pool it writes: `pm = dynamic`, `pm.max_children = 30`, `pm.start_servers = 2`, listen on 127.0.0.1 only, `clear_env = no`. Source: [local/php/fpm.go](https://github.com/symfony-cli/symfony-cli/blob/main/local/php/fpm.go), read 2026-10-07.
- The Go front server talks FastCGI itself through its own `fcgi_client` package. Source: [local/php/cgi.go](https://github.com/symfony-cli/symfony-cli/blob/main/local/php/cgi.go), read 2026-10-07.
- The repository never sets `PHP_FCGI_CHILDREN` (GitHub code search: 0 hits, 2026-10-07).
- PHP versions: a `.php-version` file in the project or a parent directory picks the version; `symfony local:php:list` lists installed versions. Source: [Symfony docs: Symfony Local Web Server](https://symfony.com/doc/current/setup/symfony_server.html), page read 2026-10-07.
- TLS: `symfony server:ca:install` runs once, then the server uses HTTPS. The CA lives in the user's Symfony CLI config folder on Linux, macOS and Windows. Its library is "Fork of https://github.com/FiloSottile/mkcert (up-to-date as of Mar 22 2023 ...) to be able to use it as a library", with trust-store code for macOS, Linux, FreeBSD, Windows and NSS (Firefox). Sources: Symfony docs; [symfony-cli/cert](https://github.com/symfony-cli/cert), read 2026-10-07.
- Host names without DNS: the user sets the system proxy auto-configuration to `http://127.0.0.1:7080/proxy.pac`, runs `symfony proxy:start` and `symfony proxy:domain:attach my-app`, and gets `https://my-app.wip`. Command-line tools need `https_proxy=$(symfony proxy:url)`. The proxy serves `/proxy.pac` with a `FindProxyForURL` function that returns the proxy only for the configured TLD. Sources: Symfony docs; [local/proxy/proxy.go](https://github.com/symfony-cli/symfony-cli/blob/main/local/proxy/proxy.go), read 2026-10-07.
- Services: when Docker Compose runs, it injects connection variables into PHP: `DATABASE_URL`, `DATABASE_HOST`, `DATABASE_PORT` (MySQL 3306, PostgreSQL 5432), `REDIS_*`, `MEMCACHED_*`, and others for RabbitMQ, Elasticsearch, MongoDB and Kafka. Mail catchers on ports 1025/1080, 1025/8025, 25/80 or 3535/3550 produce `MAILER_*`; `symfony open:local:webmail` opens the UI. Source: Symfony docs.
- Supervision: `.symfony.local.yaml` declares `workers` (asset watchers, `messenger:consume` with `watch` folders, `docker_compose`). Source: Symfony docs.
- Windows: native, using php-cgi. Open issues include "Symfony cli doesnt see other php versions on windows" ([issue 511](https://github.com/symfony-cli/symfony-cli/issues/511), 2024-08-03) and "Automatically import the CA in the windows store when running the symfony-cli in WSL" ([issue 461](https://github.com/symfony-cli/symfony-cli/issues/461), 2024-04-05). Source: [symfony-cli issues](https://github.com/symfony-cli/symfony-cli/issues), read 2026-10-07.
- Adoption: 9,192,512 release asset downloads across all releases (includes CI); Homebrew formula `symfony-cli` 1,961 installs in 365 days. Sources: GitHub API 2026-10-07; [formulae.brew.sh: symfony-cli](https://formulae.brew.sh/formula/symfony-cli), read 2026-10-07.

#### PHP built-in web server (the baseline several tools fall back to)

- "It is not intended to be a full-featured web server. It should not be used on a public network." "The web server runs only one single-threaded process, so PHP applications will stall if a request is blocked." Since PHP 7.4.0, `PHP_CLI_SERVER_WORKERS` forks workers. "Multiple workers are not supported on Windows." Source: [PHP manual: Built-in web server](https://www.php.net/manual/en/features.commandline.webserver.php), page read 2026-10-07.

#### Drupal CMS Launcher

- What it is: "A simple, standalone app to run Drupal CMS locally with zero setup", with "no fiddly setup process, no Docker, no external dependencies". Repository drupal/cms-launcher, MIT, created 2025-02-04, 46 stars, 83 issues (15 open). Maintained "by the Drupal Association and community contributors". Source: [drupal/cms-launcher README](https://github.com/drupal/cms-launcher), read 2026-10-07.
- Announcement: shown by Dries Buytaert at DrupalCon Atlanta 2025 (March 2025) and described by Gábor Hojtsy. Source: [The Drop Times: Drupal CMS Desktop App Announced at DrupalCon Atlanta 2025](https://www.thedroptimes.com/47573/drupal-cms-desktop-app-announced-at-drupalcon-atlanta-2025), page read 2026-10-07.
- Mechanism: "We use static-php-cli to compile a copy of PHP that includes everything needed to run Drupal CMS. We bundle that with the Composer dependency manager ... The app itself is a very simple wrapper around PHP and Composer, written in TypeScript using the Electron framework." Electron ^40.6.1 with electron-builder and electron-updater. Sources: README; [package.json](https://github.com/drupal/cms-launcher/blob/main/package.json), read 2026-10-07.
- Install and serve: it runs `composer create-project --no-install drupal/cms` and `drupal:recipe-unpack`, then picks a free port from 8888 to 9999 and runs `php -d max_execution_time=300 -S localhost:<port> .ht.router.php`. The code sets no `PHP_CLI_SERVER_WORKERS`. Source: [src/main/Drupal.ts](https://github.com/drupal/cms-launcher/blob/main/src/main/Drupal.ts), last changed 2026-02-18.
- Documented limits: no deploy to hosting "(yet)"; "Only supports one Drupal CMS site at a time"; "Only supports SQLite databases"; the PHP built-in server "does not support HTTPS"; "Might not be able to send email"; PHP 8.4 only; it "does not manage the Drupal CMS codebase after initial setup". Audience: "site builders, themers, and evaluators", "not intended for contributing". Source: README.
- Alternatives the Launcher itself recommends: DDEV ("Drupal's Docker-based local development platform of choice"), Lando, Docksal, "If you'd rather avoid Docker, Laravel Herd is a fine choice", MAMP, XAMPP, Valet. Source: README.
- Windows: the README has a section on Windows Defender Firewall blocking the app's local web server. Source: README.
- Releases: first 0.1.2 on 2025-02-06; 1.0.0 on 2025-10-01; latest v1.0.9 on 2026-03-23; no release in the 6.5 months since. Source: [releases](https://github.com/drupal/cms-launcher/releases), GitHub API 2026-10-07.
- Downloads (installers, all releases, including auto-update downloads): Windows 8,492, macOS 4,035, Linux 642, total 13,169. Update-check files on v1.0.9: `latest.yml` (Windows) 5,460, `latest-mac.yml` 687, `latest-linux.yml` 209. Source: GitHub API 2026-10-07.
- Most-discussed issues: "The website encountered an unexpected error" ([issue 70](https://github.com/drupal/cms-launcher/issues/70), 2025-06-05, 22 comments); Linux on Ubuntu 24 ([issue 61](https://github.com/drupal/cms-launcher/issues/61)); "`composer install` shouldn't timeout" ([issue 67](https://github.com/drupal/cms-launcher/issues/67)); "StageException: Cannot find rsync., on Windows" ([issue 45](https://github.com/drupal/cms-launcher/issues/45), 2025-04-11); "Create a way for the launcher to 'update' an existing Drupal CMS site" ([issue 97](https://github.com/drupal/cms-launcher/issues/97), 2025-07-11, open); "Document that HTTPS is not supported" ([issue 161](https://github.com/drupal/cms-launcher/issues/161), 2025-09-23). Source: [cms-launcher issues](https://github.com/drupal/cms-launcher/issues), GitHub search API 2026-10-07.
- Official page: "the easiest way to try Drupal CMS locally", for macOS and Windows, Linux through GitHub releases. Source: [new.drupal.org: Drupal CMS Launcher](https://new.drupal.org/drupal-cms/launcher), page read 2026-10-07.
- Conflict: The Drop Times says the Launcher "includes a web server, SQLite database, and Drush". The README does not mention Drush. Source: [The Drop Times: Drupal CMS Launcher Simplifies Local Setup](https://www.thedroptimes.com/47879/drupal-cms-launcher-simplifies-local-setup-with-desktop-app), page read 2026-10-07.

#### Drupal in the browser (WebAssembly)

- Matt Glaman built a WebAssembly trial prototype for the Starshot (Drupal CMS) trial experience: PHP compiled with Emscripten, IndexedDB storage, service workers for requests. Limits: no persistence, and the site cannot be visited by others. Source: [The Drop Times: Web APIs and WebAssembly Revolutionize Drupal CMS Trials](https://www.thedroptimes.com/44528/web-apis-and-webassembly-revolutionize-drupal-cms-trials-matt-glaman-writes), page read 2026-10-07; [DrupalCon Barcelona 2024 session: Running Drupal on the edge with WebAssembly](https://events.drupal.org/barcelona2024/session/running-drupal-edge-web-assembly).
- The prototype repository mglaman/wasm-drupal has 40 stars and was last pushed on 2025-11-03. Source: [mglaman/wasm-drupal](https://github.com/mglaman/wasm-drupal), GitHub API 2026-10-07.
- The current try-drupal page offers an Acquia Cloud Platform hosted trial, self-hosting and the DDEV-based Local Development Guide. It does not list the WebAssembly trial or the Launcher. Source: [new.drupal.org/try-drupal](https://new.drupal.org/try-drupal), page read 2026-10-07.

#### Acquia Dev Desktop (dead)

- Launched in March 2009 as "Drupal Stack Installer". "For many years, it was the de facto starting point for evaluating Drupal ... It was a GUI-based, Windows and OSX compatible, novice-friendly launch pad. It introduced Drupal to many thousands of developers". Source: [Acquia Dev Desktop end of life (archived)](https://web.archive.org/web/20220129114516/https://dev.acquia.com/blog/announcing-acquia-dev-desktop-end-life), 2021-02-24 (historical).
- End: announced on 2021-02-24 by Matthew Grasmick; end of life on 2021-06-30 (about four months' notice). Dev Desktop 1 had already ended on 2020-03-07. Sources: same post; [Acquia end-of-life schedule](https://docs.acquia.com/service-offerings/software-end-life-schedule), page read 2026-10-07.
- Stated reasons: "Drupal development practices have evolved beyond it"; VMs and containers "offer benefits around portability and standardization"; cloud IDEs bring "ease of use, repeatability and performance". Replacement: Acquia Cloud IDE, "the best development environment for Drupal"; for local work, Lando with Acquia CLI. Source: same post.

#### Pantheon Localdev (dead)

- Localdev bundled Lando and Docker Desktop: "Localdev 1.0.1 improvements include updates to Lando, Composer, and Docker Desktop versions." Source: [Pantheon release note: Localdev 1.0.1](https://docs.pantheon.io/release-notes/2021/02/localdev-1-0-1), 2021-02-01 (historical).
- End: "Pantheon will discontinue support for its built-in Localdev feature in June 2023." Reason given: "a number of more fully-featured options have become available". Recommended: Lando and DDEV. Sources: [release note](https://docs.pantheon.io/release-notes/2023/02/localdev), 2023-02-01; [Pantheon blog: Learn about alternatives to Pantheon's LocalDev](https://pantheon.io/blog/learn-about-alternatives-pantheons-localdev), 2023-02-09.
- Pantheon's local development guide says Pantheon does not support local development tools itself and lists Lando, MAMP, WAMP and XAMPP. Source: [Pantheon docs: Local Development](https://docs.pantheon.io/guides/local-development), last reviewed 2022-03-10.

#### Bitnami installers, Drupal VM and Kalabox (dead)

- Bitnami discontinued "all native installers" for OS X, Windows and Linux by 2022-11-30 to focus on cloud images, VMs, containers and Helm charts. Source: [Bitnami blog](https://blog.bitnami.com/2022/11/focusing-on-cloud-native-solutions.html), 2022-11-09.
- Drupal VM (Vagrant and Ansible): "DEPRECATION NOTICE: Drupal VM is no longer maintained, and no new releases will be made." The maintainer wrote in 2021 that he no longer used it, lacked "the bandwidth to continue maintaining Drupal VM on my own", and used per-project `docker-compose.yml` files instead. Archived; 1,346 stars. Sources: [geerlingguy/drupal-vm](https://github.com/geerlingguy/drupal-vm), read 2026-10-07; [Drupal VM's future, #2164](https://github.com/geerlingguy/drupal-vm/issues/2164), 2021-05-08 (historical).
- Kalabox: archived; its repository description calls it deprecated. Pantheon's docs describe it as discontinued and Lando as its successor. Sources: [kalabox/kalabox](https://github.com/kalabox/kalabox), GitHub API 2026-10-07; [Pantheon docs: Kalabox](https://pantheon.io/docs/kalabox) (search snippet only).

#### Laragon (Windows)

- Status: latest 8.7.0 on 2026-08-14; 5,427 stars; no licence file in the repository. "Starting with Laragon 7.x, a license is required to use Laragon." The earliest 7.x release on GitHub is 7.0.6 (2024-12-16). Source: [leokhoa/laragon](https://github.com/leokhoa/laragon), GitHub API 2026-10-07.
- Mechanisms the README claims: "Use `app.test` instead of `localhost/app`"; Laragon "auto-configs" so users can "add another versions of PHP, Python, Ruby, Java, Go, Apache, Nginx, MySQL, PostgreSQL, MongoDB". How names resolve and how TLS is trusted is not verified. Source: README, read 2026-10-07.
- Prices: non-commercial use without a licence is $0 with reminder pop-ups; non-commercial licences $10 to $40; commercial $49 per year (one device) to $149 lifetime (one device), team $149 to $399. Source: [laragon.org/pricing](https://laragon.org/pricing), page read 2026-10-07.
- Adoption: 4,792,493 release downloads on GitHub in total; 475,717 for 8.6.1 alone (released 2026-03-02). Source: GitHub API 2026-10-07.
- Complaints: "Laragon 7.0 keeps asking for my License Key?" ([discussion 985](https://github.com/leokhoa/laragon/discussions/985), 2025-01-21, 66 upvotes); a licence purchase window that forces a website open ([issue 1338](https://github.com/leokhoa/laragon/issues/1338), 2025-10-13); "Laragon 7.0.3 executable detected as Trojan:Win32/Phonzy.A!ml" ([issue 999](https://github.com/leokhoa/laragon/issues/999), 2025-01-29); "SSL issue with Laragon 7" ([issue 1027](https://github.com/leokhoa/laragon/issues/1027), 2025-02-16, 32 comments); a security disclosure for CVE-2024-0864 ([issue 613](https://github.com/leokhoa/laragon/issues/613)). Source: [Laragon issues and discussions](https://github.com/leokhoa/laragon/discussions/985), read 2026-10-07.

#### XAMPP, MAMP and WampServer

- XAMPP: the newest Windows builds (8.2.12, 8.1.25, 8.0.30) are all dated 2023-11-21; the newest macOS build is 8.2.4 (2023-04-06). Each package carries one PHP version. Yet 8.2.12 for Windows shows 255,244 weekly downloads on SourceForge. Sources: [SourceForge: XAMPP Windows](https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/), page read 2026-10-07; [apachefriends.org download](https://www.apachefriends.org/download.html), page read 2026-10-07.
- MAMP and MAMP PRO: macOS 7.4.0 (2026-02-02); Windows 5.0.6 (2024-04-25), so the Windows edition lags. MAMP PRO is "the commercial, professional frontend" and lets users "create a separate host for each of your web projects". Prices not verified. Sources: [MAMP downloads](https://www.mamp.info/en/downloads/), page read 2026-10-07; [MAMP PRO](https://www.mamp.info/en/mamp-pro/), page read 2026-10-07.
- WampServer (Windows only): 3.4.0 updated to 3.4.2 as of 2026-10-02; PHP 5.6 to 8.5.11, Apache 2.4.69, MySQL 5.5 to 9.7.2, MariaDB 10.5 to 13.0.2. Users can "change the PHP version of each VirtualHost" through Apache's fcgi_module. Maintained by one person, Dominique Ottello (Otomatic). Source: [WampServer updates and add-ons site](https://wampserver.aviatechno.net/), page read 2026-10-07.

#### ServBay, FlyEnv, Local and lerd (newer native or near-native tools)

- ServBay: commercial, macOS 12+ and Windows 10/11; "Multiple PHP instances running simultaneously" for PHP 5.6 to 8.5; built-in DNS; ACME certificates. Free plan: 5 websites and 5 DNS records. Pro: $9.90 per month or $59 per year. Team: $39.90 per month or $399 per year. Source: [servbay.com/pricing](https://www.servbay.com/pricing), page read 2026-10-07.
- FlyEnv (formerly PhpWebStudy): "Native local development environment for Windows, macOS & Linux. A modern alternative to XAMPP, MAMP, Laragon"; BSD-3-Clause; v4.19.1 on 2026-09-27; 3,263 stars; 447,819 release downloads. Source: [xpf0000/FlyEnv](https://github.com/xpf0000/FlyEnv), GitHub API 2026-10-07.
- Local (WP Engine, WordPress only): an nginx "Router" on port 80 proxies each site to its own server on a high port (for example 10011). "Site Domains" mode uses the hosts file; "LocalHost" mode is the fallback when port 80 is taken. Source: [Local community forum answer](https://community.localwp.com/t/running-apache-in-local-but-network-tab-in-chrome-shows-nginx-server-in-asset-header/22245), page read 2026-10-07 (community source, not official docs).
- lerd (new in 2026): "Open-source Herd-like local PHP development environment for Linux and macOS, with Windows supported via WSL2 (beta). Podman-native, rootless". It "runs Nginx, PHP-FPM, and your services as rootless Podman containers"; automatic `.test` domains "with no dnsmasq, no system resolver tweak and no sudo for the DNS bits"; a per-site FrankenPHP runtime; it writes Drupal's `settings.php`. A beta "PHP on the host, on macOS" mode exists because of the bind-mount cost; vendor claim: a Laravel app went "from 62 requests per second to 238". Created 2026-03-17, 1,373 stars by 2026-10-07, v1.36.0 on 2026-10-02. Source: [lerd-env/lerd](https://github.com/lerd-env/lerd), read 2026-10-07.

#### Lando, Docksal and DDEV (Docker-based)

- Lando: Traefik proxy; "*.lndo.site is an actual ON THE INTERNET wildcard DNS entry that points all *.lndo.site subdomains to localhost/127.0.0.1"; Lando generates its own CA and issues wildcard certificates. Source: [Lando docs: Proxy](https://docs.lando.dev/landofile/proxy.html), page read 2026-10-07.
- Lando health: Lando 3 is at v3.26.9 (2026-08-20). Lando 4 is still tagged unstable (v4.0.0-unstable.8 on 2025-12-09; unstable.6 was 2023-12-07). GitHub Sponsors goal "$5,000 per month" is 4% funded with 22 sponsors. Sources: [lando/core releases](https://github.com/lando/core/releases), GitHub API 2026-10-07; [GitHub Sponsors: lando](https://github.com/sponsors/lando), GraphQL API 2026-10-07.
- Docksal: last release v1.19.0 on 2024-05-24; last commit 2025-12-19; 721 stars. Source: [docksal/docksal](https://github.com/docksal/docksal), GitHub API 2026-10-07.
- DDEV (reference only): v1.25.4 on 2026-09-02, daily commits. On Windows the docs call "Docker CE inside WSL2" "Recommended, most popular, performant, and best-supported"; on macOS OrbStack is "very popular with DDEV users". Sources: [ddev/ddev](https://github.com/ddev/ddev), GitHub API 2026-10-07; [DDEV docs: Docker installation](https://docs.ddev.com/en/stable/users/install/docker-installation/), page read 2026-10-07.
- Drupal's official guide: "The Drupal community recommends using DDEV, a free, cross-platform local development solution." Other tools exist but are not named. Source: [drupal.org Local Development Guide](https://www.drupal.org/docs/official_docs/local-development-guide), page read 2026-10-07.

#### Cloud alternatives (positioning only)

- Gitpod was renamed Ona; "Gitpod Classic pay-as-you-go did sunset on October 15th 2025". DrupalPod's GitHub README now says the repository "HAS MOVED TO https://drupal.org/project/DrupalPod". Sources: [gitpod-io/gitpod](https://github.com/gitpod-io/gitpod), read 2026-10-07; [shaal/DrupalPod](https://github.com/shaal/DrupalPod), read 2026-10-07.
- Drupal CMS trial: a hosted trial by Acquia, described as the partner selected by the Drupal Association, runs four hours (search snippet only). Drupal Forge (DevPanel) earlier covered trial costs and offered shareable trial URLs (search snippet only). Sources: [The Drop Times node 45730](https://www.thedroptimes.com/node/45730); [The Drop Times node 42630](https://www.thedroptimes.com/node/42630).
- Upsun (formerly Platform.sh) names DDEV as its recommended local tool and maintains the integration (`ddev pull upsun`). Source: [Upsun docs: Use DDEV](https://docs.upsun.com/development/local/ddev.html), page read 2026-10-07.
- Lagoon (amazee.io): "You do not need to install Lagoon locally"; projects run their Lagoon images locally with Docker Compose and the optional pygmy stack (`*.docker.amazee.io` names). Source: [Lagoon docs: Local development environments](https://docs.lagoon.sh/using-lagoon-the-basics/local-development-environments/), page read 2026-10-07.

#### FrankenPHP and Caddy

- What it is: Go plus Caddy plus PHP's embed SAPI; maintained under github.com/php since the PHP Foundation took it on (2025-05-15), with Kévin Dunglas, Robert Landers and Alexander Stecher keeping control of releases. 11,391 stars; latest v1.13.0 on 2026-10-04. Sources: [PHP Foundation: FrankenPHP Is Now Officially Supported](https://thephp.foundation/blog/2025/05/15/frankenphp/), 2025-05-15; [php/frankenphp](https://github.com/php/frankenphp), GitHub API 2026-10-07.
- Binaries: "We provide FrankenPHP binaries for Linux, macOS and Windows containing PHP 8.5. Linux binaries are statically linked ... macOS binaries are also self-contained. Windows archives contain the official PHP binary for Windows." Windows install: `irm https://frankenphp.dev/install.ps1 | iex`. Source: [FrankenPHP README](https://github.com/php/frankenphp/blob/main/README.md), read 2026-10-07.
- Static builds use static-php-cli; fully static musl builds "can't load dynamic PHP extensions (such as Xdebug)"; mostly static glibc builds can. Source: [docs/static.md](https://github.com/php/frankenphp/blob/main/docs/static.md), read 2026-10-07.
- Configuration: "Configure FrankenPHP and Caddy via Caddyfile, JSON, or environment variables." The `php_server` directive expands to a route with `try_files`, `php` for `*.php`, and `file_server` for static files. Source: [docs/config.md](https://github.com/php/frankenphp/blob/main/docs/config.md), read 2026-10-07.
- Caddy admin API: listens on `localhost:2019` by default (`CADDY_ADMIN`); `POST /load`; `GET/POST/PUT/PATCH/DELETE /config/[path]`; `POST /stop`; `POST /adapt`; changes apply "gracefully with zero downtime" and roll back on failure; `Etag` and `If-Match` for concurrent edits. Source: [Caddy docs: API](https://caddyserver.com/docs/api), page read 2026-10-07.
- Caddy local CA: certificates for `localhost`, IPs and names ending in `.local`, `.internal`, `.home.arpa`; a root and an intermediate in `pki/authorities/local`, "powered by Smallstep libraries"; Caddy tries to install the root in the system trust store and asks for a password if needed; `caddy trust` retries. Source: [Caddy docs: Automatic HTTPS](https://caddyserver.com/docs/automatic-https), page read 2026-10-07.
- Windows gaps (open issues): "Standalone binaries (embed) and static builds for Windows" ([issue 2503](https://github.com/php/frankenphp/issues/2503), 2026-07-03); "max_execution_time always 0 on Windows" ([issue 2474](https://github.com/php/frankenphp/issues/2474), 2026-06-08); "service integration on windows and mac" ([issue 2442](https://github.com/php/frankenphp/issues/2442), 2026-05-23); a malware flag on the Windows build ([issue 2446](https://github.com/php/frankenphp/issues/2446), 2026-05-24, closed). Windows archive downloads are small (for example 166 for v1.12.0). Source: [FrankenPHP issues](https://github.com/php/frankenphp/issues), GitHub API 2026-10-07.
- Thread-safety caveats: extensions such as imap, newrelic and pcov are "Not thread-safe"; ImageMagick's OpenMP threads conflict with FrankenPHP's threads. Source: [docs/known-issues.md](https://github.com/php/frankenphp/blob/main/docs/known-issues.md), read 2026-10-07.
- Drupal: a drupal.org issue proposes a Caddyfile so Drupal can run on FrankenPHP, linked to a meta issue on persistent app servers; DDEV maintains a FrankenPHP add-on (search snippets only). Sources: [drupal.org node 3437187](https://www.drupal.org/node/3437187); [DDEV add-on: ddev-frankenphp](https://addons.ddev.com/addons/ddev/ddev-frankenphp).

#### RoadRunner, static-php-cli and php-wasm

- RoadRunner: Go server with "automatic TLS management" and middleware such as Static; PHP runs in `php worker.php` processes using PSR-7 workers; config in `.rr.yaml`; binaries for macOS, Linux, FreeBSD and Windows. Latest v2025.1.15 (2026-06-17) ships a windows-amd64 zip; 8,510 stars. Sources: [RoadRunner README](https://github.com/roadrunner-server/roadrunner), read 2026-10-07; GitHub API 2026-10-07.
- static-php-cli: builds standalone PHP for Linux, macOS and Windows; Unix builds offer `--build-cli`, `--build-fpm`, `--build-cgi`, `--build-micro`, `--build-embed`, `--build-frankenphp`; latest 2.8.5 (2026-04-18); 1,949 stars; the maintainer has 2 public GitHub sponsors. Used by Herd at launch, by the Drupal CMS Launcher and by FrankenPHP static builds. Sources: [static-php-cli README and manual-build docs](https://github.com/crazywhalecc/static-php-cli), read 2026-10-07; GitHub GraphQL API 2026-10-07; Herd and Launcher sources above.
- php-wasm (WordPress Playground): 1,973 stars. Limits: autosaves "are recovery points, not long-term backups"; temporary playgrounds vanish on refresh; 5 to 10 seconds to load WordPress, 30 to 60 seconds with large plugins; WP-CLI supports a subset. Sources: [WordPress/wordpress-playground](https://github.com/WordPress/wordpress-playground), GitHub API 2026-10-07; [Playground limitations](https://developer.wordpress.org/playground/developers/limitations/), page read 2026-10-07.

#### NGINX Unit and FreeUnit (public facts)

- NGINX Unit is archived: "This repository has been archived. There will likely be no further development at this repo, and security vulnerabilities may be unaddressed." The README was updated for archiving on 2025-10-06; the last release was 1.35.0 (2025-09-11). Source: [nginx/unit](https://github.com/nginx/unit), read 2026-10-07.
- Platforms: "Unit compiles and runs on various Unix-like operating systems, including: FreeBSD 10 or later, Linux 2.6 or later, macOS 10.6 or later, Solaris 11." Windows is not listed. Unit supports several versions of the same language at once through separate modules. Source: [Unit docs: Installation](https://unit.nginx.org/installation/), page read 2026-10-07.
- Configuration and isolation: a JSON control API on a control socket; per-application `user`, `group` and `isolation` (namespaces, cgroups, rootfs); a `share` action serves static files; PHP apps scale with `processes` `max`, `spare` and `idle_timeout`. Source: [Unit docs: Configuration](https://unit.nginx.org/configuration/), page read 2026-10-07.
- TLS: a PEM bundle is uploaded with `PUT /certificates/<name>` over the control socket. The docs describe no built-in issuance and no local CA; their issuance example uses Certbot. Source: [Unit docs: Certificates](https://unit.nginx.org/certificates/), page read 2026-10-07.
- FreeUnit: Apache-2.0, latest release 1.37.0 on 2026-10-04, homepage https://freeunit.org. Source: [freeunitorg/freeunit](https://github.com/freeunitorg/freeunit), GitHub API 2026-10-07.

#### Comparison matrix A: runtime mechanics (state on 2026-10-07)

"n/v" means not verified in this research. Sources are in the profiles above.

| Tool | Status | How PHP runs | PHP versions side by side | TLS for local names | Host names and DNS | Process supervision | Windows |
|---|---|---|---|---|---|---|---|
| Laravel Herd (macOS) | Active, 1.30.1 (2026-09-16) | nginx plus php-fpm per version; static PHP builds at launch (2023) | Global default plus `herd isolate` per site | Local CA via `herd secure`; trust method not documented | Bundled dnsmasq for `*.test` | Privileged background service runs nginx and dnsmasq | Separate edition |
| Laravel Herd (Windows) | Active, 1.30.0 (2026-08-19) | nginx plus one `php-cgi.exe` per version on a local port, spawned by the Electron app (user logs) | Same as macOS | Local root CA, 20-year validity since 2025-04-02 | HerdHelper Windows service edits the hosts file | Electron app (internal API port 9005) plus HerdHelper service | Native |
| Laravel Valet (macOS) | Active, v4.12.0 (2026-03-10) | Homebrew nginx plus php-fpm socket | `valet isolate` | Own CA trusted on the Mac | dnsmasq for `*.test` | Daemons start at boot | No |
| Valet for Windows (forks) | Slow, 2.5.3 (2025-03-01); fork 3.4.5 (2026-07-05) | nginx plus PHP | n/v | n/v | Acrylic DNS Proxy, manual setup on Windows 10 and 11 | Windows services (nginx service install failures reported) | Native |
| Symfony CLI | Active, v5.22.0 (2026-10-06) | Go front server; php-fpm (pm dynamic, 30 children), or php-cgi, or `php -S` | `.php-version` per project; discovers installed PHPs | mkcert fork; OS stores plus NSS | PAC proxy for `*.wip`; no DNS | Daemon mode; workers with file watching | Native, php-cgi |
| PHP built-in server | Part of PHP | CLI SAPI, one process; `PHP_CLI_SERVER_WORKERS` on Unix only | One per process | None | None | None | Yes, single worker |
| Drupal CMS Launcher | Active but slow, v1.0.9 (2026-03-23) | Electron runs static PHP 8.4 with `php -S` and `.ht.router.php` | One (8.4) | None | `localhost` on a port 8888 to 9999 | Electron app owns the PHP child | Native |
| Acquia Dev Desktop | Dead since 2021-06-30 | Bundled stack, GUI | n/v | n/v | n/v | GUI | Native (historical) |
| Pantheon Localdev | Dead since June 2023 | Lando plus bundled Docker Desktop | Through Lando | Through Lando | Through Lando | Docker | Through Docker Desktop |
| Laragon | Active, 8.7.0 (2026-08-14); paid since 7.x | Apache or nginx with PHP (n/v) | Several installed (switching model n/v) | n/v | `app.test` names (method n/v) | App-managed | Windows only |
| XAMPP | Stale since 2023-11-21 | Apache with PHP (n/v) | One PHP per package | Manual | Manual | Control panel | Yes |
| MAMP / MAMP PRO | macOS 7.4.0 (2026-02-02); Windows 5.0.6 (2024-04-25) | n/v | PRO: per host (n/v) | n/v | PRO: one host per project | App | Yes, lagging |
| WampServer | Active, 3.4.x (2026-10-02) | Apache; per-VirtualHost PHP through fcgi_module | Yes, per VirtualHost | Manual | VirtualHosts | n/v | Windows only |
| ServBay | Active, commercial | n/v | "Multiple PHP instances running simultaneously" | ACME and custom certificates | Built-in DNS | App | Windows 10 and 11 |
| lerd | Active, v1.36.0 (2026-10-02) | Rootless Podman containers; host PHP on macOS in beta; optional FrankenPHP | Per project | One-command TLS | `.test` without dnsmasq or sudo | Podman | WSL2 beta |
| Lando | Active, v3.26.9 (2026-08-20); v4 unstable | Docker Compose containers | Per project | Own CA, wildcard certificates | Public `*.lndo.site` DNS to 127.0.0.1; Traefik | Docker | Docker Desktop or WSL2 |
| Docksal | Stalled, v1.19.0 (2024-05-24) | Docker | Per project | n/v | n/v | Docker | n/v |
| FrankenPHP | Active, v1.13.0 (2026-10-04) | Embed SAPI inside the Go and Caddy process; worker mode | One PHP per binary (inference) | Caddy local CA; `caddy trust` | `localhost`, `.local`, `.internal` names; no DNS management | One process; Caddy reloads config | Native since v1.12.0 (2026-03-06), no static or embedded builds |
| RoadRunner | Active, v2025.1.15 (2026-06-17) | Go server plus php-cli worker processes (PSR-7) | n/v | "automatic TLS management" | None | RoadRunner supervises workers | Yes |
| NGINX Unit / FreeUnit | Unit archived (2025-10); FreeUnit 1.37.0 (2026-10-04) | PHP module inside per-app processes; separate router | Several language modules at once | Certificates uploaded over the API; no local CA | None | Unit supervises app processes (`max`, `spare`, `idle_timeout`) | No |

#### Comparison matrix B: services, debugging, mail, adoption and money

| Tool | Databases and services | Xdebug | Mail catching | Adoption signal | Business model |
|---|---|---|---|---|---|
| Laravel Herd | Pro only: MySQL, MariaDB, PostgreSQL, MongoDB, Redis, Valkey, Typesense, Meilisearch, MinIO, RustFS, Reverb | Free: optional extension; Pro: per-request detection | Pro: SMTP 127.0.0.1:2525 with a viewer | No official user numbers found; 1,294 tracker issues; Homebrew cask 4,161 installs in 365 days (DMG is the main channel) | Freemium: Pro EUR 99 per year, Teams EUR 299 per year |
| Laravel Valet | None; docs point to DBngin | n/v | None | 2,604 stars | Free, MIT, Laravel |
| Symfony CLI | Uses Docker Compose services, injects env vars | n/v | Through a mail-catcher container (`MAILER_*`) | 9.19 million release downloads (includes CI); Homebrew 1,961 in 365 days | Free, AGPL-3.0; tied to Upsun commands |
| Drupal CMS Launcher | SQLite only | None | "Might not be able to send email" | 13,169 installer downloads in total; 5,460 Windows update checks on v1.0.9 | Free, MIT, Drupal Association |
| Laragon | Several databases installable | n/v | n/v | 4.79 million GitHub release downloads | Paid licence since 7.x; free non-commercial use with pop-ups |
| XAMPP | MariaDB bundled | n/v | n/v | 255,244 weekly downloads of one Windows build | Free |
| MAMP / MAMP PRO | n/v | n/v | n/v | Homebrew cask 1,893 in 365 days | Free plus paid PRO (price n/v) |
| WampServer | MySQL and MariaDB versions as add-ons | n/v | n/v | n/v | Free, one maintainer |
| ServBay | Databases; email servers in Pro | n/v | Pro: email servers | 455 stars on its public repo | Freemium: Pro $59 per year, Team $399 per year |
| FlyEnv | n/v | n/v | n/v | 447,819 release downloads; 3,263 stars | Free, BSD-3-Clause |
| Lando | Docker services through recipes | n/v | n/v | 4,243 stars | Open source; sponsorship goal 4% funded |
| DDEV (reference) | Docker services and add-ons | Integrated (search snippet of drupal.org) | Mailpit (search snippet of drupal.org) | Drupal's recommended tool since June 2024 | Non-profit foundation, sponsorship |
| FrankenPHP | None | Not in fully static musl builds | None | 11,391 stars | Open source, PHP Foundation |
| NGINX Unit / FreeUnit | None | n/v | None | Unit 5,538 stars (archived); FreeUnit 57 stars | Unit dropped by F5; FreeUnit community |

### Inferences

- Inference: Herd's success rests on four mechanisms that freeunit4drupal must match: prebuilt PHP binaries that do not depend on a package manager, a privileged helper for DNS (dnsmasq on macOS, hosts file on Windows), a local CA, and one GUI plus CLI front end. The Valet heritage shows that the engine can stay a thin config generator (nginx site files) while the product value is in packaging.
- Inference: the vendor-backed deaths share one cause. Each tool was a cost centre for a hosting company (Acquia, Pantheon, Bitnami). When the vendor's strategy moved to cloud IDEs or cloud images, the tool ended with months of notice. A FreeUnit-based tool should not depend on one hosting vendor's budget.
- Inference: the maintainer deaths share another cause. Drupal VM, Docksal and Valet for Windows each depended on one or two people. Laragon shows the opposite risk: one maintainer can also turn a free tool into a paid one.
- Inference: tools that stop shipping new PHP versions do not die quickly. XAMPP still gets about a quarter million weekly downloads of a 2023 build. Beginners follow old tutorials. Drupal's PHP floor (the Launcher uses PHP 8.4 as "the minimum recommended for Drupal 11") will push those users off stale stacks.
- Inference: the Drupal CMS Launcher proves demand and the gap at the same time. It reached about 13,000 installer downloads with no Docker, but it runs one site, on SQLite, on a single-threaded server, without HTTPS or mail. Those are exactly the gaps a multi-process FreeUnit server with a CA and several PHP versions can fill.
- Inference: lessons for freeunit4drupal, in order: (1) ship PHP builds yourself, as Herd and the Launcher do; (2) solve names and TLS with a privileged helper and a long-lived local root, as Herd does, or with a PAC proxy that needs no admin rights, as Symfony CLI does; (3) offer per-site PHP versions, because every successful tool has them; (4) treat mail capture, Xdebug on demand and databases as the paid or pluggable layer, as Herd Pro does; (5) plan code signing and antivirus behaviour from day one on Windows; (6) aim to be listed in the Launcher's README and the drupal.org Local Development Guide, which today name DDEV first and Herd as the Docker-free option.

### Gaps

- No official Herd download or user numbers were found (Laracon keynotes and blog posts searched).
- How many php-cgi processes Herd for Windows runs per PHP version, and whether it sets `PHP_FCGI_CHILDREN`, is not documented.
- Where Herd installs its root CA on macOS and Windows is not documented.
- Acquia Dev Desktop's detailed mechanisms (PHP versions, hosts handling) were not verified.
- Laragon, XAMPP, MAMP, ServBay and FlyEnv mechanisms (how names resolve, how TLS is trusted, how PHP is run) were not verified from primary sources.
- MAMP PRO prices were not verified.
- Whether the Drupal CMS Launcher exposes Drush is unclear (The Drop Times says yes, the README is silent).
- Symfony CLI's Xdebug handling was not found in the docs.
- FrankenPHP performance on Windows and its Drupal readiness in worker mode were not measured; the drupal.org issues were seen only as search snippets.

## 2. What a Windows PHP developer uses today without Docker, and what they complain about

### Takeaway

No survey asks Windows PHP developers which native tool they use, so the picture comes from downloads, docs and issue trackers. The volume still sits with legacy all-in-one stacks (XAMPP and Laragon), while the officially endorsed path in the Laravel world is Herd for Windows, and Drupal's official path is DDEV on WSL2. The complaints repeat across tools: antivirus and Smart App Control, hosts-file permissions, port 80 conflicts, licence nags, and stale PHP. PHP on Windows has no FPM, so every native tool must supervise php-cgi or embed PHP itself.

### Cited Findings

#### How PHP runs on Windows

- There is no php-fpm in the Windows builds. A note on php.net explains that "FPM is built around fork(), which is not natively available on windows". Source: [PHP manual: FPM installation, user note](https://www.php.net/manual/en/install.fpm.php), page read 2026-10-07 (user note, not official text).
- NTS builds "are for single-threaded use cases, typically PHP running via FastCGI or on the CLI"; TS builds "are intended for PHP loaded as a web server module" (Apache). Builds use Visual Studio 2019 or 2022, for x64 and x86. Latest 8.5.11 on 2026-09-22. windows.php.net/download now redirects to php.net. Source: [php.net downloads for Windows](https://www.php.net/downloads.php?os=windows), page read 2026-10-07.
- php-cgi on Windows can run up to 64 children through `PHP_FCGI_CHILDREN` and respawns them. Source: [php-src cgi_main.c](https://github.com/php/php-src/blob/d854a54b5f52c2ca43228184e2a468b19f2163d2/sapi/cgi/cgi_main.c), 2024-09-26.
- The PHP Foundation now produces the official Windows builds with the php-windows-builder workflows; Microsoft used to provide the infrastructure. The post cites the 2025 Stack Overflow survey: "Close to 50% of respondents use Windows for development". Source: [PHP Foundation: Maintaining PHP Build infrastructure for Windows](https://thephp.foundation/blog/2026/06/26/maintaining-php-build-infrastructure-for-windows-tooling-for-builds-and-security-updates/), 2026-06-26.
- Real tools follow these constraints: Herd for Windows runs php-cgi per version behind nginx; Symfony CLI runs php-cgi; WampServer uses Apache with fcgi_module per VirtualHost; FrankenPHP for Windows ships "the official PHP binary for Windows". Sources: profiles in section 1.
- NGINX Unit, which FreeUnit forks, lists no Windows support. Source: [Unit docs: Installation](https://unit.nginx.org/installation/), page read 2026-10-07.

#### What Windows developers use (evidence)

- XAMPP for Windows 8.2.12 (2023-11-21): 255,244 weekly downloads on SourceForge. Source: [SourceForge](https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/), page read 2026-10-07.
- Laragon: 4,792,493 GitHub release downloads in total; 475,717 for 8.6.1 (2026-03-02). Source: [leokhoa/laragon](https://github.com/leokhoa/laragon), GitHub API 2026-10-07.
- FlyEnv (cross-platform): 447,819 release downloads. Source: [xpf0000/FlyEnv](https://github.com/xpf0000/FlyEnv), GitHub API 2026-10-07.
- Herd for Windows: no public numbers; Laravel's install page gives Windows users php.new (Herd Lite) and Herd. Source: [Laravel 13.x docs: Installation](https://laravel.com/docs/13.x/installation), page read 2026-10-07.
- Drupal CMS Launcher: Windows is its largest platform, 8,492 of 13,169 installer downloads. Source: GitHub API 2026-10-07.
- DDEV, Drupal's recommended tool, steers Windows users to "Docker CE inside WSL2" rather than Docker Desktop. Source: [DDEV docs](https://docs.ddev.com/en/stable/users/install/docker-installation/), page read 2026-10-07.
- Windows share among developers in these ecosystems: State of Laravel primary OS Windows 36.36% (2021), 29.79% (2022), 29.87% (2023), 26.94% (2024), 30.98% (2025), 20.41% (2026). Drupal developers in 2023: Mac nearly 56%, Linux 29%, Windows 15% (829 responses). Sources: [State of Laravel results](https://stateoflaravel.com/results), page read 2026-10-07; [Ironstar Drupal Local Development Survey 2023 (archived)](https://web.archive.org/web/20250909001037/https://www.ironstar.io/devsurvey23/), 2023-06-15.
- Community opinion (search snippets only, Reddit could not be fetched): r/laravel threads recommend Herd on Windows and say Sail is slower on macOS because of Docker file mounts; comparison articles name Laragon and Herd as the popular Windows choices, with XAMPP less favoured. Sources: [r/laravel thread "Laravel Herd or MAMP Pro", 2025-01-22 (mirror)](https://reddit.birdcat.cafe/r/laravel/comments/1i79cda/laravel_herd_or_mamp_pro) (search snippet only); [php.watch: Laragon](https://php.watch/articles/laragon-windows-php) (search snippet only).

#### Complaints, by tool

- Herd for Windows: Defender slows PHP; Smart App Control blocks unsigned PHP extension DLLs (2026-09-28); hosts file locked by antivirus; HerdHelper not running; fails in domain-controlled environments; port 80 and 443 conflicts with IIS or Apache; php-cgi crashes giving 504 for all sites. Sources: section 1, Herd for Windows.
- Laragon: licence pop-ups after the switch to paid 7.x; a Defender trojan false positive on 7.0.3; SSL problems in 7.x; unexpected shutdowns (#1271, 2025-08-03). Sources: section 1, Laragon; [#1271](https://github.com/leokhoa/laragon/issues/1271).
- Drupal CMS Launcher: Windows Defender Firewall blocks the local server; `rsync` is missing on Windows for Drupal's package manager ([issue 45](https://github.com/drupal/cms-launcher/issues/45)); long Composer installs and timeouts ([issue 67](https://github.com/drupal/cms-launcher/issues/67)). Sources: section 1, Launcher.
- Valet for Windows: Acrylic DNS must be configured by hand; the nginx service fails to install; slow time to first byte (historical). Source: section 1, Valet forks.
- Symfony CLI on Windows: other PHP versions not detected ([issue 511](https://github.com/symfony-cli/symfony-cli/issues/511)); CA not imported into the Windows store when the CLI runs in WSL ([issue 461](https://github.com/symfony-cli/symfony-cli/issues/461)). Source: section 1, Symfony CLI.
- FrankenPHP on Windows: `max_execution_time` always 0; a malware flag; no Windows service integration yet. Source: section 1, FrankenPHP.
- XAMPP and MAMP for Windows: no new XAMPP build since 2023-11-21; MAMP for Windows stuck at 5.0.6 (2024-04-25) while macOS reached 7.4.0. Source: section 1.

### Inferences

- Inference: the Windows PHP developer today without Docker uses, by volume, XAMPP and Laragon; by endorsement, Herd for Windows (Laravel) or WSL2 (Drupal via DDEV). The evidence is download counts and docs, not a survey. Treat the ranking as weak.
- Inference: any native Windows tool needs code-signed binaries, including PHP extension DLLs, or Smart App Control and Defender will break it. Herd's and Laragon's issue trackers show this is not rare.
- Inference: Windows share is falling among Laravel survey respondents (from 36% in 2021 to 20% in 2026) and is about 15% among Drupal survey respondents (2023). A FreeUnit tool that starts on Linux and macOS and reaches Windows through WSL2 would cover most Drupal developers on day one. DDEV's own advice proves WSL2 is acceptable to Drupal developers.
- Inference: if a native Windows mode is needed later, two proven patterns exist: a supervisor that runs `php-cgi.exe` with `PHP_FCGI_CHILDREN` behind a front server (Herd and Symfony CLI style), or FrankenPHP for Windows as the PHP server. FreeUnit itself would need a port, since Unit never supported Windows.

### Gaps

- No survey of Windows-only PHP developers and their tools was found. JetBrains reports did not expose numbers for local environments.
- Reddit threads could not be read in full; opinions above are snippets.
- Download counts for Herd for Windows, WampServer and MAMP for Windows were not found.

## 3. Does native tooling beat Docker-based tooling when a strong native option exists? (Herd versus Sail)

### Takeaway

Partly. In the Laravel world, local (native) environments led containers before Herd existed and kept growing after it: from 58.41% (2023) to 64.42% (2026) of State of Laravel respondents, while containers stayed near 44% to 47%. Laravel itself moved its install docs from Sail first (2022) to Herd and php.new first (2024), then removed the Sail section entirely (2025-02-25). But Docker did not collapse, and in Drupal the opposite happened: with no strong native option, the community standardised on DDEV. The evidence is correlational and self-selected.

### Cited Findings

- State of Laravel (Tobias Petry), question "Which PHP development environments do you use for development?" (multiple choice). Respondents: 4,568 (2021), 2,635 (2022), 4,047 (2023), 4,090 (2024), 3,238 (2025), 1,852 (2026). Source: [State of Laravel results](https://stateoflaravel.com/results), page titled "State of Laravel 2026 Results", read 2026-10-07.

| Year | Local (Valet, Herd, local web server, WAMP, XAMPP) | Container (Sail, Laradock, Docker) | `php artisan serve` | Remote | Virtualized (Homestead, Vagrant) |
|---|---|---|---|---|---|
| 2021 | 62.19% | 37.46% | 38.75% | 20.12% | 14.73% |
| 2022 | 61.48% | 44.93% | 34.57% | 13.97% | 10.89% |
| 2023 | 58.41% | 47.39% | 36.60% | 16.26% | 7.98% |
| 2024 | 63.25% | 44.84% | 34.08% | 15.13% | 5.77% |
| 2025 | 63.74% | 43.85% | 37.71% | 15.60% | 4.17% |
| 2026 | 64.42% | 46.38% | 34.02% | 12.15% | 2.75% |

- In the same survey, macOS as the primary OS rose from 38.84% (2021) to 57.72% (2026). Source: same page.
- Laravel's install docs, step by step:
  - 2022-03-15: "Your First Laravel Project" with "Getting Started On macOS / Windows / Linux", all through Docker Desktop and Sail. Source: [Laravel 9.x installation (archived)](https://web.archive.org/web/20220315141016/https://laravel.com/docs/9.x/installation), 2022-03-15 (historical).
  - 2023-07-24: commit "mention herd". Source: [laravel/docs c021c80946](https://github.com/laravel/docs/commit/c021c80946), 2023-07-24.
  - 2024-04-02: "If you are developing on macOS or Windows, PHP and Composer can be installed in minutes via Laravel Herd", with the Sail sections still present. Source: [Laravel 11.x installation (archived)](https://web.archive.org/web/20240402045718/https://laravel.com/docs/11.x/installation), 2024-04-02.
  - 2024-06-20: "Add Herd installation guide (#9717)" ([laravel/docs pull request 9717](https://github.com/laravel/docs/pull/9717)). Source: [laravel/docs e62a893a9e](https://github.com/laravel/docs/commit/e62a893a9e), 2024-06-20.
  - 2024-10-08: Taylor Otwell adds the php.new commands ("document php nwe"). Source: [laravel/docs 44f2375021](https://github.com/laravel/docs/commit/44f2375021), 2024-10-08.
  - 2025-02-25: Taylor Otwell, "streamline install docs", deletes "Docker Installation Using Sail", "Sail on macOS", "Sail on Windows", "Sail on Linux" and "Choosing Your Sail Services" from the install page (152 lines removed). Source: [laravel/docs e82816b331](https://github.com/laravel/docs/commit/e82816b331), 2025-02-25.
  - Today: the 13.x install page shows php.new for PHP 8.5 and "Installation Using Herd", and does not mention Docker Desktop. Source: [Laravel 13.x installation](https://laravel.com/docs/13.x/installation), page read 2026-10-07.
- Herd's stated reasons for going native: Homebrew overhead and breakage, and faster static PHP (vendor claim, see section 1). No official Laravel statement explaining the docs change was found.
- Drupal counter-example: about 70% of 829 Drupal respondents used container-based local environments in 2023, and 59% said Docker knowledge is important for a Drupal developer; DDEV led overall and Lando led in Australia and New Zealand. Source: [Ironstar survey 2023 (archived)](https://web.archive.org/web/20250909001037/https://www.ironstar.io/devsurvey23/), 2023-06-15.
- In 2020 (about 600 respondents), "Drupal VM, Dev Desktop, Vagrant, and Homebrew (most of the 'non-Docker' solutions) have put on the brakes" while Lando, Docksal and DDEV grew. Source: [Jeff Geerling: 2020 Drupal Local Development Survey Results](https://www.jeffgeerling.com/blog/2020/2020-drupal-local-development-survey-results/), 2020-05-08 (historical).
- The 2025 Drupal developer survey reportedly found 93% of DDEV users recommend it, 72% for Homebrew setups and 51% for Lando (search snippet only; the results page is gone). Source: [ironstar.io/devsurvey25](https://ironstar.io/devsurvey25) (search snippet only).
- JetBrains 2023: "Most PHP developers remain loyal to local development environments. The majority of remote environment users use local ones, too." The chart numbers are not in the page HTML. Source: [JetBrains Developer Ecosystem 2023: PHP](https://www.jetbrains.com/lp/devecosystem-2023/php/), 2023 (historical).
- Homebrew installs in the last 365 days (read 2026-10-07): docker-desktop 527,472; orbstack 183,965; rancher 29,648; php 349,100; nginx 120,376; dnsmasq 29,701; mkcert 475,990; caddy 72,596; herd 4,161; local 2,924; dbngin 1,987; mamp 1,893; flyenv 887; xampp 325. Sources: [formulae.brew.sh](https://formulae.brew.sh/cask/docker-desktop) pages for each cask and formula, read 2026-10-07.
- Pressure on Docker: Docker Desktop is free only for "Small businesses (fewer than 250 employees AND less than $10 million in annual revenue)", personal use, education and non-commercial open source. Docker raised Pro to $9 and Team to $15 per user per month from 2024-12-10. Sources: [Docker Desktop license](https://docs.docker.com/subscription/desktop-license/), page read 2026-10-07; [Docker blog](https://www.docker.com/blog/updating-product-subscriptions/), 2024-09-12.
- Pressure on Docker on macOS: VirtioFS in Docker Desktop 4.6 "reduced the time taken to complete filesystem operations by up to 98%" compared with the gRPC-FUSE default, which shows how slow bind mounts were before. Even in 2026, lerd added host PHP on macOS because of "the bind-mount boundary a Mac pays on every file PHP reads". Sources: [Docker blog: VirtioFS](https://www.docker.com/blog/speed-boost-achievement-unlocked-on-docker-desktop-4-6-for-mac/), 2022-03-16 (historical); [lerd README](https://github.com/lerd-env/lerd), read 2026-10-07.

### Inferences

- Inference: native tooling wins when it is endorsed by the framework vendor and packaged well. In Laravel, the local category gained about 6 points after Herd and the docs change, virtualised setups collapsed (14.73% to 2.75%), but containers held about 46%. Herd took share from VMs and from manual setups more than from Docker.
- Inference: Docker wins when a community needs one shared, reproducible recipe and no native tool is good enough. That is Drupal's situation. A Docker-free tool for Drupal must match DDEV's "one command, same on every machine" promise, not just its speed.
- Inference: the strength of the evidence is moderate at best. State of Laravel is self-selected, its sample fell to 1,852 in 2026, and the "Local" category mixes Herd with XAMPP and WAMP. Homebrew analytics undercount DMG installs. No source separates Herd from Valet usage.

### Gaps

- No survey splits Herd from Valet, or Sail from plain Docker.
- No JetBrains numbers for local environments were retrievable.
- The full 2025 Drupal developer survey results could not be retrieved.
- No official Laravel statement on why Sail left the install page was found.

## 4. Which existing tool is closest to "a single server binary that embeds PHP, serves static files, terminates TLS and is configured over a JSON API", and what gap is left for FreeUnit?

### Takeaway

FrankenPHP is the closest. It embeds PHP in a Go and Caddy binary, serves static files, terminates TLS with an automatic local CA, exposes Caddy's JSON admin API, and since March 2026 runs natively on Windows. NGINX Unit, and so FreeUnit, is the only one that also runs several PHP versions side by side in one server and isolates each app in its own processes with its own user. FreeUnit lacks what local development needs on top: a local CA, host names, Windows, mail and a friendly front end.

### Cited Findings

| Property | FrankenPHP | NGINX Unit / FreeUnit | RoadRunner | Symfony CLI | Laravel Herd | PHP built-in server |
|---|---|---|---|---|---|---|
| PHP inside the server's own process | Yes, embed SAPI in the Go and Caddy process | Yes, PHP module inside per-app processes that Unit starts | No, php-cli worker processes | No, proxies to php-fpm, php-cgi or `php -S` | No, nginx plus php-fpm or php-cgi | Yes, CLI SAPI |
| Serves static files | Yes, `file_server` in `php_server` | Yes, `share` action | Yes, Static middleware | n/v | Yes, nginx | Yes, when the router returns false |
| TLS termination | Yes, Caddy | Yes, certificates uploaded over the API | Yes, "automatic TLS management" | Yes | Yes, nginx | No |
| Automatic local CA | Yes, Caddy local CA and `caddy trust` | No | n/v | Yes, mkcert fork | Yes, Herd's root CA | No |
| JSON configuration API | Yes, Caddy admin API on `localhost:2019` | Yes, control socket `/config` | No, `.rr.yaml` file | No | No public API; the Windows CLI uses an internal port 9005 | No |
| Several PHP versions in one server | No, one PHP per binary (inference) | Yes, several language modules at once | n/v | Yes, one server per project | Yes, `isolate` per site | No |
| Per-app process isolation (own user, namespaces) | No, threads in one process (inference) | Yes, `user`, `group`, `isolation` | Separate worker processes, no namespaces (n/v) | Separate processes per project | No, shared pools per version | No |
| Native Windows | Yes since v1.12.0 (2026-03-06), with gaps | No | Yes | Yes | Yes | Yes, single worker |

Sources: section 1 profiles for FrankenPHP and Caddy, RoadRunner, Symfony CLI, Herd, the PHP built-in server and NGINX Unit; [Unit docs: Configuration](https://unit.nginx.org/configuration/) and [Certificates](https://unit.nginx.org/certificates/), page read 2026-10-07; [Caddy API](https://caddyserver.com/docs/api), page read 2026-10-07.

- Closest precedent for packaging: FrankenPHP static binaries use static-php-cli, as did Herd at launch and the Drupal CMS Launcher. Sources: section 1.
- Closest precedent for a JSON-driven local tool: Caddy's admin API applies config changes "gracefully with zero downtime" and rolls back on failure. Source: [Caddy docs: API](https://caddyserver.com/docs/api), page read 2026-10-07.
- Closest precedent for a Drupal-facing Docker-free product: the Launcher, which is limited to `php -S`, SQLite, one site and no HTTPS. Source: section 1.

### Inferences

- Inference: the gap FreeUnit can fill is "Herd's multi-version, multi-site model in one daemon, configured over an API". Herd needs nginx plus one PHP-FPM or php-cgi pool per version and a GUI that writes nginx files. FrankenPHP needs one binary per PHP version. FreeUnit already loads several PHP modules in one server, gives each site its own processes, user and limits, and changes config live over JSON. That fits a GUI or CLI front end directly.
- Inference: what freeunit4drupal must build around FreeUnit: (1) a local CA and trust installation, which Unit does not have (Caddy's and mkcert's designs are the models); (2) host names, through a privileged helper (dnsmasq or the hosts file) or a PAC proxy; (3) prebuilt PHP modules for each supported PHP version, probably with static-php-cli or official builds; (4) mail capture and optional databases; (5) Drupal routing rules expressed as Unit routes; (6) a Windows answer, most simply WSL2, since Unit has no Windows port.
- Inference: FrankenPHP is both the main competitor and a possible fallback runtime on Windows. Its static builds cannot load Xdebug, which matters for a development tool.

### Gaps

- FreeUnit's own Windows status and any FreeUnit-specific features beyond Unit were not checked in this research.
- RoadRunner's static-file and multi-version behaviour were not verified in detail.
- No benchmark compares these servers for Drupal locally.

## 5. Business models: free, freemium, sponsored, vendor-backed. What worked?

### Takeaway

Freemium backed by the framework vendor works best today: Herd is free, sells Pro at EUR 99 per year, and is the default in Laravel's docs. Non-profit sponsorship keeps DDEV alive but at 84.5% of its goal and exposed to one sponsor's cuts. Vendor-funded free tools die when the vendor's strategy changes. Single-maintainer free tools stall or switch to paid licences.

### Cited Findings

| Tool | Model | Money facts | Owner or backer | Outcome on 2026-10-07 | Sources |
|---|---|---|---|---|---|
| Laravel Herd | Freemium | Pro EUR 99 per year; Teams EUR 299 per year for 10 licences | Beyond Code with Laravel | Alive; default in Laravel docs | https://herd.laravel.com/ ; https://github.com/laravel/docs/commit/e82816b331 |
| ServBay | Freemium | Pro $9.90 per month or $59 per year; Team $39.90 per month or $399 per year | ServBay | Alive | https://www.servbay.com/pricing |
| MAMP / MAMP PRO | Free plus paid PRO | Price not verified | MAMP | Alive on macOS; Windows lagging | https://www.mamp.info/en/downloads/ |
| Laragon | Paid licence since 7.x; free non-commercial use with pop-ups | $10 to $399 | Leo Khoa | Alive; licence friction | https://laragon.org/pricing ; https://github.com/leokhoa/laragon |
| Laravel Valet | Free, open source | None | Laravel | Alive; docs point to Herd | https://laravel.com/docs/12.x/valet |
| Symfony CLI | Free, open source (AGPL-3.0) | None | Symfony; Upsun commands built in | Alive | https://github.com/symfony-cli/symfony-cli |
| XAMPP | Free | None | Apache Friends | Stale since 2023-11-21 | https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/ |
| WampServer | Free | None found | One maintainer | Alive | https://wampserver.aviatechno.net/ |
| FlyEnv | Free, open source (BSD-3-Clause) | None found | Individual maintainer | Alive | https://github.com/xpf0000/FlyEnv |
| Drupal CMS Launcher | Free, open source (MIT) | None | Drupal Association | Alive; no release since 2026-03-23 | https://github.com/drupal/cms-launcher |
| DDEV | Non-profit, sponsorship | $10,134 of a $12,000 monthly goal (84.5%); 210 GitHub sponsors; Upsun cut to $1,000 per month from January 2026; developer payouts $8,000 to $10,000 per month in 2025 | DDEV Foundation, a US 501(c)(3) | Alive; Drupal's recommended tool | https://ddev.com/sponsor ; https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/ |
| Lando | Open source plus sponsorship | GitHub Sponsors goal $5,000 per month, 4% funded, 22 sponsors | Lando team | Alive on v3; v4 unstable since 2023 | https://github.com/sponsors/lando ; https://github.com/lando/core/releases |
| Docksal | Open source | None found | Community | Stalled since 2024-05-24 | https://github.com/docksal/docksal |
| Acquia Dev Desktop | Vendor-funded free tool | None | Acquia | Dead 2021-06-30 | https://docs.acquia.com/service-offerings/software-end-life-schedule |
| Pantheon Localdev | Vendor-funded free tool | None | Pantheon | Dead June 2023 | https://docs.pantheon.io/release-notes/2023/02/localdev |
| Bitnami native installers | Vendor-funded free tool | None | Bitnami | Dead 2022-11-30 | https://blog.bitnami.com/2022/11/focusing-on-cloud-native-solutions.html |
| Drupal VM | Single-maintainer open source | None | Jeff Geerling | Deprecated, archived | https://github.com/geerlingguy/drupal-vm |
| FrankenPHP | Open source, foundation-hosted | None found | PHP Foundation (since 2025-05-15) and its maintainers | Alive, growing | https://thephp.foundation/blog/2025/05/15/frankenphp/ |
| static-php-cli | Open source plus sponsorship | 2 public GitHub sponsors | crazywhalecc | Alive; a dependency of Herd (2023), the Launcher and FrankenPHP static builds | https://github.com/crazywhalecc/static-php-cli |
| NGINX Unit | Corporate open source | None | F5 | Archived (2025-10) | https://github.com/nginx/unit |

- DDEV's sponsor tiers are $25, $100, $500 and $1,000 per month, and DDEV claims "over 30,000 active developers". Source: [ddev.com/sponsor](https://ddev.com/sponsor), page read 2026-10-07.
- Upsun "provided high-level sponsorship for a couple of years", reduced it in 2025, and pays $1,000 per month from January 2026. Source: [DDEV blog: Upsun thank you, new sponsors needed](https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/), 2025-12-08.

### Inferences

- Inference: Herd's model works because the free tier is the product most people need (PHP, nginx, DNS, TLS), the paid tier sells time savers that teams value (services, mail, dumps, Xdebug on demand), and the framework's own docs act as the distribution channel.
- Inference: DDEV shows that a sponsored non-profit can fund about two maintainers, but one large sponsor leaving moved it from about 70% to an expected 53% of goal before the community filled the gap. Diversify sponsors early.
- Inference: Lando's sponsorship goal at 4% and a Lando 4 that has been "unstable" since 2023 suggest that a vendor-adjacent tool without a paid tier struggles once its main integrations (Pantheon, Acquia) stop paying for development. This is not confirmed by Lando's maintainers.
- Inference: for freeunit4drupal, a mix fits the evidence: a free core (FreeUnit plus PHP builds plus CA plus names), a paid or sponsored layer for services and team features, and no dependency on a single hosting vendor. Getting into the Drupal docs and the Launcher's alternatives list is the distribution channel that Laravel's docs gave Herd.

### Gaps

- No revenue or user numbers for Herd Pro, ServBay, MAMP PRO or Laragon were found.
- Lando's funding history and its relationship with Pantheon and Acquia were not verified from primary sources.
- WampServer's and FlyEnv's funding (donations or sponsors) were not checked.
