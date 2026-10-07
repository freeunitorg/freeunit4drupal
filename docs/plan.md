# freeunit4drupal project plan

Status: plan, 2026-10-07. No code exists yet.
Evidence: docs/research-report.md (cited as "Report §N") and the five notes in research/ (cited by file name and section).
Working name: freeunit4drupal. The final name is decision D16 and an open decision for the maintainer (section 10).

Status words used in this plan:

- VERIFIED: read in code or documentation at a named revision.
- MEASURED: reproduced, with the conditions written down.
- UNKNOWN or unverified: not checked. Phase 0 or a later milestone checks it.
- Estimate (judgement): a size or date set by this plan, with its basis stated.

## 1. Recommendation

Conditional go. Build freeunit4drupal as a Docker-free local Drupal environment for Linux and macOS. It is one Go binary that supervises one FreeUnit instance per user. It ships matched PHP 8.3, 8.4 and 8.5 builds and serves `<project>.localhost` over HTTP and HTTPS. It uses a local MariaDB or MySQL server, or SQLite, and captures mail with Mailpit. It reads `.ddev/` configuration live, so teams can use it beside DDEV. Windows users run the Linux build inside WSL2 until FreeUnit runs natively on Windows. The condition has three parts:

1. Phase 0 experiments E1, E2 and E3 pass by 2026-10-30, or the maintainers accept their fallbacks. E1 is the PHP build, E2 is two PHP minor versions in one server, and E3 is the isolation of a broken project.
2. Two named maintainers commit to the work through the beta by 2026-10-30, at the M0 gate.
3. The macOS milestone (M6) starts only after FreeUnit tags a release that contains the Apple Silicon fix (1.37.1 or later).

If part 1 fails without an accepted fallback, or part 2 is not met by 2026-10-30, do not start the tool. Instead, contribute the shared Drupal route template to FreeUnit and DrupalCI. Optionally, also build the DDEV add-on that runs FreeUnit (track T1).

Three facts decide it:

1. **FreeUnit already does the server work.** FreeUnit 1.37 serves Drupal's routes, loads several PHP versions in one server (verified for the 1.37.0 Debian packages and by code reading; E2 tests the tool's own builds), and gives each project its own processes and OPcache. It selects TLS bundles by SNI and reconfigures live over a JSON API on a unix socket. What is missing is outside the request path: packages for macOS and most Linux distributions, supervision of databases and mail, and a release that works on Apple Silicon. A tool can own all three. (Report §3; research/freeunit-capabilities.md §1, §9)
2. **No tool serves the Drupal developer who works without Docker.** DDEV needs a container runtime in every mode, including all three Windows modes. The Drupal CMS Launcher stops at evaluation: SQLite only, one site, no HTTPS, and no release since 2026-03-23. Drupack is an alpha with 4 stars. Herd supports Drupal on macOS and Windows, but it is built for Laravel first, sells databases, mail capture and per-request Xdebug in a paid tier, and has no Linux version. DDEV's own reported failures sit in the Docker layers that a native process removes. (Report §1, §2)
3. **The cost is a build pipeline and maintainers, not server code.** The project will ship PHP and FreeUnit binaries for Linux x86_64, Linux arm64 and macOS arm64, and it must patch them within days of each PHP security release. Tools with one or two maintainers stalled (Drupal VM, Docksal). DDEV runs on two paid maintainers and had USD 114,757 of income in 2025. Native Windows needs a FreeUnit port estimated at 10,000 to 18,500 new lines of C, in companion research that is unpublished on 2026-10-07. (Report §3, §5)

## 2. Goals, non-goals, target users and success metrics

### 2.1 Goals

- G1. One command starts a Drupal 10.4+, 11 or 12 project without Docker on Linux x86_64, Linux arm64, macOS arm64 and WSL2.
- G2. Local behaviour matches a FreeUnit production server: the same server, the same Drupal route set, and the same PHP build for web requests and the CLI.
- G3. Teams can mix tools. A project with `.ddev/config.yaml` starts without edits, and DDEV still works on the same checkout.
- G4. The tool changes the system only with consent, and one `clean --all` reverses every change.
- G5. Every claim about speed or resource use is measured against DDEV on the same machine and published with its baseline.
- G6. Small FreeUnit problems are fixed upstream, not hidden by workarounds in the tool.

### 2.2 Non-goals (until 1.0 unless stated)

- N1. Replacing DDEV for its 27 project types. Only Drupal 10.4 and later is in scope.
- N2. Drupal 7 and older, and Backdrop.
- N3. Native Windows before FreeUnit runs on Windows (milestone M11).
- N4. Docker Compose add-ons and custom nginx or Apache files from `.ddev/`. The importer reports them.
- N5. A graphical interface. Revisit after the beta, together with the Launcher's maintainers.
- N6. Any speed claim.
- N7. Machine-wide or multi-user instances.
- N8. Pull and push integrations with hosting providers (DDEV's `ddev pull`).
- N9. Telemetry by default.
- N10. HTTP/2 before FreeUnit merges it (section 7 item 18).
- N11. macOS x86_64. Only 5.3% of DDEV's macOS users are on Intel (94.7% use Apple Silicon).
- N12. Drupal multisite. The tool writes settings for `sites/default` only. Package Manager does not support multisite installations either (research/drupal-developer-requirements.md, Settings section).

### 2.3 Target users

Sizes come from DDEV's seven-day environment chart on 2026-10-06: 24,562 users of all project types ([DDEV usage statistics](https://ddev.com/usage-stats/)). Drupal is 37.6% of DDEV's project-type entries, so the Drupal-only sizes are smaller by an unknown factor (unverified). Report §1 explains why this base is used.

| ID | Segment | Size | Why they would switch | Served from |
|---|---|---|---|---|
| U1 | macOS developers on DDEV | 13,072 users (53.2%); 94.7% on Apple Silicon; 7,297 on Docker Desktop, 4,293 on OrbStack | No VM, no Mutagen sync, no paid Docker Desktop seat (needed in companies with 250 or more employees or USD 10 million in revenue) and no OrbStack business licence | M6, after FreeUnit 1.37.1 |
| U2 | Linux developers on DDEV | 5,292 users (21.5%); 95.5% on Docker CE | Fewer layers; the same server as FreeUnit in production | M2 |
| U3 | Windows developers in WSL2 | 5,266 users (21.4%): 4,717 NAT mode, 542 mirrored, 7 VirtioProxy | WSL2 without Docker CE or Docker Desktop (35.1% of WSL2 users run Docker Desktop) | M2 (Linux build in WSL2), documented in M8 |
| U4 | Teams that run FreeUnit in production | Unknown; FreeUnit's repository has 57 stars | Parity between development and production | M2 |
| U5 | Evaluators and site builders on the Drupal CMS Launcher | 13,181 installer downloads since 2025-02, 8,500 of them for Windows; an upper bound on people, because auto-updates count | Several sites, MariaDB, HTTPS, mail | Partly M8 (macOS, Linux); mostly M11 (Windows) |
| U6 | Organisations that forbid Docker | Unknown | No container runtime at all | M2 and M6 |
| none | Traditional Windows without WSL2 | 915 users (3.7%) | Not served until FreeUnit runs on Windows | M11 |

### 2.4 Success metrics

These are targets set by this plan, not facts.

| ID | Metric | Target | Date |
|---|---|---|---|
| S1 | Phase 0 results recorded in docs/phase0/, each with a status word, versions and OS | 17 of 17 experiments | 2026-10-30 |
| S2 | The Linux alpha installs and serves Drupal 10.6, 11.4 and 12.0 on SQLite (12.0.0 if released, otherwise the newest pre-release) | 3 of 3 majors | 2026-12-18 |
| S3 | The beta runs on Linux x86_64, Linux arm64 and macOS arm64 with MariaDB or MySQL, TLS, Mailpit and DDEV import | All listed features | 2027-03-12 |
| S4 | DDEV import corpus (E11, 50 public Drupal repositories): the front page returns 200 and `drush status` succeeds without edits | 40 of 50 (80%) | 2027-03-12 |
| S5 | Drupal core's PHPUnit Unit and Kernel suites in full, plus the Functional tests of the `system`, `node` and `user` modules, on 11.4 and 12.0: the same pass and fail set as DDEV on the same commit | 100% match | 2027-03-12 |
| S6 | PHP patch releases for 8.3, 8.4 and 8.5 published as runtime sets | Within 7 days of the php.net release, every time | From the beta |
| S7 | A resource and start-time report against DDEV on the same machine (E12 method) | Published with every minor release | From the beta |
| S8 | Listings: a child page in drupal.org "Local server setup", an issue in the Official Docs queue, a pull request to the Launcher's README | 3 of 3 submitted | 2027-04-30 |
| S9 | Contributors other than the two maintainers with merged pull requests | 3 | 2027-06-30 |
| S10 | Downloads of the tool binary from GitHub Releases, excluding checksum files | 1,000 | 2027-06-30 |
| S11 | FreeUnit items 1, 2, 3, 4 and 6 of section 7 merged or released (item 5, schedules, is already scheduled for 1.38.0) | 3 of 5 | 2027-03-12, or the FreeUnit 1.38.0 release if that comes first |
| S12 | 1.0 release | Released | 2027-10-15 |

Basis for S10 (judgement): the Launcher reached 13,181 installer downloads in about 20 months with promotion from the Drupal Association, and lerd reached 1,373 stars in about 7 months. A developer CLI without that promotion should aim lower.

## 3. Decisions

Each decision states the choice, the rejected alternatives, the evidence and the risk. A decision changes only in this file, with the date and the experiment or fact that changed it.

### D1. Language and shape

- Chosen: Go (current stable release) with `CGO_ENABLED=0`, one static binary per OS and CPU, cross-built with goreleaser. Reuse mkcert's CA and trust-store code (BSD-style licence; mkcert is 1,381 lines in total, and its CA and trust-store files are 988). Reuse DDEV's hosts-file elevation helper (Apache-2.0, 308 lines) and DDEV's configuration schema. Write a small FreeUnit control-API client over a unix socket. Project hooks and custom commands run as shell scripts or with the project's own PHP, so Drupal developers can extend the tool without Go.
- Rejected:
  - Rust, reusing `unitctl`: its OpenAPI client is generated at build time with Java, it carries Docker and process-scanning dependencies, it needs Rust 1.95, and it ships no Windows binary.
  - A PHP phar: it cannot install the PHP it runs on.
  - A static-php-cli `micro` binary: possible, but a long-running supervisor with signal handling fits Go better.
  - An Electron interface: non-goal N5.
- Evidence: Report §4 and §6 (line counts); research/tool-architecture-options.md §1.
- Risk: a third language next to FreeUnit's C and Rust. Go contributors come from DDEV and Symfony CLI, not from Drupal module authors. The shell and PHP extension points reduce this. The FreeUnit maintainers' preference is unknown; ask on the FreeUnit tracker before the first commit.

### D2. Instance model

- Chosen: one unitd per user for all projects, started and supervised by the tool's daemon (D9). Each project is one FreeUnit application, plus an Xdebug twin while debugging (D12). The tool renders the whole configuration from all project files and applies it with one `PUT /config` (D11). Every application gets `"spare": 0`, so applying a configuration never waits for a PHP start.
- Rejected:
  - One unitd per project: each one needs its own ports, and cookies do not isolate by port ([RFC 6265 §8.5](https://www.rfc-editor.org/rfc/rfc6265#section-8.5)).
  - A machine-wide root instance: it needs root and touches other users' files.
- Fallbacks:
  - If E2 fails, run one unitd per PHP minor version, each with its own port pair.
  - If E3 shows that one broken project blocks a shared `PUT` even with `"spare": 0`, validate each project in a short-lived scratch unitd before the real `PUT`, and file section 7 item 16.
- Evidence: Report §4; research/tool-architecture-options.md §5; research/freeunit-capabilities.md §1 and §8.
- Risk: a crash of the shared unitd stops every project. The daemon restarts it with back-off (D9).

### D3. Host names

- Chosen: every project answers on `<name>.localhost` and `*.<name>.localhost`. Extra names from `hostnames:` become `<h>.<name>.localhost`. Imported DDEV projects also answer on `<name>.ddev.site`, on `<h>.ddev.site` for each `additional_hostnames` entry (public DNS), on `<h>.<name>.localhost` for the same entries, and on each `additional_fqdns` name through a hosts entry. Names outside `.localhost` resolve through public DNS (`*.ddev.site` points to 127.0.0.1) or through hosts-file entries that the user approves with `hosts add`. A small elevated helper, derived from DDEV's `ddev-hostname`, writes them, and `clean` removes them. On macOS 15 and older, `start` offers the hosts entry for Safari users.
- Rejected:
  - dnsmasq with `/etc/resolver`: needs root and port 53, and a macOS 26.3.1 report shows resolver files being ignored.
  - A public wildcard domain run by the project: fails offline and behind DNS-rebinding filters, as DDEV documents for `*.ddev.site`.
  - A PAC proxy: needs OS proxy settings, and CLI tools need `https_proxy`.
  - `.test` and `.local`: `.test` needs a root DNS setup, and `.local` collides with mDNS.
- Evidence: Report §4; research/tool-architecture-options.md §3; research/drupal-developer-requirements.md (Hostnames and TLS).
- Risk: OS resolvers used by PHP stream wrappers on macOS 15 and on Windows are unverified (E9). Guzzle with libcurl 7.85 or later resolves `*.localhost` itself.

### D4. Ports

- Chosen: listeners on 127.0.0.1 and, when the system has an IPv6 loopback, on ::1 (`[::1]:8080` and `[::1]:8443`). Chromium answers `*.localhost` with ::1 and 127.0.0.1, and curl tried ::1 first in the research's measurement (research/tool-architecture-options.md §3); E9 records the order per client. The default is HTTP on 8080 and HTTPS on 8443. If either is busy, the tool tries the next pair (8081 and 8444, and so on, ten times), then asks, and remembers the result in the global configuration. `ports privileged` is an opt-in:
  - Linux: `setcap cap_net_bind_service=+ep` on the tool's own unitd binary (one sudo, applied again after every update, and the module RUNPATH from D6 is required), then 127.0.0.1:80 and 127.0.0.1:443.
  - macOS: `*:80` and `*:443`, with a first route step that returns 403 to any `source` outside 127.0.0.0/8 and ::1. This is offered only if E6 passes.
  - WSL2: the bind address that E9 shows to be reachable from Windows browsers.
  - In every case, `ports privileged` first checks that 80 and 443 are free and stops with a message if they are not. DDEV's router holds both while any DDEV project runs.
- Rejected as the default:
  - Ports 80 and 443: they need privilege, `setcap` causes secure-execution mode and a non-dumpable unitd, and the macOS wildcard bind is reachable from the local network.
  - A root launchd daemon (Valet's model): a root service for a development tool.
  - Changing `net.ipv4.ip_unprivileged_port_start`: a system-wide change.
- Evidence: Report §4 and §6; research/tool-architecture-options.md §5; research/freeunit-capabilities.md §6; [XNU in_pcb.c](https://github.com/apple-oss-distributions/xnu/blob/f6217f891ac0bb64f3d375211650a4c1ff8ca1ea/bsd/netinet/in_pcb.c#L989-L1000).
- Risk: URLs carry a port, and OAuth providers that register exact redirect URIs need it. Ports 8080 and 8443 are often taken; the fallback search handles that.

### D5. TLS

- Chosen: `trust` creates a tool CA (ECDSA P-256, valid 10 years, key file mode 0600), or reuses an existing mkcert CA when its root is already trusted (`CAROOT`). `trust` installs the root in:
  - the macOS System keychain;
  - the Linux anchor directory, followed by the distribution's update command;
  - NSS databases, when `certutil` exists;
  - in WSL2, the Windows current-user Root store (method unverified, E10);
  - `<data>/ca/bundle.pem`, the bundle that the bundled PHP reads (D11).
- Each project gets one leaf: ECDSA P-256, a SAN for every project name, 396 days, renewed by `start` when fewer than 30 days remain. The tool uploads it with `PUT /certificates/<project>` and lists it in the HTTPS listener's `certificate` array (SNI). HTTPS is on by default after `trust`, and HTTP always works. CI mode skips TLS.
- Rejected:
  - Shipping the mkcert binary: an extra dependency (DDEV ships a signed copy on Windows).
  - One `*.localhost` leaf: many browsers reject second-level wildcards.
  - No TLS by default: Drupal's `SSESS` cookie and `Secure` flag differ over HTTPS, and OAuth providers need HTTPS for names other than localhost.
- Evidence: Report §4; research/tool-architecture-options.md §4; research/freeunit-capabilities.md §5.
- Risk: the CA key can intercept any HTTPS connection from the machine. `clean --all` removes the root from every store. Firefox behaviour without an NSS import is unverified (E10).

### D6. PHP provisioning

- Chosen: the tool ships runtime sets, one per PHP minor version, OS and CPU, built in one static-php-cli 2.8.5 run with:
  - `--build-cli --build-embed`;
  - `SPC_CMD_VAR_PHP_EMBED_TYPE=shared`;
  - `SPC_TARGET=native-native-gnu.2.17` on Linux;
  - Xdebug as a shared extension.
- Each set holds:
  - `bin/php`;
  - `lib/libphp-8.x.so` (Linux) or `lib/libphp-8.x.dylib` (macOS): the shared library, with a versioned SONAME or install name, so that two sets never share the name `libphp.so`;
  - `modules/php8.x.unit.so`, built with FreeUnit's `./configure php --config=<set>/bin/php-config --lib-path=<set>/lib`. FreeUnit writes an absolute rpath into the build tree (auto/modules/php lines 112 to 114 at 872bf041), so the pipeline rewrites it after the build to `$ORIGIN/../lib` (Linux, with `patchelf`) or `@loader_path/../lib` (macOS, with `install_name_tool`);
  - `lib/php/extensions/xdebug.so`;
  - `bin/php-config`.
- On install, the tool copies each library into `<data>/lib/` and each module into `<data>/modules/`, the unitd modules directory (section 4.4). It never symlinks a module. glibc resolves `$ORIGIN` from the path given to `dlopen()`, so a module opened through a symlink looks for `libphp` beside the link. In a test for this plan (glibc 2.43, two small test libraries, 2026-10-07), that lookup missed, and glibc then bound the module to the system's own `/lib/libphp.so`, a different PHP build. With the versioned SONAME that this decision sets, the same test fails instead with "libphp-8.4.so: cannot open shared object file" (also measured on 2026-10-07). E1 repeats both tests with real builds.
- Extensions in every set:
  - Drupal core's 14 required extensions: date, dom, filter, gd, hash, json, pcre, pdo, session, SimpleXML, SPL, tokenizer, xml and zlib.
  - OPcache, APCu, intl, mbstring, pdo_mysql, mysqli, pdo_sqlite and sqlite3 (SQLite 3.45 or newer).
  - zip, curl, openssl, sodium, bcmath, xmlwriter, ctype, iconv, phar, posix, pcntl (CLI) and argon2 password hashing.
  - Later: imagick, redis, memcached, pdo_pgsql, uploadprogress and xhprof.
- A "bring your own" mode accepts a unitd binary, a modules directory that holds `php8.x.unit.so` built against the user's PHP (for example `/usr/lib/freeunit/modules` from the `freeunit-php8.x` Debian packages), and the matching `php` and `php-config`. It serves users of Debian, Sury or Remi packages and distribution packagers.
- Rejected:
  - Homebrew's `php`: it has no embed SAPI.
  - FreeUnit's Homebrew tap: it still builds NGINX Unit 1.34.2.
  - Distribution packages as the only path: they do not exist on macOS, and every distribution needs its own module build.
  - Fully static musl builds: they cannot load Xdebug.
- Fallback if E1 fails:
  - Linux: distribution packages (`libphp8.x-embed`, as FreeUnit's 1.37.0 Debian packages use).
  - macOS: a static embed linked into the module with `--lib-static`, and Xdebug compiled in with `xdebug.mode=off` by default (unverified; second branch of E1). Package Manager then warns that Xdebug is loaded, which is acceptable only for a fallback.
- Evidence: Report §3, §4 and §6; research/tool-architecture-options.md §6; research/drupal-developer-requirements.md (The PHP CLI dependency).
- Risk: the project owns PHP security updates (metric S6), so M1 includes a rebuild drill. musl distributions such as Alpine are not supported.

### D7. PHP and Drupal support matrix

- Chosen: PHP 8.3, 8.4 and 8.5.
  - Drupal 10.4 to 10.6 on PHP 8.3 or 8.4, best effort after 2026-12-09.
  - Drupal 11.3 and later on PHP 8.3 to 8.5. The security-supported Drupal 11 lines on 2026-10-07 are 11.3.x and 11.4.x.
  - Drupal 12 on PHP 8.5 only.
- The tool reads `core/lib/Drupal.php` to pick defaults: Drupal 12 gets PHP 8.5, and Drupal 11 gets 8.4 unless the project pins a version.
- The tool's own database floor for every major is MariaDB 10.11 or MySQL 8.0, which is Drupal 12's floor; Drupal 10 and 11 accept older versions. SQLite needs 3.45 for Drupal 11 and 12. When PostgreSQL arrives (M9): PostgreSQL 16 for Drupal 11 and 18 for Drupal 12.
- Drush always comes from the project's `vendor/bin`.
- Rejected: PHP 8.1 and 8.2, which serve only Drupal 10; a global Drush.
- Evidence: Report §4 and §6; research/drupal-developer-requirements.md (Platform requirements).
- Risk: Drupal 12.0.0 may slip from the week of 2026-12-07. The alpha then tests the newest pre-release.

### D8. Databases and services

- Chosen:
  - MariaDB or MySQL from a server that is already installed (Homebrew `mariadb@11.8`, a formula in homebrew-core on 2026-10-07, or `mysql`, a distribution package, or a configured path). The tool checks the version against the D7 floors.
  - Each project gets its own data directory under the tool's data directory, a free port above 3306 that the tool remembers, and a unix socket. The tool initialises the directory with `mariadb-install-db --auth-root-authentication-method=normal --skip-test-db` or `mysqld --initialize-insecure`.
  - Database `db`, user `db` and password `db`, which is DDEV's convention, so imported scripts keep working.
  - SQLite mode for new evaluator projects, chosen at `init`, with the file `sites/default/files/.ht.sqlite`.
  - Mailpit, one per user: SMTP on 127.0.0.1:1025 and the web UI on 127.0.0.1:8027, each moved to the next free port with the D4 search when busy. The UI avoids 8025 and 8026, which DDEV's router publishes for Mailpit while any DDEV project runs (research/ddev-incumbent.md §2). `sendmail_path` points at `mailpit sendmail`, and the generated settings point Symfony Mailer transports at Mailpit.
  - Later (M9): PostgreSQL from the theseus-rs portable builds; Valkey or Redis from packages; Node through fnm; and an optional Docker hybrid that starts only Solr, Elasticsearch or OpenSearch from `.ddev/docker-compose.*.yaml`.
- Rejected:
  - FreeUnit `external` applications as a service supervisor: they need libunit's READY message.
  - One shared database server for all projects: it allows no per-project versions, and snapshots work per server.
  - Downloading MariaDB in the first release: MariaDB publishes no macOS binary, and its Linux binary covers x86_64 only.
- Evidence: Report §4; research/tool-architecture-options.md §7 and §8; research/drupal-developer-requirements.md (Services beyond PHP).
- Risk: the host's server version may not match a DDEV snapshot (E8). One `mariadbd` per running project costs memory (measured in E12).

### D9. Supervision

- Chosen: `freeunit4drupal daemon`, one per user. Any command that needs it starts it, and it listens on a unix socket for the CLI (`daemon.sock`, mode 0600, in a `<run>` directory with mode 0700). It:
  - owns unitd (`--no-daemon`), one `mariadbd` per running project, Mailpit, and a cron ticker;
  - restarts each child with exponential back-off;
  - writes one log per service;
  - stops everything on `poweroff`.
- `autostart on` installs a launchd agent (macOS) or a systemd user unit (Linux) that runs the same daemon.
- Cron: the generated settings set automated_cron's interval to 0. The ticker runs `drush cron` every 300 seconds (configurable) for each running project. The tool switches to a FreeUnit schedule when schedules ship in FreeUnit 1.38.0 ([pull request 452](https://github.com/freeunitorg/freeunit/pull/452)).
- Rejected:
  - FreeUnit as a general supervisor: it cannot supervise processes that do not speak libunit.
  - Separate systemd or launchd units per service: platform-specific, absent in CI and in some WSL2 setups, and harder to clean up.
  - Windows services: they need administrator rights (M11 decides).
- Evidence: Report §3 and §4; research/tool-architecture-options.md §7 and §8; research/freeunit-capabilities.md §7 and §12.
- Risk: the daemon is a new single point of failure. `diagnose` checks it, and `start` restarts it.

### D10. Configuration model, Drupal settings and DDEV import

- Chosen, configuration:
  - The native file is `.freeunit4drupal/config.yaml` (committed), plus `.freeunit4drupal/config.local.yaml` (gitignored, merged last).
  - The schema follows research/tool-architecture-options.md §2: name, type, docroot, php, database, hostnames, fqdns, https, composer, timezone, environment, php_ini, upload_dirs, direct_php, xdebug, hooks and cron.
  - Without a native file but with `.ddev/config.yaml`, the tool reads `.ddev/config*.yaml` live with DDEV's merge rules (lexicographic order, `override_config`). It maps the 52 schema fields as the mapping table in that section describes, and prints unsupported items once.
  - `init --from-ddev` writes a native file for teams that leave DDEV.
- Chosen, settings in native mode: on `init`, the tool adds this stanza once to `settings.php`, and it regenerates `settings.freeunit4drupal.php` (gitignored, with a signature line) on every `start`:

  ```php
  // Added by freeunit4drupal. Loads local settings only when the tool serves this site.
  $freeunit4drupal_settings = __DIR__ . '/settings.freeunit4drupal.php';
  if (getenv('FREEUNIT4DRUPAL_PROJECT') === 'true' && is_readable($freeunit4drupal_settings)) {
    require $freeunit4drupal_settings;
  }
  ```

- Chosen, settings in DDEV mode: the tool sets `IS_DDEV_PROJECT=true`, `DDEV_PRIMARY_URL` and `DRUSH_OPTIONS_URI` in the FreeUnit application and in the CLI environment. It regenerates `settings.ddev.php` only while that file carries `#ddev-generated`.
- Both generated files set:
  - `$databases` and a stable `hash_salt`;
  - exact `trusted_host_patterns`: every name the project serves, plus the tunnel name during `share`;
  - `config_sync_directory`, using DDEV's default `sites/default/files/sync` when it is empty;
  - `file_private_path` set to `<project>/private` (created by `init` and gitignored) and `file_temp_path` set to `<data>/projects/<name>/tmp`, both outside the web root;
  - `skip_permissions_hardening = TRUE`;
  - Mailpit transports and verbose error logging;
  - automated_cron's interval set to 0.
- Drupal removes write permission from `sites/default` and `settings.php` after installation unless `skip_permissions_hardening` is set (research/drupal-developer-requirements.md, file ownership section). `init` and `start` add write permission for the user before they write, and say so.
- `disable_settings_management: true` turns all of this off.
- Rejected:
  - A one-time conversion only: teams that keep DDEV would see the copies drift apart.
  - Setting `IS_DDEV_PROJECT` in native mode: native projects do not need the DDEV variable.
  - `trusted_host_patterns = ['.*']`: it accepts any host.
- Evidence: Report §4 and §6; research/tool-architecture-options.md §2; research/drupal-developer-requirements.md (Settings conventions).
- Risk: DDEV and the tool both rewrite `settings.ddev.php` if both serve the same checkout at once. When the `ddev` binary is present, `start` asks it whether the project is running and warns if it is. The docs say to run one tool at a time.

### D11. FreeUnit configuration generation

- Chosen: the tool owns the whole configuration. After any change (a project file, `.env`, an ini fragment, a start or a stop), it renders the configuration and applies it with one `PUT /config`.
- Routes come from one shared Drupal template derived from the DrupalCI `drupal.json` (dev branch, commit 4e0adb89):
  - every deny rule is a case-insensitive regular expression (`~(?i)...`);
  - PHP entry points are chosen per Drupal major (no `core/authorize.php` on Drupal 12);
  - the project's `direct_php` list adds entry points;
  - a final `share` falls back to `index.php`.
- The share sends no `Cache-Control` header in development. FreeUnit's ETag and Last-Modified validators handle caching.
- Defaults per application:
  - `processes`: `{"max": 8, "spare": 0, "idle_timeout": 60}`; SQLite projects get `max` 2 until E13.
  - `settings.http.max_body_size`: 1 GiB.
  - PHP: `memory_limit` 1024M, `max_execution_time` 600, `post_max_size` and `upload_max_filesize` 100M (DDEV's values).
  - OPcache: `opcache.revalidate_freq` 0 and `opcache.save_comments` 1.
  - `apc.shm_size` 64M (Drupal warns below 32M).
  - Errors: `log_errors` 1 with `error_log` in the project's log directory, and `display_errors` 1.
  - TLS from PHP: `openssl.cafile` and `curl.cainfo` point at `<data>/ca/bundle.pem`, which holds the system's CA certificates plus the tool CA. A static PHP may not find the system store, so without this, requests from PHP to `https://<name>.localhost` or to drupal.org could fail (unverified; E10 tests it).
- The CLI loads the same files: `PHPRC=<data>/projects/<name>`, so PHP reads that directory's `php.ini`, and the same `PHP_INI_SCAN_DIR`.
- `fastcgi_finish_request()` stays enabled, because FreeUnit 1.37.0 counts detached workers as busy. CI mode disables it, as the DrupalCI image does.
- Environment per application: the settings variable; `PHP_BINARY` and `PATH` pointing at the matching CLI, with `<data>/bin`, which holds the Composer that the tool downloads, also on `PATH`; `PHP_INI_SCAN_DIR`; `COMPOSER_HOME`; `DRUSH_OPTIONS_URI`; `TZ`; and the project's `web_environment` values.
- `settings.http.static.mime_types` adds `.wasm`, `.map` and `.webmanifest` until FreeUnit adds them itself.
- Rejected:
  - Patching per-project subtrees of the configuration: it leaves stale routes after a crash, while a full `PUT` is validated as one unit.
  - The howto's glob deny list: it is case-sensitive and has no private or temporary directory rules.
  - DDEV's nginx rule that runs any existing `.php` file: less strict than Drupal's `.htaccess`.
- Evidence: Report §3 and §4; research/freeunit-capabilities.md §1, §2, §4 and §8; research/drupal-developer-requirements.md (UNIT rows); research/tool-architecture-options.md §2.
- Risk: contributed modules with their own PHP entry points get 404 until they are listed in `direct_php`. `diagnose` reports 404 answers for `.php` paths from the access log.

### D12. Xdebug

- Chosen: a twin application `<project>-xdebug`. It has the same configuration as the project's application, plus:
  - its own ini file, which loads `xdebug.so` as a `zend_extension`;
  - `XDEBUG_MODE=debug,develop` in its environment;
  - `xdebug.client_port=9003` and `xdebug.start_with_request=yes` (the twin receives only requests meant for debugging);
  - `processes` `{"max": 2, "spare": 0, "idle_timeout": 300}`.
- `xdebug on` routes every request of the project to the twin. `xdebug trigger` routes only requests with an `XDEBUG_SESSION` or `XDEBUG_TRIGGER` cookie or query argument. `xdebug off` removes the routes and the twin.
- The main application's JSON never changes, so its workers and OPcache stay warm.
- `drush`, `php` and `composer` accept `--xdebug`, which sets `XDEBUG_MODE` for that run.
- Native paths need no IDE path mappings. In WSL2 with a Windows IDE, `xdebug.client_host` must point at the Windows host (unverified; documented in M8).
- Rejected:
  - `options.admin` with `xdebug.mode`: applied after Xdebug's module start (E5 tests this; basis: xdebug.c line 508 at 64007df3 and nxt_php_sapi.c lines 445 to 455 at 872bf041).
  - Swapping the main application's ini file: restarts its workers and loses OPcache.
  - Xdebug always loaded in `off` mode: Package Manager warns whenever Xdebug is loaded.
- Evidence: Report §4; research/freeunit-capabilities.md §2; research/tool-architecture-options.md §9; research/drupal-developer-requirements.md (Services beyond PHP, Xdebug).
- Risk: if E4 shows that a routes-only `PUT` restarts applications, `xdebug on` costs one cold start. That is acceptable.

### D13. CLI surface

- Chosen, commands for the beta:
  - Lifecycle: `init`, `start`, `stop`, `restart`, `poweroff`, `list`, `describe`, `launch`, `logs`.
  - Tools: `exec`, `php`, `composer`, `drush`, `dr` (runs the project's `vendor/bin/dr`, Drupal 11.4 and later), `mysql`.
  - Data: `import-db`, `export-db`, `import-files`, `snapshot` (create, list, restore).
  - Other: `xdebug on|trigger|off|status`, `share`, `trust`, `hosts add|remove`, `ports default|privileged`, `autostart on|off`, `runtime list|prune`, `diagnose`, `self-update`, `clean [--all]`, `version`.
- `exec` runs a host shell in the project root with the project environment. There is no `ssh`.
- The CLI environment also sets `SIMPLETEST_BASE_URL` (the project's HTTP URL), `SIMPLETEST_DB` (by default `sqlite://localhost/sites/default/files/db.sqlite`, as Drupal's contrib CI templates use) and `BROWSERTEST_OUTPUT_DIRECTORY` (`<docroot>/sites/simpletest/browser_output`), so PHPUnit Functional tests run without edits (S5; research/drupal-developer-requirements.md, TEST-01).
- Hooks: DDEV's 28 hook points. `exec` and `exec-host` tasks both run on the host. `composer` tasks run the project's Composer.
- DDEV custom commands: `.ddev/commands/host/*` run unchanged. `.ddev/commands/web/*` run on the host after a check of their `ProjectTypes` line, with `/var/www/html` translated to the project root. `diagnose` lists the ones that fail.
- CI mode (`--ci` or `CI=true`): no CA, no hosts entries and no prompts; HTTP on 127.0.0.1:8080; SQLite or a service database; a non-zero exit code when a hook fails. A GitHub Action wraps it.
- Update check: at most once a day, and it only prints a notice. Each check is a request to GitHub, so a global setting turns it off, and it is off when `CI=true`.
- Rejected: `ssh` into a container; `pull` and `push` to hosting providers (N8); a graphical interface (N5).
- Evidence: Report §4; research/tool-architecture-options.md §9; research/ddev-incumbent.md §2; [DDEV schema at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/schema.json).
- Risk: users expect imported projects to behave exactly as in DDEV. E11 measures the gap, and the docs list the differences.

### D14. Distribution

- Chosen:
  - goreleaser builds the tool for linux-amd64, linux-arm64 and darwin-arm64.
  - GitHub Releases carry `SHA256SUMS` and cosign signatures, with an install script and a Homebrew tap formula (a formula, not a cask).
  - macOS binaries are notarized once E15 shows that browser downloads need it (Apple Developer Program, USD 99 a year).
  - Runtime sets (D6) and unitd builds live in the same repository under a separate tag namespace, for example `runtime/php-8.4.13-1` and `runtime/freeunit-1.37.1-1`, as FreeUnit does with `unitctl/1.37.0`.
  - Each tool release pins one tested FreeUnit version and the newest runtime set per PHP minor version. The tool downloads them on first use and verifies checksums and signatures.
  - `php: "8.4"` in a project uses the newest installed 8.4 set, and `php: "8.4.13"` pins a patch version. `runtime list` shows the installed sets, and `runtime prune` deletes the sets that no project pins and that are not the newest for their minor version. DDEV's release notes ask users to delete old images after every upgrade for the same reason (research/ddev-incumbent.md §7).
  - After the beta: deb and rpm repositories. With native Windows: winget and Scoop, and signed binaries.
- Rejected:
  - Depending on FreeUnit's Homebrew tap or packages.freeunit.org: neither has FreeUnit packages today.
  - A Homebrew cask: Homebrew 5.0.0 announced that casks failing Gatekeeper checks would be disabled from September 2026.
- Evidence: Report §5; research/tool-architecture-options.md §10; research/freeunit-capabilities.md §9.
- Risk: signing identity and cost. Microsoft Artifact Signing accepts individuals only in the US and Canada, which matters for M11.

### D15. Telemetry

- Chosen: no telemetry in the alpha, the beta or 1.0. Adoption is read from GitHub release download counts, Homebrew analytics and a voluntary survey at DrupalCon Orlando. After 1.0, an opt-in prompt may be added. It is always off when `CI=true`.
- Rejected: DDEV's opt-in Amplitude model. DDEV uses about 45 million events a month against a free quota of 2 million, and the share of users who opt out is unknown.
- Evidence: research/ddev-incumbent.md §2 and §7; research/tool-architecture-options.md §9.
- Risk: less data on platform demand, for example for macOS x86_64 or PostgreSQL. The survey and the issue tracker cover it.

### D16. Name and organisation

- Chosen, pending the maintainer (section 10): keep the working name freeunit4drupal for the repository, the binary and the settings variable until the maintainer decides. Host the project under the freeunitorg GitHub organisation, which already holds FreeUnit, its docs and its SDK, so that releases share infrastructure and trust. Check the Drupal trademark policy for the use of "Drupal" in a project name before the beta (unverified).
- Rejected: a personal account for a project with several maintainers.
- Evidence: Report §2 (failures of single-vendor and single-maintainer tools).
- Risk: a rename after the beta breaks install scripts and the settings variable. Decide before M8.

### D17. Relationship with DDEV

- Chosen: complement DDEV, do not replace it.
  - Read `.ddev/` live (D10) and keep DDEV's names and snapshots usable.
  - Never take a port that a running DDEV holds. DDEV's router holds 80, 443, 8025 and 8026 while any DDEV project runs (research/ddev-incumbent.md §2). The tool's defaults avoid all four, and `ports privileged` checks first (D4).
  - Never write into `.ddev/`. The only DDEV file the tool writes is `settings.ddev.php` in the Drupal site directory, and only while it carries DDEV's signature.
  - Offer optional track T1: a DDEV add-on that runs FreeUnit as the web server through DDEV's `generic` web server type, following the official `ddev-frankenphp` add-on. It gives DDEV users FreeUnit parity inside Docker and tests the shared route template.
  - Contact DDEV's maintainers before the beta.
- Rejected: forking DDEV, whose core is the Docker API; competing feature for feature with its 27 project types and 303 add-ons.
- Evidence: Report §1 and §2; research/ddev-incumbent.md §1, §2 and §6.
- Risk: DDEV changes its configuration schema. The importer records the DDEV versions it understands and warns on newer schemas.

### D18. Positioning and claims

- Chosen: claim these things only:
  - no Docker;
  - one binary;
  - one FreeUnit configuration per user;
  - the same server as FreeUnit in production;
  - per-project PHP versions without containers;
  - DDEV import.
- Claim resource use only with E12 numbers and the DDEV baseline in the same sentence. Never claim speed.
- "The same server as FreeUnit in production" means the script-per-request mode. FreeUnit 1.38.0 plans a PHP worker mode that changes behaviour; the claim covers it only after section 7 item 20 tests Drupal under it.
- Evidence: Report §3; rule 2 of the findings repository's AGENTS.md ([andypost/freeunit-drupal](https://github.com/andypost/freeunit-drupal)).
- Risk: none if review enforces it (section 8, rule 4).

## 4. Architecture

The diagrams use two example projects: `example` (Drupal 11, PHP 8.4, MariaDB) and `shop` (Drupal 12, PHP 8.5, SQLite). `<data>` is the tool's data directory, `<run>` its runtime directory, and `<example>` the root of the `example` checkout.

### 4.1 The per-user instance

```
 Browser, curl, Guzzle, links that Drush builds
        |                                    |
 https://example.localhost:8443      http://shop.localhost:8080
        |                                    |
+-------v------------------------------------v--------------------------+
| unitd, one per user (a child of `freeunit4drupal daemon`)             |
|                                                                       |
| listeners   127.0.0.1:8080, [::1]:8080   HTTP                         |
|             127.0.0.1:8443, [::1]:8443   HTTPS, SNI "example", "shop" |
|                                                                       |
| routes/hosts      Host header -> routes/<project>                     |
|                   (routes/<project>-xdebug while debugging)           |
| routes/<project>  deny rules -> PHP entry points -> deny other .php   |
|                   -> share, with fallback to index.php                |
|                                                                       |
| applications                                                          |
|   "example"          php 8.4, prototype + OPcache, 0 to 8 workers     |
|   "example-xdebug"   php 8.4 + Xdebug, only while debugging           |
|   "shop"             php 8.5, prototype + OPcache, 0 to 2 workers     |
|                                                                       |
| control API   unix socket, mode 0600: PUT /config,                    |
|               PUT /certificates/<name>, GET /status,                  |
|               GET /control/applications/<name>/restart                |
+-----------+--------------------------+-----------------------+--------+
            |                          |                       |
  mariadbd for "example"     SQLite file for "shop"    Mailpit, one per user
  127.0.0.1:<port> and       web/sites/default/        SMTP 127.0.0.1:1025
  a unix socket              files/.ht.sqlite          UI   127.0.0.1:8027
```

### 4.2 A project's FreeUnit configuration

The tool generates the whole document. This sketch is shortened: the full deny lists come from the shared route template (D11), and comments mark the omitted parts. Experiment E17 tests the real template.

```jsonc
{
  "settings": {
    "http": {
      "max_body_size": 1073741824,
      "static": {
        "mime_types": {
          "application/wasm": [".wasm"],
          "application/json": [".map"],
          "application/manifest+json": [".webmanifest"]
        }
      }
    }
  },
  "listeners": {
    "127.0.0.1:8080": { "pass": "routes/hosts" },
    "[::1]:8080": { "pass": "routes/hosts" },
    "127.0.0.1:8443": {
      "pass": "routes/hosts",
      "tls": { "certificate": ["example", "shop"] }
    },
    "[::1]:8443": {
      "pass": "routes/hosts",
      "tls": { "certificate": ["example", "shop"] }
    }
  },
  "routes": {
    "hosts": [
      {
        // "xdebug trigger" mode. Further steps match the XDEBUG_TRIGGER cookie
        // and the XDEBUG_SESSION and XDEBUG_TRIGGER query arguments.
        "match": {
          "host": ["example.localhost", "*.example.localhost"],
          "cookies": { "XDEBUG_SESSION": "*" }
        },
        "action": { "pass": "routes/example-xdebug" }
      },
      {
        "match": { "host": ["example.localhost", "*.example.localhost"] },
        "action": { "pass": "routes/example" }
      },
      {
        "match": { "host": ["shop.localhost", "*.shop.localhost"] },
        "action": { "pass": "routes/shop" }
      },
      { "action": { "return": 404 } }
    ],
    "example": [
      {
        // Deny rules, case-insensitive. Shortened.
        "match": {
          "uri": [
            "~(?i)/\\.(?!well-known/)",
            "~(?i)^/(vendor|sites/[^/]+/(private|temp))(/|$)",
            "~(?i)\\.(engine|inc|install|make|module|po|profile|sh|sql|theme|twig|yml|sqlite)$",
            "~(?i)/(composer\\.(json|lock)|web\\.config)$"
          ]
        },
        "action": { "return": 404 }
      },
      {
        "match": { "uri": ["/index.php", "/index.php/*"] },
        "action": { "pass": "applications/example/index" }
      },
      {
        // Entry points for Drupal 11. The Drupal 12 template has no core/authorize.php.
        "match": {
          "uri": ["/update.php", "/update.php/*", "/core/install.php", "/core/install.php/*",
                  "/core/rebuild.php", "/core/authorize.php"]
        },
        "action": { "pass": "applications/example/direct" }
      },
      {
        "match": { "uri": "~(?i)\\.php(/|$)" },
        "action": { "return": 404 }
      },
      {
        // No Cache-Control in development; ETag and Last-Modified handle caching.
        "action": {
          "share": "<example>/web$uri",
          "fallback": { "pass": "applications/example/index" }
        }
      }
    ],
    "example-xdebug": [
      // The same steps as "example", passing to applications/example-xdebug.
    ],
    "shop": [
      // Generated from the same template for Drupal 12.
    ]
  },
  "applications": {
    "example": {
      "type": "php 8.4",
      "processes": { "max": 8, "spare": 0, "idle_timeout": 60 },
      "environment": {
        "FREEUNIT4DRUPAL_PROJECT": "true",
        "PHP_BINARY": "<data>/runtimes/php-8.4/bin/php",
        "PATH": "<data>/runtimes/php-8.4/bin:<data>/bin:<example>/vendor/bin:/usr/local/bin:/usr/bin:/bin",
        "PHP_INI_SCAN_DIR": ":<data>/projects/example/php.d",
        "COMPOSER_HOME": "<data>/composer",
        "DRUSH_OPTIONS_URI": "https://example.localhost:8443",
        "TZ": "Europe/Berlin"
      },
      "options": { "file": "<data>/projects/example/php.ini" },
      "targets": {
        "direct": { "root": "<example>/web/" },
        "index": { "root": "<example>/web/", "script": "index.php" }
      }
    },
    "example-xdebug": {
      // As "example", with these differences:
      //   "processes": { "max": 2, "spare": 0, "idle_timeout": 300 }
      //   "environment": { ..., "XDEBUG_MODE": "debug,develop" }
      //   "options": { "file": "<data>/projects/example/php-xdebug.ini" }
    },
    "shop": {
      "type": "php 8.5",
      "processes": { "max": 2, "spare": 0, "idle_timeout": 60 }
      // environment, options and targets as for "example"
    }
  }
}
```

### 4.3 The process tree with services

```
freeunit4drupal daemon                     one per user; started by any command that
|                                          needs it, or at login after `autostart on`
+-- unitd --no-daemon                      FreeUnit main process
|     --control unix:<run>/control.sock --pid <run>/unitd.pid
|     --log <data>/logs/unit.log --statedir <data>/unit/state
|     --tmpdir <data>/unit/tmp --modulesdir <data>/modules
|   +-- unit: controller
|   +-- unit: router                       listeners on 127.0.0.1 and [::1], ports 8080 and 8443
|   +-- unit: "example" prototype          php 8.4, OPcache shared memory
|   |   +-- unit: "example" application    workers, 0 to 8, started on demand
|   +-- unit: "example-xdebug" prototype   only while `xdebug on` or `xdebug trigger`
|   |   +-- unit: "example-xdebug" application
|   +-- unit: "shop" prototype             php 8.5
|       +-- unit: "shop" application       workers, 0 to 2 (SQLite project)
+-- mariadbd --datadir=<data>/projects/example/mariadb --port=<port>
|     --socket=<run>/example-mariadb.sock --bind-address=127.0.0.1
+-- mailpit --smtp 127.0.0.1:1025 --listen 127.0.0.1:8027
|     --database <data>/mailpit/mailpit.db
+-- cron ticker (inside the daemon)        every 300 s for each running project:
                                           <data>/runtimes/php-8.4/bin/php <example>/vendor/bin/drush cron
```

FreeUnit names a prototype process `"<name>" prototype` ([nxt_main_process.c:636-647](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_main_process.c#L636-L647)) and a worker `"<name>" application`; `ps` shows both with the `unit: ` prefix. The daemon restarts unitd, `mariadbd` and Mailpit with exponential back-off. FreeUnit itself starts and replaces PHP workers.

### 4.4 Files on disk

```
<project>/                                  the developer's checkout
  .freeunit4drupal/
    config.yaml                             committed
    config.local.yaml                       gitignored, merged last
    snapshots/                              gitignored; every snapshot the tool creates
  .ddev/                                    read only; used when no native config exists
    db_snapshots/                           read only; `snapshot restore` reads DDEV snapshots (E8)
  composer.json, vendor/, ...
  private/                                  Drupal private files, gitignored
  web/sites/default/
    settings.php                            gains one include stanza (native mode, on `init`)
    settings.freeunit4drupal.php            generated on `start`, gitignored (native mode)
    settings.ddev.php                       regenerated in DDEV mode only, and only with #ddev-generated
    files/.ht.sqlite                        SQLite projects only

<data> = $XDG_DATA_HOME/freeunit4drupal or ~/.local/share/freeunit4drupal (Linux)
         ~/Library/Application Support/freeunit4drupal (macOS)
  config.yaml                               global settings: ports, CA, update check
  bin/composer                              Composer, downloaded by the tool (project setting `composer`)
  runtimes/
    freeunit-1.37.1/sbin/unitd              the FreeUnit build pinned by this tool release
    php-8.4/bin/php                         CLI
    php-8.4/bin/php-config
    php-8.4/lib/php/extensions/xdebug.so
    php-8.5/...                             same layout
  lib/                                      shared libraries that the modules load (copies)
    libphp-8.4.so                           SONAME libphp-8.4.so; libphp-8.4.dylib on macOS
    libphp-8.5.so
  modules/                                  the unitd modules directory: real copies, never symlinks
    php8.4.unit.so                          NEEDED libphp-8.4.so, RUNPATH $ORIGIN/../lib
    php8.5.unit.so                          on macOS: @rpath/libphp-8.x.dylib, @loader_path/../lib
  unit/state/                               FreeUnit state: conf.json, certs/
  unit/tmp/
  ca/rootCA.pem
  ca/bundle.pem                             system CA certificates plus the tool CA, for PHP (D11)
  ca/rootCA-key.pem                         mode 0600
  projects/<name>/
    php.ini, php-xdebug.ini, php.d/         one ini set for FreeUnit (options.file) and the CLI (PHPRC)
    certs/leaf.pem                          396-day leaf
    mariadb/                                data directory
    tmp/                                    Drupal temporary files
    logs/php-error.log, logs/mariadb.log
  mailpit/mailpit.db
  composer/                                 COMPOSER_HOME
  logs/daemon.log, logs/unit.log, logs/access.log

<run> = $XDG_RUNTIME_DIR/freeunit4drupal (Linux), or ~/.freeunit4drupal/run when the variable is unset, as in WSL2 without systemd and in CI
        ~/.freeunit4drupal/run (macOS)
  daemon.sock, control.sock, unitd.pid, <name>-mariadb.sock
```

`<run>` is a short path because unix socket paths have a small length limit. `diagnose` checks every socket path against the platform's limit. It also checks that `<run>` has mode 0700 and that `daemon.sock` and `control.sock` have mode 0600.

### 4.5 Reconfiguration rules

- Any change to a project file, an `.env` file, an ini fragment, or a start or stop makes the daemon render the whole configuration, check it against the tool's own schema, and apply it with one `PUT /config`.
- If only routes, listeners or certificate lists change, FreeUnit reuses every application whose JSON is byte-identical, with its processes and OPcache ([nxt_router.c:3152-3154](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_router.c#L3152-L3154)). E4 tests this.
- If one project's application JSON changes, only that application gets a new prototype and a cold OPcache.
- `restart <project>` calls `GET /control/applications/<name>/restart`. Old workers finish their requests while new ones start.
- `PUT /certificates/<project>` replaces a bundle in place. New TLS handshakes use the new leaf at once.

## 5. Phase 0 experiments

Phase 0 answers every open question in the research that blocks a decision. It runs with two people from the day they are named (target 2026-10-08) to 2026-10-30. M0 does not start until both are named (section 10 item 9). Each experiment writes one file, `docs/phase0/E<n>.md`. The file starts with a status word (VERIFIED, MEASURED or UNKNOWN) and records the FreeUnit, PHP, OS and hardware versions, the exact commands and the raw output. Sizes are estimates (judgement).

| ID | Question | Decision unblocked | Size |
|---|---|---|---|
| E1 | Does a FreeUnit module load a static-php-cli shared `libphp`, with Xdebug, also under `setcap`? | D6 | 4 to 6 days, both platforms |
| E2 | Do two PHP minor versions load side by side in one unitd? | D2 | 1 day |
| E3 | Does one broken project block a shared `PUT /config`? | D2, D11 | 1 to 2 days |
| E4 | Does a routes-only `PUT` keep OPcache warm? | D11, D12 | 0.5 day |
| E5 | Which Xdebug activation path works: environment, ini or `options.admin`? | D12, section 7 item 3 | 1 day |
| E6 | Can a non-root unitd on macOS bind `127.0.0.1:443`, and does the `source` guard hold? | D4 | 0.5 day |
| E7 | Does Package Manager work under FreeUnit once `PHP_BINARY` is set? | D10, D11 | 1 to 2 days |
| E8 | Does a DDEV MariaDB snapshot restore into a native MariaDB? | D8, D13 | 1 day |
| E9 | Where does `*.localhost` resolve, including PHP, Node and WSL2? | D3, D4 | 2 days |
| E10 | Which certificates and trust paths do browsers accept? | D5 | 2 days |
| E11 | How many real DDEV Drupal projects import without edits? | D10, D13, S4 | 2 to 3 days |
| E12 | What do the tool and DDEV use in disk, memory and start time on one machine? | D18, D11 | 2 days |
| E13 | How many PHP workers can a SQLite project use before "database is locked"? | D11 | 1 day |
| E14 | Does BigPipe stream, and how are large uploads written? | Minimum FreeUnit version, D11 | 1 day |
| E15 | Do macOS binaries need notarization for this install path? | D14 | 1 day |
| E16 | Does PHP's own `error_log` setting give per-project logs in foreground mode? | D11, section 7 item 4 | 0.5 day |
| E17 | Does the route template deny and serve correctly on Drupal 10, 11 and 12 and on APFS? | D11, section 7 item 8 | 1 to 2 days |

Total: about 23 to 29 person-days. Three tracks can run in parallel:

- Track A, Linux: E1 (Linux), E2, E3, E4, E5, E13, E14, E16. E3, E4, E5 and E16 can start before E1 by using the Debian 13 packages attached to the FreeUnit 1.37.0 release.
- Track B, macOS, browsers and Windows: E1 (macOS), E6, E9, E10, E15. It needs a Mac with a FreeUnit build from master that includes the fix for [issue 595](https://github.com/freeunitorg/freeunit/issues/595), and a Windows 11 machine with WSL2.
- Track C, Drupal and DDEV: E7, E8, E11, E12, E17. It needs DDEV v1.25.4 on the same machines.

### E1. Build a FreeUnit PHP module against a static-php-cli shared libphp

- Question: can FreeUnit load a PHP module built against the shared `libphp` of static-php-cli 2.8.5, with Xdebug as a shared extension, on Linux and macOS? Does it still load when unitd carries a file capability?
- Where: Ubuntu 24.04 x86_64, and macOS 15 arm64 with FreeUnit built from master at or after the fix for [issue 595](https://github.com/freeunitorg/freeunit/issues/595). E6 and the M6 gate cover macOS 26.
- Steps:
  1. `spc download --with-php=8.4 --for-extensions=<D6 list>`.
  2. Linux: `SPC_TARGET=native-native-gnu.2.17 SPC_CMD_VAR_PHP_EMBED_TYPE=shared spc build <D6 list> --build-cli --build-embed --build-shared=xdebug`. macOS: the same without `SPC_TARGET`.
  3. Check that `buildroot/bin/php-config` exists. Run `php -m`. `php -r 'echo SQLite3::version()["versionString"];'` must print 3.45 or newer. `php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'` must print `bool(true)`.
  4. Build FreeUnit (the 1.37.0 tag on Linux, master on macOS): `./configure --modulesdir=<dir> ...`, then `./configure php --module=php8.4 --config=<buildroot>/bin/php-config --lib-path=<buildroot>/lib`, and build the module. Before the FreeUnit build, give the library a versioned name: on Linux, `patchelf --set-soname libphp-8.4.so <buildroot>/lib/libphp.so`; on macOS, `install_name_tool -id @rpath/libphp-8.4.dylib <buildroot>/lib/libphp.dylib`. The module then records the versioned name. FreeUnit writes an absolute rpath into the build tree (auto/modules/php lines 112 to 114 at 872bf041), so rewrite it after the build: on Linux, `patchelf --set-rpath '$ORIGIN/../lib' php8.4.unit.so`; on macOS, `install_name_tool -delete_rpath <buildroot>/lib -add_rpath @loader_path/../lib php8.4.unit.so`.
  5. Install the set in the section 4.4 layout: the library, under its versioned file name, in `<data>/lib/`, and a copy of the module in `<data>/modules/`. Check NEEDED and RUNPATH with `readelf -d` (Linux), or the `@rpath` reference and LC_RPATH with `otool -L` and `otool -l` (macOS). Load the module once through a symlink in another directory and record the symptom: with a versioned SONAME, "cannot open shared object file"; with a plain `libphp.so`, a binding to another `libphp`. Start unitd as a normal user. `GET /status` must list the module, and a `phpinfo()` application must answer.
  6. On Linux, run FreeUnit's PHP pytest files. Install Drupal 11.4 and 12.0.0-beta1 or newer with `drush site:install` on SQLite through the module, and load the front page.
  7. Add `zend_extension=xdebug.so` to the application's ini file and check `extension_loaded('xdebug')`. Load the same `xdebug.so` in the CLI with `php -d zend_extension=<path>/xdebug.so -v`.
  8. Linux only: `sudo setcap cap_net_bind_service=+ep <unitd>`, restart, and check that the module still loads without `LD_LIBRARY_PATH`. Basis: in secure-execution mode glibc ignores `LD_LIBRARY_PATH` ([ld.so(8)](https://man7.org/linux/man-pages/man8/ld.so.8.html)) but still expands `$ORIGIN` when it is the first element of a loaded object's RUNPATH; its trusted-directory check applies only to the main program ([dl-load.c lines 285 to 315 at glibc 04e750e7](https://github.com/bminor/glibc/blob/04e750e75b73957cf1c791535a3f4319534a52fc/elf/dl-load.c#L285-L315)).
  9. Repeat steps 1 to 6 for PHP 8.5.
- Expected result: every step passes. Basis: `LinuxBuilder.php` and `MacOSBuilder.php` at 2.8.5 pass `--enable-embed=shared`; FreeUnit's configure accepts `--config` and `--lib-path` (auto/modules/php lines 17 to 28 at 872bf041); the DrupalCI FreeUnit image builds the module against an embed SAPI built from source. Unverified until run.
- Decision unblocked: D6 and the scope of M1.
- If the result differs: test the D6 fallbacks in the same week. On Linux, use distribution packages. On macOS, use a static embed linked with `--lib-static` and Xdebug compiled in with `xdebug.mode=off`. Add 2 to 4 weeks to M1.

### E2. Two PHP minor versions in one unitd

- Question: do `php8.4.unit.so` and `php8.5.unit.so`, each linked to its own shared `libphp`, load and serve side by side in one unitd? FreeUnit opens modules with `RTLD_GLOBAL` ([nxt_application.c:413](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_application.c#L413)). Both static-php-cli sets name their library `libphp.so` by default, so D6 gives each set a versioned SONAME. Module discovery runs in a separate discovery process ([nxt_application.c:142-149](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_application.c#L142-L149)) that opens each module in turn with `RTLD_GLOBAL | RTLD_NOW` and closes it again (lines 413 and 540); each prototype then loads only its own module (lines 593 and 1513). So the only process where two `libphp` builds can meet is discovery, and the test aims at it.
- Steps:
  1. Put both modules from E1 in one `--modulesdir` and start unitd.
  2. `GET /status` must list both modules.
  3. Configure a `php 8.4` and a `php 8.5` application that print `phpversion()` and `php_ini_loaded_file()`.
  4. Send 1,000 interleaved requests to both with 10 concurrent clients. Check every response and check the unit log for crashes.
  5. Check with `readelf -d` that each module needs its own versioned library, and record the discovery process with `LD_DEBUG=libs`.
  6. Control run: Debian 13 with the 1.37.0 `freeunit-php8.4` and `freeunit-php8.5` packages. Their libraries already have distinct names (`libphp8.4.so` and `libphp8.5.so`), so the control run does not test a shared name.
- Expected result: both versions serve correctly (unverified; the 1.37.0 release ships modules for PHP 8.3, 8.4 and 8.5 for one Debian release).
- Decision unblocked: D2.
- If the result differs: run one unitd per PHP minor version (D2 fallback) and file section 7 item 17.

### E3. One broken project and the shared reconfiguration

- Question: does one project whose PHP cannot start block a `PUT /config` for every project?
- Steps:
  1. Run two healthy projects.
  2. `PUT` a full configuration that adds a third project whose `options.file` names a missing file. Run four variants: `spare` 0 or 1, each with `limits.start_timeout` unset and with 10 s.
  3. Record the HTTP status of the `PUT`, its duration and the log.
  4. Request all three projects. Then `PUT` a corrected configuration.
  5. Repeat with a PHP fatal error in the third project's `settings.php`, which fails at request time.
- Expected result: with `spare` 0, the `PUT` succeeds and only the broken project answers 503. With `spare` 1, the `PUT` may fail with "Failed to apply new configuration", because a worker must start at apply time ([nginx/unit issue 1546](https://github.com/nginx/unit/issues/1546)). Unverified.
- Decision unblocked: D2 and D11.
- If the result differs: validate each project in a short-lived scratch unitd before the shared `PUT`, and file section 7 item 16.

### E4. A routes-only change keeps OPcache warm

- Question: does a `PUT` that changes only routes keep every application's processes and OPcache?
- Steps:
  1. A probe script prints `opcache_get_status()['opcache_statistics']['start_time']` and its worker's pid.
  2. `PUT` a configuration that changes only routes: add an Xdebug route and another project. Read the probe again.
  3. Change one member of the project's application (`environment`) and read the probe again. Check that the other projects keep their start time.
- Expected result: no change after the routes-only `PUT`; a new start time only for the changed application. Basis: byte-identical applications are reused ([nxt_router.c:3152-3154](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_router.c#L3152-L3154)).
- Decision unblocked: D11 (full `PUT`) and D12 (Xdebug by route).
- If the result differs: D12 accepts one cold start on `xdebug on`; D11 keeps the full `PUT`.

### E5. Xdebug activation path

- Question: which way of enabling Xdebug works in a FreeUnit application?
- Steps:
  1. A twin application loads `zend_extension=xdebug.so`. Test three variants: (a) `XDEBUG_MODE=debug` in `environment`; (b) `xdebug.mode=debug` in its ini file; (c) `xdebug.mode=off` in its ini file and `options.admin` `{"xdebug.mode": "debug"}`.
  2. Run `nc -l 9003` as a stand-in for the IDE.
  3. Send requests with the cookie `XDEBUG_SESSION=1`, routed to the twin once by cookie and once by query argument.
  4. Record `ini_get('xdebug.mode')`, `xdebug_info('mode')` and whether a connection arrives on port 9003.
  5. Check that `extension_loaded('xdebug')` is false in the main application.
  6. Run the CLI with `XDEBUG_MODE=debug` and the same `xdebug.so`.
  7. Repeat in WSL2 with the stand-in listening on the Windows side.
- Expected result: (a) and (b) connect. (c) reports `debug` from `ini_get` but no connection arrives. Basis: Xdebug reads its mode in its module start ([xdebug.c:508](https://github.com/xdebug/xdebug/blob/64007df3a0925808022fb87b6c6f06febf058ee2/xdebug.c#L508)); FreeUnit applies `options.admin` after PHP starts ([nxt_php_sapi.c:445-455](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_php_sapi.c#L445-L455)).
- Decision unblocked: D12, and the priority of section 7 item 3.
- If the result differs: if (c) works, D12 may use `options.admin`, and section 7 item 3 drops in value.

### E6. macOS non-root binds and exposure to the local network

- Question: can a non-root unitd on macOS bind `127.0.0.1:443` or `*:443`, and does a `source` guard route keep other machines out?
- Steps:
  1. On macOS 15 and macOS 26 (arm64), as a normal user, with a FreeUnit build that includes the fix for [issue 595](https://github.com/freeunitorg/freeunit/issues/595): `PUT` a listener on `127.0.0.1:443`, then on `*:443`. Record the errors and the `PUT` results.
  2. With the macOS firewall on, note any prompt.
  3. Add the `source` guard route. From a second machine on the same network, request `https://<mac address>/` with `Host: example.localhost`. Record the status code and whether the TLS handshake completes.
- Expected result: `127.0.0.1:443` fails with EACCES; `*:443` works; the guard answers 403; the TLS handshake completes before the guard answers, so the listener is reachable. Basis: [in_pcb.c lines 989 to 1000](https://github.com/apple-oss-distributions/xnu/blob/f6217f891ac0bb64f3d375211650a4c1ff8ca1ea/bsd/netinet/in_pcb.c#L989-L1000) and in6_pcb.c at the same commit.
- Pass: `*:443` binds as a normal user; at most one firewall prompt appears, on the first start; and every request from the second machine gets 403, with no PHP execution in the application log. Fail: a prompt on every start, or any request from another machine reaches PHP.
- Decision unblocked: D4 (the macOS privileged mode).
- If E6 fails: drop the macOS privileged mode and use high ports only.

### E7. PHP_BINARY and Package Manager under FreeUnit

- Question: can Drupal CMS install a module through Package Manager when FreeUnit serves it?
- Steps:
  1. Serve Drupal CMS 2.2.2 (core 11.4) through the tool's FreeUnit application.
  2. In a web request, print `PHP_BINARY` and `PHP_SAPI`.
  3. With and without `PHP_BINARY` and `PATH` in the application environment, open the Package Manager readiness checks on the status report and install one small contributed module through Project Browser.
  4. Repeat with `package_manager_allow_direct_write` TRUE and FALSE, and with rsync installed and missing.
  5. Record each validator message, the Composer run time, and whether `max_execution_time` 600 is enough.
- Expected result: the `PHP_BINARY` constant is empty. With the environment variable set, Symfony's `PhpExecutableFinder` returns it first, and the install succeeds. Basis: [nxt_php_sapi.c:337](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_php_sapi.c#L337) and [PhpExecutableFinder.php v8.1.7](https://github.com/symfony/process/blob/v8.1.7/PhpExecutableFinder.php). Unverified.
- Decision unblocked: D10 and D11 (environment and ini values), and the claim that Drupal CMS works.
- If the result differs: document the limit. If the environment variable is not enough, file a FreeUnit feature that sets the PHP executable location for an application.

### E8. DDEV snapshot restore into a native MariaDB

- Question: can the tool restore a DDEV MariaDB snapshot into a native server?
- Steps:
  1. Create a DDEV v1.25.4 project with MariaDB 11.8 and the Umami demo, and run `ddev snapshot`.
  2. On the host with a native MariaDB 11.8: `zstd -d`, `mbstream -x` into an empty directory, `mariadb-backup --prepare`, then `mariadb-backup --copy-back` into a new data directory.
  3. Start `mariadbd` on it, check the tables and load a Drupal page.
  4. Repeat with a native MariaDB 11.4 to see the version-mismatch failure.
  5. Time a `mariadb-dump` import of the same database as the fallback path.
- Expected result: a restore at the same version works; a mismatched version fails (unverified).
- Decision unblocked: D8 and D13 (`snapshot restore` from `.ddev/db_snapshots`).
- If the result differs: import DDEV databases only through SQL dumps (the output of `ddev export-db`), and say so in the docs.

### E9. Where `*.localhost` resolves

- Question: which browsers, runtimes and systems resolve `*.localhost` to loopback, and how do Windows browsers reach a server in WSL2?
- Steps:
  1. Run a test server answering on `foo.localhost` and `a.foo.localhost`.
  2. Test Chrome, Firefox and Safari on macOS 15 and macOS 26.
  3. Test Ubuntu 24.04 and 26.04, with and without systemd-resolved.
  4. In WSL2 (Ubuntu), in NAT and in mirrored mode, reach unitd from Windows Chrome, Edge and Firefox, with unitd bound once to 127.0.0.1 and once to 0.0.0.0.
  5. On Windows 11 24H2, run `Resolve-DnsName foo.localhost`.
  6. On each system, run `php -r "var_dump(gethostbynamel('foo.localhost'));"`, a `file_get_contents()` of `http://foo.localhost:8080/`, a Guzzle request with the cURL handler, and `node -e "require('dns').lookup('foo.localhost', console.log)"`. Record whether each client tries `::1` or 127.0.0.1 first, and whether it falls back when only one of them listens.
- Expected result: browsers resolve the names except Safari on macOS 15. Linux system resolvers resolve them when systemd provides the resolver. PHP stream wrappers fail on macOS 15 (unverified). Windows browsers reach WSL2 through localhost forwarding in NAT mode ([Microsoft Learn: WSL networking](https://learn.microsoft.com/en-us/windows/wsl/networking)); which bind address WSL2 forwards is unverified.
- Decision unblocked: D3 (when to offer hosts entries) and D4 (the WSL2 bind address).
- If the result differs: offer hosts entries by default on the affected platforms. In WSL2, bind 0.0.0.0 inside the WSL2 VM if 127.0.0.1 is not forwarded.

### E10. Certificates and trust

- Question: which leaf names and trust steps work in each browser and system?
- Steps:
  1. Trust the tool CA (ECDSA P-256) through each D5 path.
  2. Issue a leaf with the SANs `example.localhost`, `*.example.localhost` and `example.ddev.site`, valid 396 days, and serve it through FreeUnit's SNI array.
  3. Test Chrome, Firefox (with and without the NSS import, and with `security.enterprise_roots.enabled` on and off), Safari, curl and Guzzle.
  4. Test a single `*.localhost` leaf.
  5. Test a `Secure` cookie set over `http://a.localhost`.
  6. In WSL2, install the CA in the Windows current-user Root store from inside WSL2, and test Edge and Chrome on Windows.
  7. From the bundled PHP, request `https://example.localhost:8443/` and `https://www.drupal.org/` with `file_get_contents()` and with Guzzle, with and without the generated `openssl.cafile` and `curl.cainfo`.
- Expected result: per-project leaves work everywhere after `trust`. At least one browser rejects the `*.localhost` leaf ([mkcert cert.go](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/cert.go#L148-L161)). Firefox on Linux needs the NSS import, as DDEV documents. The WSL2 method is unverified.
- Decision unblocked: D5.
- If the result differs: change the leaf names and trust steps, and document a manual import where automation fails.

### E11. DDEV import corpus

- Question: how many real DDEV Drupal projects could start without edits, and what blocks the rest?
- Steps:
  1. Use GitHub code search to find public repositories with `.ddev/config.yaml` and a Drupal project type. Pick 50, excluding forks, with a mix of Drupal 10, 11, 12 and Drupal CMS. Record the list in `test/corpus/`.
  2. Run a classification script, `test/corpus/classify`, over the 50 `.ddev/config*.yaml` sets with DDEV's merge rules. It is the first version of the importer's field mapper. Count the fields by class (Maps, Partial, Irrelevant, Unsupported), custom nginx or Apache files, `docker-compose.*.yaml` add-ons, `.ddev/commands/web/*` files, hooks with `exec` tasks, and use of `.ddev/.env`.
  3. For 10 of the 50, write the FreeUnit configuration by hand from the section 4.2 template, serve the project with unitd 1.37.0 on Linux, load the front page and run `drush status`. The importer's own dry run and a full `start` are the M5 gate (S4).
  4. Run each DDEV web command from the corpus on the host with `DDEV_*` variables set, and list the failures.
- Expected result: unknown. This is the baseline for S4.
- Decision unblocked: the scope of D10 and D13, and the wording of the import claim.
- If the result differs: below 60%, the import is labelled experimental and the docs make no "without edits" claim (risk R6).

### E12. Resource and start-time baseline against DDEV

- Question: what disk, memory and start time do a FreeUnit stack and DDEV use on the same machine?
- Steps:
  1. Prepare one site: Umami plus five common contributed modules, on MariaDB 11.8.
  2. Machines: macOS 15 arm64, with DDEV on OrbStack and on Docker Desktop; Ubuntu 24.04 x86_64, with DDEV on Docker CE. Both run on the same machine. In Phase 0 the FreeUnit side is a stack started by a shell script: unitd with the section 4.2 configuration (1.37.0 on Linux; on macOS, a master build with the fix for [issue 595](https://github.com/freeunitorg/freeunit/issues/595)), `mariadbd` and Mailpit. M2 and every release measure the tool itself again (S7).
  3. Measure the download size for the first project and the disk use after the first start.
  4. Measure the time from `start` to the first 200 response, cold and then warm, 5 runs each.
  5. After a 100-page crawl, measure the resident memory (RSS and PSS) of every process: the FreeUnit prototype and workers, `mariadbd` and Mailpit for the tool; the VM as the provider reports it and the container statistics for DDEV. Also record OPcache memory.
  6. Record versions, hardware, core count and run count with the results.
- Expected result: unknown. The results are the only numbers the docs may use (D18).
- Decision unblocked: the wording of D18 and the default `processes.max` in D11.

### E13. SQLite concurrency

- Question: how many PHP workers can a SQLite project use before writes fail with "database is locked"?
- Steps: Drupal 11.4 with the standard profile on SQLite (WAL mode). Set FreeUnit's `processes.max` to 1, 2, 4 and 8. Run 20 concurrent logged-in clients for 10 minutes, mixing page views, node saves and cache clears. Count "database is locked" errors and failed saves.
- Expected result: errors rise with the worker count. Basis: [drupal.org/i/1120020](https://www.drupal.org/i/1120020). Unverified for FreeUnit.
- Decision unblocked: the SQLite `max` in D11.

### E14. BigPipe streaming and large uploads

- Question: does BigPipe stream under FreeUnit 1.37, and how is a large upload written to disk?
- Steps:
  1. Install Drupal standard with `big_pipe`. As a logged-in user, fetch a page with `curl -N --raw` and timestamp each chunk, on FreeUnit 1.36.1 and on 1.37.x.
  2. Upload a 100 MB file to a file field with `max_body_size` 1 GiB and PHP limits of 200M. Trace writes with `strace -f -e trace=openat,write` on the router and the worker.
- Expected result: chunks arrive one by one on 1.37.x (basis: CHANGES lines 95 and 103, fixes in 1.37.0). The upload is written once to the router's temporary file and once to PHP's upload directory.
- Decision unblocked: the minimum FreeUnit version for the tool, and the body limits in D11.

### E15. macOS binary distribution

- Question: what must the project sign or notarize so that macOS runs its binaries?
- Steps: on clean macOS 15 and macOS 26 machines, download the Go binary with `curl` and with a browser. Run it with and without the quarantine attribute. Repeat with a notarized but unstapled build. Download a runtime set (unitd, the `libphp` library, the modules and `xdebug.so`) with a short Go program that uses `net/http`, as the tool will, then start unitd by hand.
- Expected result: `curl` downloads run; browser downloads need notarization; libraries downloaded by a Go program load (unverified).
- Decision unblocked: D14 (when notarization starts and when the Apple Developer Program is needed).

### E16. Per-project PHP error log in foreground mode

- Question: does PHP's own `error_log` setting give per-project error logs while unitd runs with `--no-daemon`?
- Steps: run unitd with `--no-daemon`. Set `log_errors=1` and `error_log=<data>/projects/example/logs/php-error.log` in the project's ini file. Trigger a warning, a fatal error, an `error_log()` call and a `fwrite(STDERR, ...)` call, and record where each one lands.
- Expected result: the per-project file receives the warning, the fatal error and the `error_log()` call. `fwrite(STDERR)` reaches the unit log (unverified).
- Decision unblocked: logging in D11, and the priority of section 7 item 4.

### E17. Route template across Drupal majors and file systems

- Question: does the shared route template deny what Drupal's `.htaccess` denies and serve every entry point, on every supported major and file system?
- Steps:
  1. Render the template for Drupal 10.6.18, 11.4.8 and 12.0.0-beta1 or newer.
  2. Run a probe suite: the front page, clean URLs, `update.php`, `core/install.php`, `core/rebuild.php`, an image style derivative, a private file download, gzip and brotli aggregates, a `.svgz` file, `/core/composer.JSON`, `/WEB.CONFIG`, `/sites/default/settings.php`, `/sites/default/files/x.php`, `/vendor/autoload.php`, `/sites/default/files/.ht.sqlite`, a child site of a running Functional test under `sites/simpletest/`, which must be served, and `/core/authorize.php` on Drupal 12, which must answer 404.
  3. Run on APFS (case-insensitive) and on ext4.
- Expected result: every deny probe answers 404 or 403 on both file systems, and every entry point works on its major.
- Decision unblocked: D11, and section 7 item 8 (the shared template upstream).

### Open questions that block no decision

| Question from the research | Disposition |
|---|---|
| Start time of a Go, Rust or `micro` build (research/tool-architecture-options.md §12, item 9) | Dropped. D1 rests on code reuse and licences. |
| The macOS 26 `/etc/resolver` regression (same section, item 7) | Dropped. D3 rejects dnsmasq. |
| The Drupal Redis module against Garnet on Windows (item 11) | Moved to M11. |
| The `pcntl_fork` exit re-entry (research/freeunit-capabilities.md §13, item 9) | FreeUnit issue, section 7 item 13. |
| An `external` application that exits at once, in a loop | Not needed; D9 uses no `external` applications. |
| PhpStorm and VS Code integrations for DDEV | Checked in M7. |
| OAuth redirect rules of Google, GitHub and Microsoft for `http://*.localhost` | Documented as a limit in M8. |
| HTTP.sys holding port 80 on Windows | Moved to M11. |
| Drush behaviour without a URI | Not needed; the tool always sets `DRUSH_OPTIONS_URI`. |
| Whether gitlab_templates can run a non-Apache image | Community work in M8; it does not affect the tool. |
| Extension needs of Commerce, Address and similar modules | Reviewed in M9 with the optional extension list. |
| Demand for macOS x86_64 and PostgreSQL | Answered by DDEV data: 5.3% of macOS users on Intel, 6.3% of database settings on PostgreSQL. |
| Java resolution of `*.localhost` (research/tool-architecture-options.md §12, item 5) | Deferred to M9 with Solr. No Java code calls the site in the first release. |

## 6. Milestones

All dates are targets. Sizes are estimates (judgement), because no prototype exists. The basis for the Go sizes is the line count of comparable code, taken for this plan at the commits the research cites. Counts are lines in non-test `.go` files, comments included, counted on 2026-10-07 with `find <dir> -name '*.go' ! -name '*_test.go' | xargs wc -l` at the named commits, excluding `vendor/` and `testdata/`:

- Symfony CLI ([a9011678](https://github.com/symfony-cli/symfony-cli/tree/a9011678fe13f2d6778768a09a01c94ac8e519e9)): 23,066 in total. Of these, `local/php` has 2,306; `local/pid`, `local/process` and `local/logs` together have 1,226; `commands` has 4,961; `local/proxy` has 771; and `envs` has 2,382.
- mkcert ([1c1dc4ed](https://github.com/FiloSottile/mkcert/tree/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e)): 1,381. symfony-cli/cert: 1,010.
- DDEV ([598e255e](https://github.com/ddev/ddev/tree/598e255e233f060dd371a9abc46c73742695f9b4)): 54,768 in total. Of these, `pkg/ddevapp` has 20,590; `cmd/ddev` has 15,851; `pkg/globalconfig` has 1,301; and `cmd/ddev-hostname` has 308.

| ID | Deliverable | Target dates | Size | Depends on | Runs in parallel with |
|---|---|---|---|---|---|
| M0 | Phase 0 experiments | 2026-10-08 to 2026-10-30 | 23 to 29 person-days | none | none |
| M1 | Runtime build pipeline | 2026-11-02 to 2026-12-04 | 800 to 1,500 lines of CI and shell; 3 to 5 person-weeks | E1 | M2 |
| M2 | Linux core (alpha) | 2026-11-02 to 2026-12-18 | 4,000 to 6,500 lines of Go; 6 to 9 person-weeks | E2, E3, E4 | M1 |
| M3 | Databases, mail, cron, data commands | 2026-12-07 to 2027-01-22 | 2,000 to 3,500 lines; 4 to 6 person-weeks | M2, E8 | M4 |
| M4 | TLS, names, ports | 2026-12-07 to 2027-01-15 | 1,500 to 2,500 new lines plus 1,689 reused; 3 to 5 person-weeks | M2, E6, E9, E10 | M3 |
| M5 | DDEV live import | 2027-01-04 to 2027-02-12 | 2,000 to 3,500 lines; 3 to 5 person-weeks | M2, M3, E11 | M6, M7 |
| M6 | macOS arm64 | From the FreeUnit 1.37.1 tag; target 2027-02-26 | 500 to 1,500 lines plus CI; 2 to 4 person-weeks | FreeUnit 1.37.1 tag; E6, E9, E10, E15 | M5, M7 |
| M7 | Developer workflow | 2027-01-18 to 2027-02-26 | 1,500 to 2,500 lines; 3 to 5 person-weeks | M2, M4, E5 | M5, M6 |
| M8 | Public beta and community work | 2027-03-01 to 2027-03-19 | Documentation; 2 to 3 person-weeks | M3 to M7 | none |
| M9 | More services | 2027-04 to 2027-06 | 2,000 to 4,000 lines; 5 to 8 person-weeks | M8 | M10 |
| M10 | 1.0 | Target 2027-10-15 | 2 to 4 person-weeks | M8; S6 held for six months | M9 |
| M11 | Native Windows | No date | Tool: 2,000 to 4,000 lines, 6 to 10 person-weeks. FreeUnit: see section 7 item 19 | The freeunit-windows project | Any |
| T1 | DDEV add-on that runs FreeUnit (optional) | Any time after E17 | 1 to 2 person-weeks | E17 | All |

Capacity check: M1 to M8 add up to 26 to 42 person-weeks and 11,500 to 20,000 lines of Go. Between 2026-11-02 and 2027-03-19, two people working about full time have about 35 to 39 person-weeks after holidays. The beta date holds only if the work lands in the lower half of the range. If it does not, the beta moves to a date before DrupalCon Edinburgh (2027-10-25), and 1.0 moves to 2028. The calendar windows overlap: M1 with M2, M2 with M3 and M4, and M5 with M6 and M7. The same two people work across them, so a window shows when a milestone may be worked on, and the person-week sums set the capacity.

### M0. Phase 0 experiments

- Deliverables: `docs/phase0/E1.md` to `E17.md`; the build scripts from E1, which M1 reuses; updated decisions D2, D4, D6, D11 and D12 in this file.
- Gate: on 2026-10-30 the maintainers evaluate the condition in section 1 and record the result.

### M1. Runtime build pipeline

- Deliverables:
  - CI jobs that build runtime sets for PHP 8.3, 8.4 and 8.5 on linux-amd64 and linux-arm64, and unitd for the pinned FreeUnit version, built with OpenSSL (for TLS) and PCRE2 (for regular expressions in routes). darwin-arm64 jobs run against FreeUnit master for testing and publish only after M6 starts.
  - `SHA256SUMS` and cosign signatures for every artifact, and a NOTICE file per set that lists the bundled libraries and their licences.
  - A scheduled job that compares the newest PHP patch versions with the built sets and opens a rebuild pull request.
  - Smoke tests per set: `php -m` against the D6 list, SQLite 3.45 or newer, argon2, the module loads in unitd, `phpinfo()` answers through FreeUnit, and `PUT /config` accepts a route with a `~(?i)` pattern.
- Gate: two clean CI runs produce the same extension list for each set. A rebuild drill for one PHP patch release finishes within 2 working days.

### M2. Linux core (alpha)

- Deliverables:
  - The Go module and the `freeunit4drupal` binary for linux-amd64 and linux-arm64.
  - The native configuration (D10) without DDEV import, and Drupal version detection (D7).
  - The daemon (D9) with unitd supervision, the control-API client, and the configuration renderer with the shared route template (D11). These three parts are an interim deliverable due 2026-12-04, because M3 and M4 build on them.
  - Native settings generation (D10).
  - Runtime selection: the "bring your own" mode first, then the M1 sets.
  - SQLite projects, and `<name>.localhost` over HTTP on the high port (D3, D4).
  - Commands `init`, `start`, `stop`, `restart`, `poweroff`, `list`, `describe`, `launch`, `logs`, `exec`, `php`, `composer`, `drush`, `dr` and `version`, and CI mode.
- Gate (S2): on Ubuntu 24.04 x86_64 and arm64, and in WSL2 Ubuntu, `init` and `start` serve Drupal 10.6, 11.4 and 12.0 (or the newest pre-release), installed with `drush site:install` on SQLite. The E17 probe suite passes. `drush status` shows the project URL. `stop` and `poweroff` leave no process behind.

### M3. Databases, mail, cron and data commands

- Deliverables: MariaDB and MySQL detection and per-project servers (D8); `mysql`; `import-db`, `export-db` and `import-files`; `snapshot` create, list and restore with `mariadb-backup` and zstd, plus a dump fallback; the Mailpit download, supervision and settings; the cron ticker.
- Needs from M2: the interim deliverable of 2026-12-04 (the daemon, the control-API client and the renderer).
- Gate: Drupal 11.4 and 12.0 install on MariaDB 11.8 and on MySQL 8.4 on Linux. A password-reset mail appears in Mailpit. A snapshot round trip restores the same content. A DDEV snapshot restores, if E8 passed.

### M4. TLS, names and ports

- Deliverables: `trust` (D5) with every store, including NSS and the WSL2 Windows store as E10 found; per-project leaves, renewal and the SNI listener; `hosts add|remove` with the elevated helper; `ports privileged` for Linux, and for macOS if E6 passed; `clean --all`, which reverses each step.
- Needs from M2: the interim deliverable of 2026-12-04 (the daemon, the control-API client and the renderer).
- Gate: after `trust`, Chrome and Firefox on Linux load `https://<name>.localhost:8443` with no warning (Safari on macOS 26 is checked in M6). A script confirms that `clean --all` leaves no CA, hosts entry or file capability behind.

### M5. DDEV live import

- Deliverables: the `.ddev/config*.yaml` reader with DDEV's merge rules and the 52-field mapping; DDEV-mode settings (D10); the 28 hook points, run on the host; host and web custom commands (D13); `.ddev/php/*.ini` and `.ddev/mysql/*.cnf`; `init --from-ddev`; the corpus runner in CI. `nodejs_version`, `nodejs_root` and `corepack_enable` are reported, and the host's Node is used until M9 adds fnm.
- Gate (S4): 40 of the 50 corpus repositories start and serve the front page without edits on Linux.

### M6. macOS arm64

- Start: the day FreeUnit tags 1.37.1, or a later release with the Apple Silicon fix. Target: 2027-02-26.
- Deliverables: darwin-arm64 runtime sets and unitd built from the tagged release; macOS paths, a launchd agent for `autostart`, and System keychain trust; the Homebrew tap formula; notarization as E15 decided.
- Gate: the gates of M2 to M5 pass on macOS 15 and macOS 26 on Apple Silicon.
- Dependencies: section 7 item 1 (the tag) is required. Item 2 (macOS module CI) is strongly wanted. If no tagged FreeUnit release serves PHP on Apple Silicon by 2027-01-31, the beta ships for Linux and WSL2 only (risk R2).

### M7. Developer workflow

- Deliverables:
  - The Xdebug twin (D12).
  - `share` through cloudflared and ngrok. It adds the tunnel name to the routes and to `trusted_host_patterns`, and sets FreeUnit's `forwarded` object and Drupal's `reverse_proxy` settings for the local tunnel client.
  - `diagnose`, and `self-update`, which applies `setcap` again.
  - `autostart on|off` on Linux, as a systemd user unit. M6 adds the launchd agent for macOS.
  - A GitHub Action.
  - Setup notes for PhpStorm and VS Code.
- Gate: a breakpoint stops in PhpStorm and in VS Code on Linux and on macOS, for a web request and for a `drush` command. `share` serves the site through a cloudflared quick tunnel. The GitHub Action runs a Drupal 11 Functional test.

### M8. Public beta and community work

- Deliverables:
  - The beta release (S3).
  - User documentation: installation, a quick start, DDEV import, a WSL2 guide, the known limits, and the E12 measurements with their DDEV baseline.
  - A child page in the drupal.org "Local server setup" guide, an issue in the Official Docs queue, and a pull request to the Launcher's README (S8).
  - A BoF proposal for DrupalCon Orlando (2027-03-22 to 2027-03-25).
  - A proposal to Drupal CMS for a neutral variable that marks a managed local environment, so that tools need not set `IS_DDEV_PROJECT`.
- Gate: S3, S4 and S5 are met. A reviewer who did not write the code reviews the security of `trust`, the hosts helper, `setcap` and the daemon socket.

### M9. More services

- Deliverables: PostgreSQL 16 and 18 from the theseus-rs portable builds, with `pg_trgm`; Valkey or Redis; Node through fnm and corepack; an optional Docker hybrid for Solr, Elasticsearch and OpenSearch; the optional extensions imagick, redis, memcached, pdo_pgsql, uploadprogress and xhprof.
- Gate: Drupal 11.4 and 12.0 install on PostgreSQL 16 and 18, and the redis module works with Valkey.

### M10. 1.0

- Deliverables: deb and rpm repositories; a security policy and a table of supported versions; migration notes from the beta.
- Gate: S6 has held for six consecutive months, and no issue labelled data loss or security is open.

### M11. Native Windows

- Start: when the freeunit-windows project serves PHP on Windows 11 x64 in a foreground console process.
- Deliverables: Windows runtime sets based on the official windows.php.net builds, with the FreeUnit module built with clang-cl; trust in the current-user Root store; a hosts helper with one UAC prompt per change (DDEV's model); a Task Scheduler login item; port checks for `excludedportrange` and HTTP.sys; signed binaries; winget and Scoop packages.
- Gate: the gates of M2 to M5 pass on Windows 11 x64.

### T1. DDEV add-on that runs FreeUnit (optional)

- Deliverable: a DDEV add-on that installs FreeUnit and the shared route template in DDEV's web container with `webserver_type: generic`, following the official `ddev-frankenphp` add-on.
- Gate: the E17 probe suite passes inside DDEV.

## 7. Changes needed in FreeUnit

File these in https://github.com/freeunitorg/freeunit unless the item says otherwise. The order follows research/freeunit-capabilities.md §11, adjusted for this plan's architecture. macOS module CI moves up, because it protects M6. The Homebrew formulas move down, because the tool ships its own unitd. The shared route template comes from research/drupal-developer-requirements.md, and the `LISTEN_FDS` item comes from research/tool-architecture-options.md §5. Code locations are at commit 872bf041. Sizes are judgement from reading the code, not from a prototype. Bug fixes belong to the FreeUnit bug-fix tracking issue [597](https://github.com/freeunitorg/freeunit/issues/597): item 1 is already in its wave 0 (issue 595), and items 2, 9, 11, 12 and 13 should be added to it as checklist entries, each with its own issue. Worker mode and schedules belong to the 1.38.0 tracking issue [596](https://github.com/freeunitorg/freeunit/issues/596).

1. **Release 1.37.1 with the Apple Silicon shared-memory fix.**
   - Motivation: 1.37.0 fails every application request on macOS with 16 KiB pages ([issue 595](https://github.com/freeunitorg/freeunit/issues/595)). The fix merged on 2026-10-07 ([pull request 598](https://github.com/freeunitorg/freeunit/pull/598)), and CHANGES already has a 1.37.1 block. M6 cannot start without the tag.
   - Files: none. Size: release work only. Type: bug-fix release. Ask the release manager for a date rather than filing an issue.
2. **ci: build the PHP module and run the PHP tests on macOS.**
   - Motivation: the macOS job builds "No modules, no pytest, no sudo" (`.github/workflows/build-test-macos.yml` lines 25 to 30), which is how the Apple Silicon failure reached a release. [Pull request 602](https://github.com/freeunitorg/freeunit/pull/602) added the C tests on macOS.
   - Files: `.github/workflows/build-test-macos.yml`. Size: 40 to 100 lines of workflow, plus unknown test fixes. Type: CI.
3. **php: apply `options.admin` before `php_module_startup()`.**
   - Motivation: `zend_extension`, `opcache.jit` and `xdebug.mode` in `options.admin` fail or have no effect, because FreeUnit applies the values after PHP starts ([nginx/unit issue 785](https://github.com/nginx/unit/issues/785); the use case in [nginx/unit issue 969](https://github.com/nginx/unit/issues/969)). The fix lets a project turn on Xdebug or JIT from the JSON configuration alone. E5 sets its priority.
   - Files: `src/nxt_php_sapi.c` lines 393 to 470 (startup at line 445, `ini_entries` at line 355). Size: 60 to 120 lines plus tests. Type: feature.
4. **application: honour per-application `stdout` and `stderr` with `--no-daemon`.**
   - Motivation: `nxt_app_set_logs()` returns early when unitd is not a daemon (`src/nxt_application.c` line 1601). A supervisor that runs unitd in the foreground therefore cannot give each project its own output file. The behaviour is documented as daemon-only ([nginx/unit issue 915](https://github.com/nginx/unit/issues/915)). E16 sets its priority.
   - Files: `src/nxt_application.c` lines 1586 to 1635. Size: 3 to 10 lines plus a pytest of about 40 lines. Type: feature (behaviour change).
5. **router: merge schedules.**
   - Motivation: Drupal cron without an external timer. Schedules ship in 1.38.0: [pull request 452](https://github.com/freeunitorg/freeunit/pull/452) says "This feature is for 1.38.0", and the 1.38.0 tracking issue [596](https://github.com/freeunitorg/freeunit/issues/596) plans schedules together with PHP worker mode. The pull request's GitHub milestone (1.37.1) lags its description. The item is already scheduled, so S11 does not count it.
   - Files: already written. Size: review only. Type: feature.
6. **http: an optional reason on server error pages.**
   - Motivation: "Error 503." says nothing about a failed start, a timeout or a missing application (`src/nxt_http_error.c` lines 19 to 22). Precedents: [nginx/unit issue 489](https://github.com/nginx/unit/issues/489) and [nginx/unit issue 1347](https://github.com/nginx/unit/issues/1347).
   - Files: `src/nxt_http_error.c`, the validator (`src/nxt_conf_validation.c` lines 390 to 451) and the router's socket configuration. Size: 150 to 300 lines plus tests. Type: feature.
7. **packaging: FreeUnit formulas in the Homebrew tap.**
   - Motivation: the tap's `unit` and `unit-php` formulas build NGINX Unit 1.34.2, and `php-embed` builds PHP 8.4.2 ([homebrew-unit](https://github.com/freeunitorg/homebrew-unit)). FreeUnit users on macOS, and the tool's "bring your own" mode, need an install path without Docker.
   - Files: `Formula/*.rb` in freeunitorg/homebrew-unit (file the issue there). Size: 150 to 250 lines of Ruby. Type: packaging.
8. **drupal: one tested route template for CI, the dev kit, the howto and local tools.**
   - Motivation: three Drupal configurations exist, and they differ: the howto, the DrupalCI image's `drupal.json` on the dev branch, and `unit-drupal.json` in [pull request 453](https://github.com/freeunitorg/freeunit/pull/453). The howto's globs are case-sensitive, it lists `core/authorize.php`, which Drupal 12 removed, and it has no rules for private and temporary directories. E17 provides the test suite.
   - Files: `pkg/docker/drupal/` (from [pull request 453](https://github.com/freeunitorg/freeunit/pull/453)) and `source/howto/drupal.rst` in freeunitorg/docs. Size: 200 to 400 lines of JSON and tests. Type: feature and documentation.
9. **static: add `.wasm`, `.map`, `.webmanifest` and `.csv` to the MIME table.**
   - Motivation: `.wasm` served without `application/wasm` breaks `WebAssembly.instantiateStreaming()`, and a file with an unknown extension gets no Content-Type at all (`src/nxt_http_static.c` lines 834 to 844). Comment on the open [issue 559](https://github.com/freeunitorg/freeunit/issues/559) rather than file a duplicate.
   - Files: `src/nxt_http_static.c` lines 2054 to 2125. Size: 3 to 6 lines plus a test of about 20 lines. Type: bug fix.
10. **access log: a per-listener or per-route access log.**
    - Motivation: there is one global access log (`src/nxt_conf_validation.c` line 352), and a shared instance needs one log per project. Until then the tool splits the log on `$host`.
    - Files: `src/nxt_router_access_log.c`, `src/nxt_conf_validation.c` and `src/nxt_router.c`. Size: 200 to 400 lines plus tests. Type: feature.
11. **runtime: use or remove the inherited `LISTEN_FDS` sockets.**
    - Motivation: unitd reads `LISTEN_FDS` into `rt->inherited_sockets`, but `nxt_runtime_listen_sockets_create()` has no caller. Sockets that systemd passes are never used, and the variable only stops unitd from daemonising. The loop that collects inherited sockets is `for (s = 3; s < n; s++)` (line 258), where `n` is the socket count, so with `LISTEN_FDS=1` it takes no socket and with `LISTEN_FDS=4` it takes only fd 3; systemd passes n sockets as fds 3 to 3+n-1 ([sd_listen_fds](https://www.freedesktop.org/software/systemd/man/latest/sd_listen_fds.html)). If wired, a system socket unit could pass ports 80 and 443 to an unprivileged unitd without `setcap`.
    - Files: `src/nxt_runtime.c` lines 218 to 262, 399 and 1522 to 1565; `src/nxt_runtime.h` line 154. Size: 30 to 150 lines. Type: bug.
12. **CHANGES: add the entry for the Last-Modified GMT fix.**
    - Motivation: commit fd2d1501 shipped in 1.37.0 without a CHANGES entry, so 1.36.x users do not learn that their Last-Modified header was offset by the server's time zone.
    - Files: `CHANGES` and `docs/changes.xml`. Size: 6 to 10 lines. Type: documentation.
13. **php: re-test and guard the `pcntl_fork()` exit re-entry.**
    - Motivation: in a measurement on 1.36.0 (2026-09-12), a child forked during a request that called `exit()` returned into the request loop and served traffic. No guard exists at 872bf041. It is rare in Drupal.
    - Files: `src/nxt_php_sapi.c` around lines 1363 to 1384. Size: 10 to 30 lines plus a test. Type: bug fix.
14. **runtime: accept `--log -` for stderr in foreground mode.**
    - Motivation: convenience only; `--log /dev/stderr` already works.
    - Files: `src/nxt_runtime.c` lines 1262 to 1269 and 1497 to 1517. Size: 10 to 20 lines. Type: feature.
15. **docs: explain why the PHP SAPI is named "cli-server", and keep the name.**
    - Motivation: a rename would turn off OPcache on PHP 8.4 and older ([php-src 3088d640684](https://github.com/php/php-src/commit/3088d640684)).
    - Size: about 10 lines of documentation. Type: documentation.
16. **Only if E3 fails. router: keep one application's failed start from failing the whole reconfiguration.**
    - Motivation: in a shared instance, one broken project would block configuration changes for every other project.
    - Files: `src/nxt_router.c` and `src/nxt_controller.c`. Size: unknown until E3 (judgement: 200 to 600 lines). Type: feature.
17. **Only if E2 fails. application: load PHP modules linked against different `libphp` builds in one instance.**
    - Motivation: one server for several PHP versions is the basis of D2.
    - Files: `src/nxt_application.c` lines 268 and 413 (module discovery and `dlopen` with `RTLD_GLOBAL`). Size: unknown until E2. Type: bug.
18. **Track only, no new issue: HTTP/2.**
    - Seven open pull requests target milestone 1.38 on 2026-10-07, from stage 1 in [pull request 463](https://github.com/freeunitorg/freeunit/pull/463) to stage 4 in [pull request 479](https://github.com/freeunitorg/freeunit/pull/479). With aggregation off in development, a Drupal page requests many files, and Chromium opens at most 6 HTTP/1.1 connections per host ([client_socket_pool_manager.cc](https://github.com/chromium/chromium/blob/f24386a5821b5ba5fc100599fe1ad7f4969158f0/net/socket/client_socket_pool_manager.cc#L54-L58)). Test the pull requests with Drupal admin pages.
19. **Track only: native Windows (the companion freeunit-windows project).**
    - Estimate from that research, which is unpublished on 2026-10-07, so readers cannot check it yet: 10,000 to 18,500 new lines of C plus 4,000 to 8,000 changed lines. The staged path is a platform layer, a WSAPoll engine for bring-up, and then a ProcessSocketNotifications engine that needs Windows 11 or Server 2022 or later. The PHP module must be built with clang-cl to link the official windows.php.net builds. The first target is a foreground console process on x64.
20. **Track only: PHP worker mode ([issue 596](https://github.com/freeunitorg/freeunit/issues/596)).**
    - FreeUnit plans a PHP worker mode for 1.38.0: a PHP target member `"worker": true` and a `unit_handle_request()` loop, in which one PHP process serves many requests. A worker keeps application state between requests (research/freeunit-capabilities.md §2), so behaviour that depends on a fresh PHP request, such as settings changes and static caches, can differ from the script-per-request mode that this plan targets. Whether Drupal runs correctly in worker mode is unverified.
    - When 1.38.0 is tagged: add a per-project `worker` switch to the configuration schema, default off; test Drupal 11.4 and 12 under it (cron, settings changes, memory over 1,000 requests, Xdebug) before the tool offers it; and extend the parity claim in D18 only after that test.

Reports to other projects:

- freeunitorg/docs: extend `source/howto/drupal.rst` with uploads (`max_body_size` together with PHP's limits), TLS, `forwarded`, `trusted_host_patterns`, private and temporary directories, and a local development section. Remove `core/authorize.php` for Drupal 12 and `core/modules/statistics/statistics.php` for Drupal 11 and later (research/drupal-developer-requirements.md, requirement UNIT-08).
- drupal.org: the 12.0.0-beta1 release notes say PostgreSQL 19, while the code requires 18 (Report §6).
- Drupal CMS: a neutral variable that marks a managed local environment (requirement SET-07; M8).

## 8. Repository layout, contribution workflow and CI

### 8.1 Layout

```
freeunit4drupal/
  README.md
  LICENSE                       Apache-2.0
  docs/
    research-report.md          synthesis of research/ (2026-10-07)
    plan.md                     this plan; decisions D1 to D18 change only here
    phase0/E1.md ... E17.md     one file per experiment (M0)
    user/                       user documentation (from M2)
  research/                     the five research notes, frozen at 2026-10-07
  cmd/freeunit4drupal/          main package (M2)
  internal/
    config/                     native schema and merge rules
    ddev/                       .ddev reader, field mapping, settings.ddev.php
    drupal/                     version detection, settings templates
    unit/                       FreeUnit control-API client and configuration renderer
    routes/                     shared Drupal route template and its tests
    daemon/                     supervisor, back-off, logs
    php/                        runtime sets and ini generation
    services/                   mariadb, mailpit, cron
    tls/                        CA, leaves, trust stores (derived from mkcert)
    hosts/                      hosts-file helper (derived from DDEV)
  runtimes/                     static-php-cli recipes and unitd build scripts (M1)
  test/
    e2e/                        Drupal 10.6, 11.4 and 12.0 install and probe suites
    corpus/                     the 50 DDEV repositories and the runner (E11, M5)
  .github/workflows/
```

### 8.2 Rules for contributions

These rules come from the findings repository ([andypost/freeunit-drupal](https://github.com/andypost/freeunit-drupal), AGENTS.md). Every pull request is reviewed against them.

1. Write plain technical English: short sentences, one idea each, no idioms, no dashes used as punctuation, no marketing adjectives. Use exact names and numbers. Cite issues and pull requests as full URLs, never as bare numbers.
2. Anchor every factual claim. Use a URL with the date it was read, or `path:line@<commit>` for code read at a named commit. Read code at the upstream default branch at a named commit, never from a local working tree.
3. Give every finding a status: VERIFIED (read at a named revision), MEASURED (reproduced, with the conditions written down) or UNKNOWN. "Probably" is not a status. Mark unverified claims as unverified.
4. Never publish a speed or resource number without its baseline in the same sentence. Record the dataset, the database, the core count, the machine, the versions and the number of runs with every measurement.
5. Publish nothing private: no host names, home-directory paths, credentials, tokens or private project names.
6. Keep one source of truth. When a fact changes, edit the place that states it and delete the old statement. Decisions change only in docs/plan.md, with the date and the experiment or fact that changed them.
7. Send FreeUnit problems upstream to https://github.com/freeunitorg/freeunit. The tool carries a workaround only with a link to the upstream issue.
8. Contributions are under Apache-2.0. Code taken from mkcert (BSD-style licence) or DDEV (Apache-2.0) keeps its notice. Copy no code from AGPL or GPL projects such as Symfony CLI and Drupack.

### 8.3 Workflow

- Work starts from an issue. Each pull request does one thing and links its issue.
- One maintainer approves a normal change. Two approve any change to `tls/`, `hosts/`, `daemon/`, the `setcap` path or the release pipeline.
- CI must pass before merge. Maintainers squash-merge.
- Only maintainers tag releases. A release note lists the pinned FreeUnit version and the runtime set of each PHP minor version.

### 8.4 CI

- Documentation: Markdown lint; a check that rejects dash punctuation, home-directory paths and a list of private host names; a weekly link check.
- Go: `gofmt`, `go vet`, `staticcheck`, and unit tests with `-race` on linux-amd64.
- End to end: Ubuntu 24.04 x86_64 and arm64 runners, plus a macOS arm64 runner from M6. The matrix covers Drupal 10.6, 11.4 and 12.0; SQLite, MariaDB 11.8 and MySQL 8.4; and the PHP versions of D7. Each run includes the E17 probe suite and a check that `clean --all` leaves nothing behind.
- Runtime pipeline (M1): builds on a tag, plus a nightly check for new PHP patch releases.
- Corpus (M5): a weekly run over the 50 repositories that publishes the pass rate.
- Release: goreleaser, `SHA256SUMS`, cosign signatures and, from M6, notarization.

## 9. Risks

| ID | Risk | Mitigation | Stop condition |
|---|---|---|---|
| R1 | E1 fails: FreeUnit cannot load a static-php-cli shared `libphp`, or Xdebug does not load. | The D6 fallbacks: distribution packages on Linux; a static embed with Xdebug compiled in on macOS. | If no fallback works on macOS by 2026-11-20, the beta covers Linux and WSL2 only. If none works on Linux either, stop the tool and keep only T1 and the route template. |
| R2 | The 1.37.1 tag slips, or Apple Silicon breaks again. | Build and test against FreeUnit master in M0; section 7 items 1 and 2 upstream. | If no tagged release serves PHP on Apple Silicon by 2027-01-31, the beta ships without macOS. |
| R3 | One broken project blocks the shared instance (E3). | `"spare": 0`; validation per project; section 7 item 16. | None. The D2 fallbacks exist: validation per project in a scratch unitd (section 7 item 16), and one unitd per PHP minor version if E2 fails. |
| R4 | PHP security patches arrive late. | M1 automation; metric S6. | If two consecutive PHP security releases miss the 7-day target, make distribution packages the Linux default and show a warning on macOS until the pipeline is fixed. |
| R5 | Too few maintainers. | Two named maintainers before M1; the review rules in section 8. | If fewer than two people are active for 8 consecutive weeks before the beta, pause the project and publish its state. |
| R6 | The DDEV import works for too few projects. | The E11 corpus; a clear list of unsupported items. | If the corpus pass rate is below 60% at M5, label the import experimental and drop the "without edits" claim. |
| R7 | A speed or resource claim without a baseline reaches the docs. | Rule 4 in section 8; review. | None. |
| R8 | Security exposure through the CA key, the hosts helper, `setcap` or the macOS wildcard bind. | Key file mode 0600; elevation only per action; `clean --all`; the `source` guard; the security review in M8. | If E6 shows that a machine on the network reaches PHP despite the guard, remove the macOS wildcard mode. |
| R9 | Windows demand stays unserved: 64.5% of the Launcher's installer downloads are for Windows. | The WSL2 guide; M11 follows the freeunit-windows project. | None. Revisit at 1.0. |
| R10 | Another tool ships a Docker-free Drupal environment first: Drupack, lerd, Herd adding Drupal features, or DDEV on Apple containers. | Focus on FreeUnit parity and several PHP versions per server; T1. | If another Docker-free Drupal tool is listed by drupal.org before M8 and covers D2 to D11, offer FreeUnit support to that tool instead of shipping a separate beta. |
| R11 | Drupal CMS features fail under FreeUnit (Package Manager, Project Browser). | E7; documented limits. | None. Document the limit. |
| R12 | Licence problems in the runtime sets (PHP, OpenSSL, ICU and other libraries). | A NOTICE file per set; a licence review in M1. | If a library's licence forbids redistribution, drop that extension. |
| R13 | FreeUnit development slows or stops. It is a fork of an archived project, and its repository has 57 stars. | Pin FreeUnit versions; keep the "bring your own" mode. | If FreeUnit makes no release for 6 months, freeze features and decide whether to continue. |
| R14 | Signing costs or identity rules block distribution. | The Apple Developer Program (USD 99 a year) from M6; Windows signing decided in M11. | None. |
| R15 | The schedule slips: M1 to M8 need 26 to 42 person-weeks, and two people have about 35 to 39 before 2027-03-19. | Keep M9 out of the beta; cut T1 first. | If M2 misses its gate by more than 4 weeks, move the beta to a date before DrupalCon Edinburgh (2027-10-25). |

## 10. Open decisions for the maintainer

Only the maintainer can make these decisions. Each one names a default that applies if no decision is made by the stated milestone.

1. **Name.** Keep freeunit4drupal or choose another name, and pick a short command name. Check the Drupal trademark policy for "Drupal" in a project name (unverified). Decide before M8; the default is freeunit4drupal.
2. **Organisation.** freeunitorg (recommended in D16), the maintainer's personal account, or a new organisation. Decide before M2.
3. **Licence.** The repository's LICENSE file is Apache-2.0. Confirm it. Apache-2.0 is compatible with the mkcert (BSD-style) and DDEV (Apache-2.0) code that D1 reuses. The runtime sets also bundle PHP under the PHP License and libraries under their own licences (R12).
4. **Funding model.** Options: sponsorship (DDEV raises USD 10,134 a month, 84.4% of its goal, after one sponsor cut its support twice); a paid tier for services and team features (Herd Pro costs EUR 99 a year); a foundation; or volunteer time only. Decide before M8, because metric S6 needs paid or committed time.
5. **Relationship to the findings repository** ([andypost/freeunit-drupal](https://github.com/andypost/freeunit-drupal)). Keep findings about Drupal on FreeUnit there and link to them from here (recommended), or move them into this repository.
6. **Relationship to the Drupal CMS Launcher.** Ask for a listing among its alternatives, or propose FreeUnit as an engine that the Launcher could use for several sites and HTTPS. Either way, contact its maintainers at the Drupal Association.
7. **drupal.org Official Docs listing.** Decide whether to open the Official Docs issue after the beta (S8). DDEV's path to the recommendation took six years, so the realistic first goal is a listing among the alternatives.
8. **DDEV.** Decide whether to build T1, and whether to talk to DDEV's maintainers before the beta.
9. **People.** Name the two people who run Phase 0 before it starts (target 2026-10-08). Record by 2026-10-30 whether they commit as maintainers through the beta, and with how much time (section 1, condition part 2).

## 11. Evidence index

- research/ddev-incumbent.md: DDEV v1.25.4 architecture and start sequence, releases, platform modes, Docker licensing, funding (USD 114,757 of income in 2025; 84.4% of the sponsorship goal), adoption (telemetry and surveys), problems that users report, and the 2026 roadmap. Its split of Launcher downloads by platform is wrong (Report §6).
- research/competitors-and-native-precedents.md: Herd, Valet, Symfony CLI, the Drupal CMS Launcher, FrankenPHP, Laragon, lerd and the tools that died; PHP on Windows; State of Laravel data; business models. It does not cover Drupack.
- research/drupal-developer-requirements.md: Drupal 10 to 12 platform floors, the PHP extension matrix, settings conventions, Package Manager, testing and CI, services, permissions, host names and TLS, adoption channels, and the requirement IDs (PLAT, SQL, PHP, SET, TEST, SVC, PERM, HOST, UNIT, ADOPT, MKT).
- research/freeunit-capabilities.md: the FreeUnit 1.37.0 capability matrix with code anchors at 872bf041; PHP module behaviour; static files; routing; TLS; instances; the limits of supervision; packaging; the performance context; the ordered list of server changes; ten open questions.
- research/tool-architecture-options.md: the decision table and evidence for language, configuration and DDEV field mapping, host names, TLS, ports, PHP provisioning, databases, supervision, CLI and distribution; the cut of the first release; twelve experiments. Its claim that FreeUnit starts no spare worker by default is wrong (Report §6).
- docs/research-report.md: the synthesis, the resolution of sixteen conflicts between the notes, and the read-only checks run on 2026-10-07.
- The companion freeunit-windows research (2026-10-07, not yet published, so its figures are UNVERIFIED for readers): the size and staging of a native Windows port of FreeUnit, which M11 depends on.
- [andypost/freeunit-drupal](https://github.com/andypost/freeunit-drupal): the findings about Drupal on FreeUnit, and the rules for claims and measurements that section 8 adopts.
