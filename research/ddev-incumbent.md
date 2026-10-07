# DDEV, the incumbent Drupal local development environment (state on 2026-10-07)

Scope and conventions. All facts were observed on 2026-10-07 unless a date is given. Documentation quotes come from https://docs.ddev.com/en/stable/, which is built from the current release, v1.25.4. Code references are pinned to the Git tag v1.25.4 of https://github.com/ddev/ddev. Items dated before 2025 are marked "historical". Lines marked "Correction" say where the research briefing was wrong or out of date.

## 1. Architecture: what the CLI generates and runs, and how `ddev start` works

### Takeaway
DDEV is one Go binary. It writes docker-compose files for each project and, since v1.25.3 (2026-07-06), runs them through the Docker Compose Go library instead of a separate binary. Everything that serves a site runs in Linux containers. Each project gets a web container (nginx or Apache with php-fpm, plus Mailpit, Node.js and Composer inside it), a database container, and an XHGui container that starts only for profiling. All projects share one Traefik v3 router container and one ssh-agent container. The host runs only the `ddev` binary, a few helper binaries (Mutagen, docker-buildx, `ddev-hostname`), the mkcert certificate authority and the Docker provider.

### Cited Findings
**What runs where**
- "DDEV is a Go application that stores its configuration in files on your workstation." It mounts project files "into Docker containers" and "writes and uses docker-compose files for you". ([How DDEV Works](https://docs.ddev.com/en/stable/users/usage/architecture/), v1.25.4 docs)
- Per-project containers on the same page: `ddev-webserver` "runs `nginx` or `apache` and `php-fpm` for a single site". `ddev-dbserver` "handles MariaDB/MySQL/PostgreSQL database management" and is reachable as `db`. Add-on services such as phpMyAdmin, Solr, Elasticsearch or Memcached are optional. ([How DDEV Works: Container Architecture](https://docs.ddev.com/en/stable/users/usage/architecture/#container-architecture))
- Global containers, one of each: `ddev-router`, a reverse proxy that "looks up the hostname in the incoming URL, and routes it to the correct project's `ddev-webserver`", and `ddev-ssh-agent`, which holds the user's SSH keys for all projects after `ddev auth ssh`. ([same page](https://docs.ddev.com/en/stable/users/usage/architecture/#container-architecture))
- Web server types in code: `nginx-fpm` (the default), `apache-fpm` and `generic`. The `generic` type serves things such as FrankenPHP (an official add-on since v1.25.0) or non-PHP apps. ([pkg/nodeps/values.go at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/values.go); [v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0))
- Mailpit is not a separate container. It runs inside the web container as a supervisord program. ([mailpit.conf at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/containers/ddev-webserver/ddev-webserver-dev-base-files/etc/supervisor/conf.d/mailpit.conf))
- XHGui is a separate per-project container, `ddev-<project>-xhgui`, in the compose profile `xhgui`, so it starts only when used. ([app_compose_template.yaml at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/app_compose_template.yaml))
- Images pinned by v1.25.4: `ddev/ddev-traefik-router`, `ddev/ddev-ssh-agent`, `ddev/ddev-xhgui` and `ddev/ddev-utilities`, with tags built on 2026-08-31. The router image is built `FROM traefik:v3`. The web image is built from `debian:trixie-slim`; Debian 13 "Trixie" replaced Bookworm in v1.25.0. ([versionconstants.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/versionconstants/versionconstants.go); [router Dockerfile](https://github.com/ddev/ddev/blob/v1.25.4/containers/ddev-traefik-router/Dockerfile); [webserver Dockerfile](https://github.com/ddev/ddev/blob/v1.25.4/containers/ddev-webserver/Dockerfile); [v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0), 2026-02-03)
- Minimum versions in v1.25.4: Docker 25.0 (API 1.44), Podman 5.0, Mutagen 0.18.1, docker-buildx 0.17.0. ([versionconstants.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/versionconstants/versionconstants.go))
- Host side: `~/.ddev/bin` holds "private executable binaries it needs, like `mutagen` and `docker-buildx`". Mutagen keeps its data in `~/.ddev_mutagen_data_directory`. ([How DDEV Works: Global Files](https://docs.ddev.com/en/stable/users/usage/architecture/#global-files))
- `ddev hostname` "runs a special `ddev-hostname` or `ddev-hostname.exe` executable to elevate privileges" to edit the hosts file. On WSL2 the `.exe` comes from the `ddev-wsl2` package or the Windows installer. ([Commands: hostname](https://docs.ddev.com/en/stable/users/usage/commands/#hostname))
- The database "lives in a Docker volume, which means it does not appear in your DDEV project's filesystem". If you change Docker provider or reset it, you lose the data unless you saved a snapshot. ([FAQ](https://docs.ddev.com/en/stable/users/usage/faq/#where-is-my-database-stored-in-my-ddev-project))
- Generated files: per project `.ddev/.ddev-docker-compose-base.yaml`, `.ddev-docker-compose-full.yaml`, `.webimageBuild/`, `.dbimageBuild/` and a self-ignoring `.gitignore`; globally `.router-compose*.yaml` and `.ssh-auth-compose*.yaml`. "Most are regenerated, and thus overwritten, on every `ddev start`." ([How DDEV Works: Hidden Project Files](https://docs.ddev.com/en/stable/users/usage/architecture/#hidden-project-files))
- v1.25.3 moved to the Docker Compose library: "the separate `~/.ddev/bin/docker-compose` binary is no longer needed and can be removed". ([v1.25.3 notes](https://github.com/ddev/ddev/releases/tag/v1.25.3), 2026-07-06)

**`ddev start`, step by step.** Read from `DdevApp.Start()` in [pkg/ddevapp/ddevapp.go at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/ddevapp.go#L1497):
1. Check for an internet connection, because `*.ddev.site` names need public DNS. Warn if offline.
2. Choose the router, Mailpit and XHGui ports. A busy port is swapped for an ephemeral one with the message "Port %s is not available, using %s instead" ([router.go at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/router.go)).
3. Decide whether an empty database volume should be seeded from a snapshot.
4. Make sure the shared `ddev_default` Docker network exists. Remove duplicate project networks.
5. Run the `pre-start` hooks.
6. Render the compose YAML. Pull the images it needs.
7. Create upload directories. If Mutagen is on, write `mutagen.yml` and check the Mutagen volume.
8. Add the project to the global project list. Generate the nginx or Apache config and the PostgreSQL config. Merge global and project `homeadditions`.
9. Fix volume ownership in a helper container, in parallel with config checks. Render the compose YAML again. Add hosts-file entries if a hostname does not resolve. Start `ddev-ssh-agent`.
10. Build the per-project images from `.webimageBuild` and `.dbimageBuild` ("Building project images...").
11. Run `compose up -d` through the library.
12. Copy the mkcert root CA and the Traefik certificates into the `ddev-global-cache` volume.
13. Start the Mutagen sync and wait for the first flush.
14. Run `/start.sh` in the web container. Wait until web and db are healthy. Start `web_extra_daemons`.
15. Start the router in the background. A code comment says "waiting for it to become ready is the slowest part of startup".
16. Write the CMS settings files (for Drupal, `settings.ddev.php`). Run the project type's post-start action. Run the `post-start` hooks.

**Measured start time**
- DDEV's own benchmark: on macOS the median `ddev start` fell from 11.03 s (v1.25.2) to 7.91 s (v1.25.3). On Linux it fell from 18.03 s to 14.18 s. The post does not give the hardware or the number of runs. ([Release v1.25.3 blog post, Stas Zhuk](https://ddev.com/blog/release-v1-25-3/), 2026-07-06)
- v1.25.2 "Reduced fresh-router healthcheck wait". v1.25.3 removed "an unnecessary ~10-second delay" from `ddev stop` and ran post-healthcheck tasks concurrently. ([v1.25.2 notes](https://github.com/ddev/ddev/releases/tag/v1.25.2), 2026-04-22; [v1.25.3 notes](https://github.com/ddev/ddev/releases/tag/v1.25.3), 2026-07-06)
- Correction: the briefing listed Mailpit with the containers. Mailpit runs inside the web container. The rest of the briefing's container list is right, and the router is Traefik v3.

### Inferences
- One Drupal site means at least four running containers (web, db, router, ssh-agent), plus image builds on the first start and after each DDEV upgrade. The 8 to 14 s medians are for a project whose images already exist. A cold first start adds image pulls and builds.
- The router, the ssh-agent and the shared network are global singletons. That is how many projects share ports 80 and 443 at once.
- The start path has many places where the host and the container runtime must agree (DNS, ports, volumes, file sync, certificates). Each one is a failure point that a single native process avoids.

### Gaps
- DDEV publishes no official numbers for a cold first start (image pulls) or for the RAM the containers use. User reports are in section 7.

## 2. Feature inventory and the last four releases

### Takeaway
The current release is v1.25.4, published 2026-09-02. It supports 27 project types, including `drupal6` to `drupal12`, PHP 5.6 to 8.5, MariaDB 5.5 to 12.3, MySQL 5.5 to 9.7 and PostgreSQL 9 to 18. Its add-on registry lists 303 add-ons. The 2026 releases focused on start speed, diagnostics, a terminal dashboard, rootless runtimes (Podman, Docker rootless) and database seeding.

### Cited Findings
**Release cadence**
- From the GitHub releases API: v1.25.4 on 2026-09-02, v1.25.3 on 2026-07-06, v1.25.2 on 2026-04-22, v1.25.1 on 2026-02-23, v1.25.0 on 2026-02-03, v1.24.10 on 2025-11-04. There were nine releases in 2025 (v1.24.2 to v1.24.10). ([GitHub releases](https://github.com/ddev/ddev/releases))

**What changed in the last four releases**
- v1.25.1 (2026-02-23): a new interactive terminal dashboard ("Just type `ddev`"), plus fixes; "Users of v1.25.0 are encouraged to upgrade". ([notes](https://github.com/ddev/ddev/releases/tag/v1.25.1))
- v1.25.2 (2026-04-22): faster `ddev start`; new project types `wp-bedrock`, `asterios` and `joomla`; stopped projects now auto-start on `ddev exec`, `ssh`, `share`, `xhgui`, `pull`, `push` and `snapshot restore`; new `ddev utility port-diagnose`, `check-custom-config` and `tls-diagnose`; support for the WSL2 `virtioproxy` networking mode. ([notes](https://github.com/ddev/ddev/releases/tag/v1.25.2))
- v1.25.3 (2026-07-06): the Docker Compose library; faster start and stop; MariaDB 12.3 LTS; "Podman and Docker rootless are no longer experimental"; Node.js handling through `n`; a new `ddev dr` command for Drupal 11.4+ (the old `dr` alias for `drush` was removed); `ddev version` and `ddev config` now work when Docker is not running. ([notes](https://github.com/ddev/ddev/releases/tag/v1.25.3))
- v1.25.4 (2026-09-02): database seeding (`ddev start --seed-snapshot`, and an automatic `seed` snapshot) and `ddev start --reset-database`; MySQL 9.7 LTS; MySQL 8.0 and 8.4 images built from Docker Hardened Images; `.env.local` and global env files; global Dockerfiles in `~/.ddev/web-build/`; `ddev add-on update`; new MODX and Maho project types; apt and rpm packages moved to Cloudsmith (`packages.ddev.com`). A known issue: a new 403 page turns off directory listing for every project. ([notes](https://github.com/ddev/ddev/releases/tag/v1.25.4))
- v1.25.0 (2026-02-03), the base of the current line: Debian Trixie images; XHGui as the default profiler; new-project defaults of PHP 8.4, Node.js 24 and MariaDB 11.8; a per-user Windows installer that needs no admin account; `ddev share` with a new cloudflared provider; zstd snapshots; experimental Podman and Docker rootless; a `drupal12` type. It removed NFS mode, `ddev service`, `ddev nvm` and many old flags. ([notes](https://github.com/ddev/ddev/releases/tag/v1.25.0))

**Project types**
- 27 types in v1.25.4: asterios, backdrop, cakephp, codeigniter, craftcms, drupal, drupal6, drupal7, drupal8, drupal9, drupal10, drupal11, drupal12, generic, joomla, laravel, magento, magento2, maho, modx, php, shopware6, silverstripe, symfony, typo3, wordpress, wp-bedrock. ([apptypes.go at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/apptypes.go))
- In code, `drupal` is "an alias for latest stable (currently drupal11)" and is never auto-detected. `drupal12` forces PHP 8.5. `drupal11` and `drupal12` turn on Corepack. Older types pin PHP: drupal6 uses 5.6, drupal7 8.2, drupal8 7.4 (with MariaDB 10.4), drupal9 8.1. ([drupal.go at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/drupal.go#L431))
- The v1.25.0 notes say: "Added Drupal 12 project type (development branch). Drupal 12 has not yet been released". v1.25.3 added a "Drupal 12 (HEAD)" quickstart. ([v1.25.0](https://github.com/ddev/ddev/releases/tag/v1.25.0); [v1.25.3](https://github.com/ddev/ddev/releases/tag/v1.25.3))
- The Drupal quickstart is: `ddev config --project-type=drupal11 --docroot=web`, `ddev start`, `ddev composer create-project drupal/recommended-project`, `ddev composer require drush/drush`, `ddev drush site:install --account-name=admin --account-pass=admin -y`, `ddev launch`. Drupal CMS uses `ddev composer create-project drupal/cms`. ([Quickstart: Drupal](https://docs.ddev.com/en/stable/users/quickstart/#drupal))

**Language and database runtimes**
- PHP: 5.6, 7.0 to 7.4, 8.0 to 8.5. The default is 8.4. Only 8.2 to 8.5 are preinstalled in the web image; other versions are installed when the project image is built. ([php_values.go at v1.25.4](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/php_values.go))
- MariaDB: 5.5, 10.0 to 10.8, 10.11, 11.4, 11.8 (the default) and 12.3. On ARM64, 5.5 and 10.0 are not available. ([mariadb_values.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/mariadb_values.go); [mariadb_values_arm64.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/mariadb_values_arm64.go))
- MySQL: 5.5, 5.6, 5.7, 8.0, 8.4 and 9.7. On ARM64 only 5.7, 8.0, 8.4 and 9.7. ([mysql_values.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/mysql_values.go); [mysql_values_arm64.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/mysql_values_arm64.go))
- PostgreSQL: 9 to 18. The default for the postgres type is 14. ([postgres_values.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/nodeps/postgres_values.go))
- Node.js: the default is 24, managed by `n`. `nodejs_version` takes a major version, an exact version, or `auto`/`engine` to read a version file in the project. "A version installed by `n` is fixed at build time." ([Config: nodejs_version](https://docs.ddev.com/en/stable/users/configuration/config/#nodejs_version))
- Composer and Drush run inside the web container through `ddev composer` and `ddev drush`. The FAQ explains that the `ddev` prefix runs "that very command inside the web container". ([FAQ](https://docs.ddev.com/en/stable/users/usage/faq/))

**Drupal settings management**
- DDEV "creates a `sites/default/settings.ddev.php` and adds an include in `sites/default/settings.php`". "There are guards to prevent the `settings.ddev.php` from being active when the project is not running under DDEV, but it still should not be checked in and is gitignored." ([Managing CMS Settings: Drupal](https://docs.ddev.com/en/stable/users/usage/cms-settings/#drupal-specifics))
- The include that DDEV appends is guarded by `if (getenv('IS_DDEV_PROJECT') == 'true' && is_readable($ddev_settings))`. The compose template sets `IS_DDEV_PROJECT=true` in the web and db containers. ([drupal.go lines 76 to 78](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/drupal.go#L76); [app_compose_template.yaml](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/app_compose_template.yaml))
- The Drupal 11 template sets the database credentials, `hash_salt`, `skip_permissions_hardening = TRUE`, `trusted_host_patterns = ['.*']`, `config_sync_directory` to `sites/default/files/sync` when empty, SMTP to Mailpit on port 1025 for symfony_mailer, mailer_transport and symfony_mailer_lite, verbose error logging, and `aggregate_gc_threshold = 0`. The drupal12 template is the same. ([drupal11/settings.ddev.php](https://github.com/ddev/ddev/blob/v1.25.4/pkg/ddevapp/drupal/drupal11/settings.ddev.php))
- Ways to opt out: remove the `#ddev-generated` line, set `disable_settings_management: true`, switch to type `php`, or unset `IS_DDEV_PROJECT`. ([Managing CMS Settings](https://docs.ddev.com/en/stable/users/usage/cms-settings/))

**Hostnames, TLS and the router**
- "`*.ddev.site` is a wildcard DNS entry that always returns the IP address 127.0.0.1 (localhost)." If a name does not resolve, DDEV adds it to the hosts file, "which requires administrative privileges (sudo or Windows UAC)". ([Troubleshooting: DNS](https://docs.ddev.com/en/stable/users/usage/troubleshooting/))
- Some DNS servers, "mostly local Fritz!Box routers", refuse answers of 127.0.0.1 as DNS-rebinding protection. The docs call the hosts-file fallback "not the preferred behavior, as DDEV does not want to edit your system files". ([Networking: restrictive DNS](https://docs.ddev.com/en/stable/users/usage/networking/#restrictive-dns-servers-especially-fritzbox-fritzbox-routers))
- TLS uses mkcert. `mkcert -install` registers a local CA. Firefox and some other browsers keep their own certificate store and need a manual import. `ddev utility tls-diagnose` exists since v1.25.2. ([DDEV Installation](https://docs.ddev.com/en/stable/users/install/ddev-installation/); [v1.25.2](https://github.com/ddev/ddev/releases/tag/v1.25.2))
- Default ports: router 80 and 443, Mailpit 8025 and 8026, XHGui 8143 for HTTP. "All of the DDEV projects on a given computer can use ports 80 and 443 at the same time." ([Config](https://docs.ddev.com/en/stable/users/configuration/config/); [Troubleshooting: ports](https://docs.ddev.com/en/stable/users/usage/troubleshooting/))
- Extra names: `additional_hostnames`, `additional_fqdns` (which write hosts-file entries; the docs say "we recommend extreme care with this feature") and `project_tld`. ([Additional hostnames](https://docs.ddev.com/en/stable/users/extend/additional-hostnames/))

**Extensibility**
- Add-ons: `ddev add-on get`, `remove`, `list`, `search` and, since v1.25.4, `update`. The registry at https://addons.ddev.com lists GitHub repositories that carry the `ddev-get` topic. Its JSON feed reports 303 add-ons: 27 official and 276 contributed (feed updated 2026-10-07 00:51 UTC). Most-starred: ddev/ddev-drupal-contrib (141), ddev/ddev-browsersync (42), ddev/ddev-selenium-standalone-chrome (39), Lullabot/ddev-playwright (39). By repository creation year, 128 of the listed add-ons were created in 2026, 63 in 2025 and 48 in 2024. ([addons.json](https://addons.ddev.com/addons.json); [Using add-ons](https://docs.ddev.com/en/stable/users/extend/using-add-ons/))
- Hooks: 26 hook points, a `pre-` and a `post-` for each of start, stop, config, composer, exec, import-db, import-files, pull, push, share, snapshot, restore-snapshot and delete-snapshot. Task types are `exec` (in any container), `exec-host` and `composer`. ([Hooks](https://docs.ddev.com/en/stable/users/configuration/hooks/))
- Custom commands go in `.ddev/commands/<host|web|db>/` per project or in `~/.ddev/commands/` globally. Bundled ones include `drush`, `npm`, `artisan` and `cake`. Custom compose files (`.ddev/docker-compose.*.yaml`), custom Dockerfiles (`.ddev/web-build/`) and `web-entrypoint.d` scripts are also supported. ([How DDEV Works](https://docs.ddev.com/en/stable/users/usage/architecture/))
- The config reference documents 72 keys. ([Config](https://docs.ddev.com/en/stable/users/configuration/config/))

**Developer tools**
- Snapshots: `ddev snapshot` uses zstd since v1.25.0. Seeding and reset arrived in v1.25.4. ([v1.25.0](https://github.com/ddev/ddev/releases/tag/v1.25.0); [v1.25.4](https://github.com/ddev/ddev/releases/tag/v1.25.4))
- Sharing: `ddev share` with ngrok, which "requires an ngrok account (free or paid)", or with cloudflared. ([Sharing](https://docs.ddev.com/en/stable/users/topics/sharing/))
- `ddev launch` opens the site in a browser. `--print-url` exists since v1.25.4. ([v1.25.4](https://github.com/ddev/ddev/releases/tag/v1.25.4))
- Xdebug is off by default. The docs say "Most people use `ddev xdebug` and `ddev xdebug off` (or `ddev xdebug toggle`)". ([Config: xdebug_enabled](https://docs.ddev.com/en/stable/users/configuration/config/#xdebug_enabled))
- XHProf: `xhprof_mode` defaults to `xhgui` since v1.25.0, and `ddev xhgui` opens the UI. Blackfire is supported through `ddev blackfire`. ([Config: xhprof_mode](https://docs.ddev.com/en/stable/users/configuration/config/#xhprof_mode); [XHProf](https://docs.ddev.com/en/stable/users/debugging-profiling/xhprof-profiling/); [Blackfire](https://docs.ddev.com/en/stable/users/debugging-profiling/blackfire-profiling/))
- v1.24.9 (2025-11-04) made "`ddev utility` ... now the primary command (`ddev debug` moved to alias)". The aliases are now `ut`, `debug`, `d` and `dbg`. Subcommands include `diagnose`, `mutagen-diagnose`, `xdebug-diagnose`, `port-diagnose`, `tls-diagnose` and `check-custom-config`. ([v1.24.9 notes](https://github.com/ddev/ddev/releases/tag/v1.24.9); [Commands: utility](https://docs.ddev.com/en/stable/users/usage/commands/#utility))
- `ddev config --auto`: "Automatically run config without prompting". Each release note advises running it after an upgrade. ([Commands: config](https://docs.ddev.com/en/stable/users/usage/commands/#config); [v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0))
- Hosting integrations: `ddev pull` and `ddev push` for Acquia, Lagoon, Pantheon, Platform.sh and Upsun. ([Hosting providers](https://docs.ddev.com/en/stable/users/providers/))

**Performance modes**
- `performance_mode` takes `mutagen`, `none` or `global`. NFS support was removed in v1.25.0 ("Removed NFS support, use Mutagen"). Mutagen is on by default on macOS and on traditional Windows. ([performance_mode.go](https://github.com/ddev/ddev/blob/v1.25.4/pkg/config/types/performance_mode.go); [v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0); [Performance](https://docs.ddev.com/en/stable/users/install/performance/))

**Telemetry**
- On first start, or after an upgrade, DDEV asks whether to send usage data. It sends `Identify`, `Command` and `Project` events through Amplitude. `instrumentation_opt_in` defaults to `true` in the global config. `DDEV_NO_INSTRUMENTATION=true` turns it off, and nothing is sent when `CI=true`. The docs link a public dashboard at https://ddev.com/usage-stats/. ([Opt-In Usage Information](https://docs.ddev.com/en/stable/users/usage/diagnostics/); [Config: instrumentation_opt_in](https://docs.ddev.com/en/stable/users/configuration/config/#instrumentation_opt_in))

**Briefing corrections**
- Correction: there is no NFS mode any more. It was removed in v1.25.0 (2026-02-03).
- Correction: `ddev debug` is now `ddev utility` (since v1.24.9). The old name still works as an alias.
- Correction: the docs moved from ddev.readthedocs.io to docs.ddev.com. A request to https://ddev.readthedocs.io/en/stable/ returns HTTP 302 to https://docs.ddev.com/en/stable/ (observed 2026-10-07).

### Inferences
- For a Drupal developer, the features that carry daily work are few: `settings.ddev.php`, Composer and Drush passthrough, Mailpit, Xdebug, XHGui, snapshots, hostnames and TLS. Much of the rest serves the other 20+ project types.
- The add-on ecosystem is growing fast. Repositories created in 2026 (128) outnumber those created in 2025 (63) about two to one. This is a moat for any competitor.
- DDEV's Drupal defaults (`trusted_host_patterns = ['.*']`, Mailpit SMTP, `skip_permissions_hardening`) are a de facto spec. A competing tool that writes a different settings file will surprise users who move between tools.

### Gaps
- The docs give no per-feature usage numbers, for example the share of users on Mutagen. Section 6 covers what the published telemetry shows.

## 3. Platforms, with Windows in detail

### Takeaway
DDEV runs wherever a Docker-compatible engine runs: macOS (OrbStack recommended), Linux (Docker CE recommended) and Windows. On Windows the recommended mode is Docker CE inside a WSL2 Ubuntu distro, which DDEV's own installer sets up. "Traditional Windows" (running `ddev` in PowerShell or Git Bash) is still supported, but it needs Docker Desktop or Rancher Desktop, and both of those run on WSL2. So WSL2 is required in every Windows mode. Only the Linux distro is optional, and only in traditional mode.

### Cited Findings
**Minimum requirements**
- macOS Sonoma (14) or later, 8 GB RAM, 256 GB storage. Windows WSL2: 8 GB RAM, 256 GB. Traditional Windows: "Any recent edition of Windows Home or Windows Pro" with "Docker Desktop using the WSL2 backend". Linux: "Most distros and most versions work fine, on both AMD64 and ARM64", 8 GB RAM. ([System Requirements](https://docs.ddev.com/en/stable/#system-requirements))

**macOS**
- DDEV's labels for Docker providers: OrbStack "Recommended, easiest to install, most performant, commercial, not open-source"; Lima "Free, open-source"; Docker Desktop "Familiar, popular, not open-source, may require license, may be unstable"; Rancher Desktop "Free, open-source, simple installation, slower startup"; Colima "Free, open-source". ([Docker Installation: macOS](https://docs.ddev.com/en/stable/users/install/docker-installation/#macos))
- Lima "only mounts filesystems in your home directory unless you do further configuration", and Colima has the same limit. Podman rootless on macOS "can't use ports 80/443", so URLs need `:8443`. ([same page](https://docs.ddev.com/en/stable/users/install/docker-installation/))
- The FAQ marks OrbStack, Docker Desktop for Mac ("on Apple Silicon"), Colima and Rancher Desktop for macOS as "officially tested and supported". ([FAQ: Docker providers](https://docs.ddev.com/en/stable/users/usage/faq/#what-docker-providers-can-i-use))

**Linux**
- Docker CE is "Recommended". Docker Desktop for Linux is only "minimally supported". Docker rootless and Podman rootless became stable in v1.25.3. ([FAQ](https://docs.ddev.com/en/stable/users/usage/faq/#what-docker-providers-can-i-use); [v1.25.3](https://github.com/ddev/ddev/releases/tag/v1.25.3))
- "On Linux, including Windows WSL2, Docker is fast. Most people are happy with Linux performance and don't need to change anything." ([Performance](https://docs.ddev.com/en/stable/users/install/performance/))

**Windows: the three modes**
- The docs table: "Docker CE inside WSL2 (recommended)", best for "Most users, best performance", requires WSL2. "Docker Desktop / Rancher Desktop with WSL2", for users who already have one. "Traditional Windows", for a "PowerShell/Git Bash workflow", requires "Docker Desktop or Rancher Desktop (no separate WSL2 distro needed)". ([DDEV Installation: Windows](https://docs.ddev.com/en/stable/users/install/ddev-installation/#windows))
- Provider labels on Windows: Docker CE inside WSL2 is "Recommended, most popular, performant, and best-supported. The DDEV installer installs it automatically." Docker Desktop has "Extensive automated testing, but some performance and reliability problems. Not open-source; may require a license." Rancher Desktop is "Free, open-source. Manually tested on traditional Windows; no automated testing." Docker Desktop setup must keep "Use WSL 2 instead of Hyper-V" selected. ([Docker Installation: Windows](https://docs.ddev.com/en/stable/users/install/docker-installation/#windows))
- Rancher Desktop "is the only open-source Docker provider for traditional Windows". ([FAQ](https://docs.ddev.com/en/stable/users/usage/faq/#what-docker-providers-can-i-use)) Rancher's own docs say it "requires Windows Subsystem for Linux on Windows" and a machine with virtualization enabled. ([Rancher Desktop installation](https://docs.rancherdesktop.io/getting-started/installation/))
- Podman rootless on Windows, through Podman Desktop, "works but is less mature than on Linux". ([Docker Installation: Windows](https://docs.ddev.com/en/stable/users/install/docker-installation/#windows))

**Windows: what a user does, end to end** (all from [DDEV Installation: Windows](https://docs.ddev.com/en/stable/users/install/ddev-installation/#windows) unless noted)
1. In PowerShell: `wsl --install --no-distribution`, rebooting if asked, or `wsl --update` if WSL is already there. This step applies to every mode.
2. For the WSL2 modes: `wsl --install Ubuntu-26.04 --name DDEV`, then set a Linux user name and password, and check with `wsl -l -v` that it is the default distro. Traditional Windows skips this.
3. Download `ddev_windows_amd64_installer.exe` or `ddev_windows_arm64_installer.exe` and choose a mode. For WSL2 modes the installer lists the Debian-based distros it finds and "will automatically configure DDEV for your chosen Docker provider". Alternatives: `winget install --interactive DDEV`, `choco install ddev` (traditional Windows on AMD64 only), or silent flags such as `/S /docker-ce /distro=DDEV`.
4. Open the distro and "Run all `ddev` commands here", "never in PowerShell or Git Bash". "Store your projects inside WSL2 (e.g. `/home/<username>/projects`), not on `C:\` drive (`/mnt/c` in WSL2)", because it is "faster and avoids permission issues".
5. Certificates: the manual steps install mkcert's CA on the Windows side and share `CAROOT` into WSL2 through `WSLENV`. "This will set WSL2 to use the Certificate Authority installed on the Windows side, so your Windows-side browser can trust it." Firefox may need a manual import of `rootCA.pem`.
6. Hosts file: "Custom hostnames with a non-default TLD (non `ddev.site`) are managed via the Windows hosts file at `C:\Windows\System32\drivers\etc\hosts`, not within WSL2." The edit goes through `ddev-hostname.exe`. ([Commands: hostname](https://docs.ddev.com/en/stable/users/usage/commands/#hostname))
- The v1.25.0 installer "now uses per-user installation for WSL2 or traditional Windows (no admin account required)". ([v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0), 2026-02-03)
- The installer announcement says that setup on a fresh machine takes "about 10 minutes", and that "What used to require a privileged PowerShell script and manual WSL2 configuration now takes just a few clicks." ([Watch: new Windows installer, Randy Fay](https://ddev.com/blog/watch-new-windows-installer), 2025-07-21, updated 2025-10-06)

**Windows: file performance**
- "Mutagen offers a huge performance boost on macOS and Traditional Windows. ... It's enabled by default in those environments." ([Performance](https://docs.ddev.com/en/stable/users/install/performance/))
- "On macOS and Windows with Docker Desktop, allocated resources and mounted filesystem performance can be significant bottlenecks." ([same](https://docs.ddev.com/en/stable/users/install/performance/))
- On `/mnt/c`: "Although you could keep your project on the Windows filesystem and access it in WSL2 via `/mnt/c`, the performance is even worse than native Windows. It does work though, but don't do it. You'll be miserable." ([PhpStorm setup](https://docs.ddev.com/en/stable/users/install/phpstorm/))

**Windows: networking**
- WSL2 "Mirrored" networking needs the experimental `hostAddressLoopback=true` setting. VirtioProxy mode is supported "experimentally". ([System Requirements: Windows WSL2](https://docs.ddev.com/en/stable/#system-requirements))
- VirtioProxy is "the primary reason" Netskope users end up in that mode. "On some machines it works well; on others, the WSL2 distro has no internet access at all". "DDEV treats this mode as best-effort." ([Networking: WSL2 VirtioProxy](https://docs.ddev.com/en/stable/users/usage/networking/#wsl2-virtioproxy-mode-netskope-and-similar-vpns))
- "The ports are shared between Windows and WSL2, so a conflict on either side blocks DDEV." IIS is listed as a cause. ([Troubleshooting](https://docs.ddev.com/en/stable/users/usage/troubleshooting/))

**Other environments**
- GitHub Codespaces and other devcontainers are supported. In Codespaces "DDEV's router is not used". ([DDEV Installation: Codespaces](https://docs.ddev.com/en/stable/users/install/ddev-installation/#github-codespaces))

**Briefing corrections**
- Correction: "Traditional Windows" in DDEV does not mean Windows without WSL2. It means running `ddev.exe` from PowerShell or Git Bash, with no Linux distro, but the required Docker Desktop (WSL2 backend) or Rancher Desktop still runs on WSL2. WSL2 is required in all three modes, and it is "recommended" only in the sense of where to run `ddev` and keep the files.

### Inferences
- A Windows user faces about six steps, two shells (PowerShell and a Linux shell), a possible reboot, a Linux account and a second filesystem. This onboarding cost is the clearest opening for a tool that runs natively on Windows.
- Teams that keep code on `C:\` so that Windows IDEs can open it get DDEV's slowest path, by DDEV's own docs.
- Any corporate policy that blocks virtualization or WSL blocks every DDEV mode on Windows.

### Gaps
- I found no DDEV statement that gives a plan to drop traditional Windows. DDEV's telemetry puts traditional Windows at 3.7% of users and the WSL2 modes at about 21% (section 6).

## 4. Docker Desktop licensing, and how DDEV routes around it

### Takeaway
Docker Desktop is free only for organisations with fewer than 250 employees and less than USD 10 million in annual revenue, and for personal, education and non-commercial open-source use. Paid seats cost USD 9 to 24 per user per month. DDEV's docs steer users to free engines: Docker CE inside WSL2 is the Windows installer's default, and Lima, Colima and Rancher Desktop are listed as free on macOS. DDEV's recommended macOS provider, OrbStack, is itself commercial (USD 8 per user per month for business use).

### Cited Findings
- Docker Desktop is free for "Small businesses (fewer than 250 employees AND less than $10 million in annual revenue)", "Personal use", "Education" and "Non-commercial open source projects". A paid subscription is needed for "Professional use in larger organizations" and "Government entities". "The licensing and distribution terms for Docker and Moby open-source projects, such as Docker Engine, aren't changing." ([Docker Desktop license agreement](https://docs.docker.com/subscription/desktop-license/), observed 2026-10-07; the page shows no date)
- Prices: Personal $0; Pro $11 per user per month billed monthly or $9 billed annually; Team $16 monthly or $15 annually, for up to 100 users; Business $24 per user per month. ([Docker pricing](https://www.docker.com/pricing/), observed 2026-10-07)
- OrbStack is "Always free for personal use". Its Pro plan for "Business & commercial use" costs "$8 per user per month" or "$96 billed annually". ([OrbStack pricing](https://orbstack.dev/pricing), observed 2026-10-07)
- DDEV labels Docker Desktop "not open-source, may require license, may be unstable" on macOS and "Not open-source; may require a license" on Windows. ([Docker Installation](https://docs.ddev.com/en/stable/users/install/docker-installation/))
- How the docs route around it: the Windows installer installs Docker CE inside WSL2 automatically. On macOS, Lima, Colima and Rancher Desktop are listed as free and open source. Rancher Desktop is "the only open-source Docker provider for traditional Windows". Podman rootless is "a good fit where Docker is forbidden". ([Docker Installation](https://docs.ddev.com/en/stable/users/install/docker-installation/); [FAQ](https://docs.ddev.com/en/stable/users/usage/faq/#what-docker-providers-can-i-use))

- History: Docker announced the paid requirement on 2021-08-31. "It remains free for small businesses (fewer than 250 employees AND less than $10 million in annual revenue)". Larger businesses needed a paid plan "starting at $5 per user per month", with "a grace period until January 31, 2022". (historical) ([Docker press release](https://www.docker.com/press-release/docker-updates-product-subscriptions/), 2021-08-31) The thresholds have not changed since. The cheapest paid seat rose from USD 5 to USD 9 a month (Pro, billed annually), by comparing this release with today's price page.
- The next day, 2021-09-01, Randy Fay opened DDEV issue 3208, "Explore alternatives to Docker Desktop for the engine behind ddev". The opening post is about stability. On 2021-09-04 he linked an article titled "Docker and the Internet of Entitlement". On 2021-09-08 he wrote: "The plan is to move everybody on Windows to WSL2 anyway, regardless of Docker Desktop. So yes, Mac is the issue". (historical) ([issue 3208](https://github.com/ddev/ddev/issues/3208))
- Podman and Docker rootless support "opens DDEV to corporate environments where traditional Docker is not allowed". The same post still advises: "Use traditional Docker (OrbStack, Docker Desktop, Lima, Colima, or Rancher Desktop) unless your organization forbids it", and rates Podman "Slower than Docker". ([Podman and Docker Rootless in DDEV, Stas Zhuk](https://ddev.com/blog/podman-and-docker-rootless/), 2026-02-03)
- In practice, many users still run Docker Desktop: 55.6% of DDEV's macOS users and 35.1% of its WSL2 users, in the week to 2026-10-06. ([DDEV Live Usage Statistics](https://ddev.com/usage-stats/))
- Traditional Windows cannot avoid a desktop Docker product. Issue 7453 is titled "Traditional Windows 11 installation: `docker` client is required, needs Docker Desktop". ([issue 7453](https://github.com/ddev/ddev/issues/7453), 2025-07-14)
- Correction: the briefing assumed that Docker Desktop licensing pushes users away from DDEV. DDEV does not need Docker Desktop on macOS, Linux or WSL2, and its Windows installer sets up the free Docker CE. Licensing only matters on traditional Windows (Docker Desktop or Rancher Desktop) and for teams that choose Docker Desktop or OrbStack on macOS.

### Inferences
- A company with 250 or more employees that uses DDEV on macOS pays Docker (USD 9 to 24) or OrbStack (USD 8) per developer per month, or uses a free engine (Lima, Colima, Rancher Desktop). On Windows the free path is Docker CE in WSL2. A tool without Docker removes this licensing question entirely.
- DDEV itself charges nothing, but its recommended setup on macOS has a commercial licence. A competitor can make "no paid runtime" an explicit selling point.

### Gaps
- I found no survey or dataset that ties Docker Desktop licensing to developers leaving Docker-based tools. Reddit and Stack Overflow could not be searched: the search tool refuses both domains, and Reddit's JSON API returned HTTP 403.
- I found no 2025 or 2026 DDEV issue where a user reports that a Docker Desktop licence (rather than a technical fault) blocked them.

## 5. Governance and funding

### Takeaway
DDEV belongs to the DDEV Foundation, a Colorado nonprofit with US 501(c)(3) status (IRS ruling, March 2024). It was formerly the Localdev Foundation. Two paid contractors, Randy Fay and Stas Zhuk, write almost all the code. The Foundation's 2025 income was USD 114,757, all of it contributions, and it ended 2025 with about USD 10,000 in total assets. Live sponsorship is USD 10,134 a month, 84.4% of a USD 12,000 goal set for early 2025. Platform.sh (now Upsun) cut its support twice: from paying Randy's salary to EUR 3,000 a month (January 2025), then to USD 1,000 a month (January 2026). It handed the trademark to the Foundation in May 2026. The maintainers call the funding outlook "horrible". They name a maintainer in Ukraine, burnout and the maintenance-only state of Mutagen as risks.

### Cited Findings
**The Foundation**
- The Foundation page calls it a "Colorado (USA) nonprofit corporation", "formerly named the Localdev Foundation". Its funds pay "infrastructure costs and to pay part-time and full-time maintainers". ([DDEV Foundation](https://ddev.com/foundation/), observed 2026-10-07)
- ProPublica lists "Ddev Foundation", Palisade, Colorado, EIN 87-2477578, 501(c)(3), IRS ruling March 2024. Its Form 990-EZ for 2025, filed 2026-06-19, reports revenue USD 114,757, expenses USD 113,264 and total assets USD 10,067. ([ProPublica Nonprofit Explorer](https://projects.propublica.org/nonprofits/organizations/872477578), observed 2026-10-07)
- "The DDEV Foundation is now officially a US Nonprofit (501(c)(3)) organization." (historical) ([501(c)(3) post](https://ddev.com/blog/501c3/), 2024-03-31)
- A board of five was announced on 2025-12-05: Mike Anello (Treasurer), Jen Lampton, Benni Mack, Andrew Berry and Randy Fay. The post says "We don't want to depend on any single maintainer" and that the board meets "every two months". ([Board of Directors Established](https://ddev.com/blog/board-of-directors-established/), 2025-12-05)

**People and pay**
- DDEV runs on "two full-time maintainers", and "Both maintainers are currently paid, but perhaps not at a salary level that you would accept". ([Securing DDEV's Future](https://ddev.com/blog/sustainability-for-ddev/), dated 2025-06-10; the text was revised later)
- In January 2025 the advisory group set both maintainers' pay at USD 50 an hour with a USD 4,000 monthly cap. Randy's pay is also limited by "always trying to leave $6000 in the bank". ([February 2025 newsletter](https://ddev.com/blog/ddev-feb-2025-newsletter/), 2025-02-10)
- "In 2025, the DDEV Foundation (through your support) paid between $8,000-$10,000 per month to developers". ([Upsun thank-you post](https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/), 2025-12-08)
- Stas Zhuk was introduced as a maintainer on 2023-10-24 (historical). The appeal for him, updated 2026-07-27, says "his full-time maintainer role is only about 80% funded". ([Introducing maintainer Stas](https://ddev.com/blog/introducing-maintainer-stas/); [Let's fund Stas](https://ddev.com/blog/lets-fund-stas-maintainer/), first published 2024-08-01, modified 2026-07-27)
- Of the commits to the default branch since 2026-07-01, rfay wrote 141 and stasadev 51. The next authors are dependabot with 5 and two people with 2 each. (GitHub commits API, observed 2026-10-07, [commits](https://github.com/ddev/ddev/commits/main))
- Scale as the project states it: "about 17,000 weekly developer-users" (2025-06-10) and "nearly 20,000 developers and teams worldwide every week" (2025-12-08). ([Securing DDEV's Future](https://ddev.com/blog/sustainability-for-ddev/); [Upsun thank-you post](https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/))
- Historical origin: DDEV "originated within the company" Drud, which "shut down in 2021". ([About DDEV](https://ddev.com/about/), observed 2026-10-07)

**Platform.sh and Upsun**
- Historical: on 2022-05-27 Platform.sh began "providing a salary and benefits" package to Randy Fay and "acquired the DDEV trademark". ([Platform.sh becomes a lead sponsor](https://ddev.com/blog/platform-sh-becomes-a-lead-sponsor-of-ddev/), 2022-05-27)
- 2025-01-06: instead of employing Randy, Platform.sh would fund the Foundation with "€3000/month". The post calls this "a reduction of about 60%" that would still be "about 50% of our total income". It adds that other funding fell, "with several key sponsors having to back away". ([Platform.sh funding changes](https://ddev.com/blog/platform-sh-ddev-funding-changes/), 2025-01-06)
- 2025-12-08: Upsun announced it was "lowering their sponsorship level to $1000/month" from January 2026. Income would fall to "about $6300/month", "a shortfall of about $2000" a month. ([Upsun thank-you post](https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/), 2025-12-08)
- 2026-05-19: "The DDEV Foundation now owns the DDEV name outright". Upsun "Continued sponsoring DDEV at $1,000/month level!" ([Trademark transfer complete](https://ddev.com/blog/upsun-trademark-transfer-complete/), 2026-05-19)

**Money in and out** (the Foundation's own profit-and-loss statements, attached to advisory-group discussions)
- 2024 (historical): income USD 49,230.55 (business 40,278.26, individual 8,952.29). Contract services USD 48,505.95. Total expenses USD 54,816.53. Net USD -5,585.98. ([2024 P&L](https://github.com/user-attachments/files/18295467/DDEV.Foundation.Profit.and.Loss.2024.full.year.pdf), from [discussion 6853](https://github.com/orgs/ddev/discussions/6853))
- 2025: income USD 114,757.10 (business 61,437.96, corporate 9,238.69, individual 42,110.18, program fees 1,959.94). Contract services USD 104,711.73, which is 92% of the USD 113,264 expenses. Net USD 1,493.10. ([2025 P&L](https://github.com/user-attachments/files/24599003/2025.DDEV.Foundation.Profit.and.Loss.2025-12-31.pdf), from [discussion 7863](https://github.com/orgs/ddev/discussions/7863))
- January to June 2026: income USD 63,641.19 (business 48,234.07, individual 15,401.80). Contract services USD 58,611.40. Total expenses USD 62,249.88. Net USD 1,391.31. ([H1 2026 P&L](https://github.com/user-attachments/files/29516219/DDEV.Foundation.Profit.and.Loss.2026-06-30-16_59.pdf), from [discussion 8472](https://github.com/orgs/ddev/discussions/8472))
- The 2026-07-01 meeting notes say: "Current bank balance ~$7,000 after payroll; target buffer is ~$6,000". ([discussion 8472](https://github.com/orgs/ddev/discussions/8472))
- Live feed: "We're up to 84.4% of our $12,000/month goal ($10,134)". The parts: GitHub Sponsors to the ddev organisation USD 5,302 a month from 219 sponsors; Randy Fay's personal GitHub Sponsors USD 236 from 9; monthly invoices USD 3,662 from 8; annual invoices USD 10,553 a year from 9 (USD 879 a month); PayPal USD 55. The goal ID is still "2025-q1-growth". ([all-sponsorships.json](https://ddev.github.io/sponsorship-data/data/all-sponsorships.json), updated 2026-10-07 00:39 UTC)
- GitHub Sponsors tiers are USD 25, 100 and 1,000 a month. 210 sponsors are public, and the page shows no active goal. (GitHub GraphQL API, observed 2026-10-07; [sponsors page](https://github.com/sponsors/ddev))
- Trend in the monthly newsletters: about USD 6,964 a month projected after the Upsun cut (2025-12-15); USD 8,208 in January 2026, when sponsorship rose "from 58% to 68%" of the goal; USD 10,099 in September 2026. ([December 2025](https://ddev.com/blog/ddev-december-2025-newsletter/); [January 2026](https://ddev.com/blog/ddev-jan-2026-newsletter/); [September 2026](https://ddev.com/blog/ddev-september-2026-newsletter/))
- "DDEV's financial sustainability depends entirely on sponsorships (we have no other income)." ([Sponsorship data post](https://ddev.com/blog/sponsorship-data-droptimes/), 2026-05-05)
- Campaigns: "Let's Fully Fund Maintainer Stas" (2024-08-01, historical); the USD 12,000 goal for early 2025, still unmet; the appeal after the Upsun cut (2025-12-08); and the renewed appeal (2026-07-27) to "Increase income by about $2,000/month", for example "$100/month from 20 organizations". ([Let's fund Stas](https://ddev.com/blog/lets-fund-stas-maintainer/); [Upsun thank-you post](https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/))

**Risk statements by the maintainers**
- "Randy has been the leader, acting as what's commonly called a 'Benevolent Dictator for Life', or 'BDFL'." ([Securing DDEV's Future](https://ddev.com/blog/sustainability-for-ddev/))
- From the 2026 plans: the outlook for discretionary funding "remains horrible". "Mutagen is a critical part of DDEV, and it's in maintenance-only mode since Jacob went to work for Docker." Stas lives in "a very volatile situation in Ukraine". "Randy is not young and can always face new risks." Maintainers "can burn out". "Without the current expertise, we couldn't maintain the existing scope." ([2026 plans](https://ddev.com/blog/2026-plans/), 2026-01-16)
- The macOS and Windows test machines sit at Randy's home: open issue 6444 is titled "Windows and macOS testing capabilities should not be at Randy's house". ([issue 6444](https://github.com/ddev/ddev/issues/6444), open since 2024-07-29)

**Briefing corrections**
- Correction: the tax status is 501(c)(3), confirmed by ProPublica and the 2024 post.
- Correction: Platform.sh/Upsun did not stop sponsoring. It reduced its support twice and still pays USD 1,000 a month.
- Correction: there is no Open Collective channel. Money comes through GitHub Sponsors, invoices and PayPal.
- Correction: the maintainers are paid contractors who invoice the Foundation, not employees. Randy was a Platform.sh employee until the January 2025 change.

### Inferences
- Most of the jump from USD 49,000 (2024) to USD 115,000 (2025) is Platform.sh money that moved onto the Foundation's books when the salary ended. It is not new demand.
- The whole project runs on about USD 120,000 a year and two people. One USD 1,000-a-month sponsor is about 10% of income (my arithmetic: 1,000 of 10,134). A cash buffer of about USD 6,000 to 7,000 is under one month of maintainer pay.
- Sponsorship has recovered after each cut. Many agencies still use DDEV without paying for it, so the funding problem is structural, not cyclical.
- For a competitor, DDEV is hard to beat on features but fragile on people and money. A FreeUnit-based tool should not plan on DDEV disappearing, but it should expect DDEV to move slowly on anything outside its Docker core.

### Gaps
- The Foundation's formation date. ProPublica gives only the March 2024 ruling, and the Colorado registry returned HTTP 403.
- Whether the USD 4,000 monthly pay cap still applies in 2026. The statements do not name payees.

## 6. Adoption

### Takeaway
DDEV is the default local tool for Drupal. Drupal.org says "DDEV is the recommended local development tool for Drupal" and dates the choice to June 2024. In the Ironstar developer survey, DDEV's usage rose from 42% (2023) to 60% (2024). In 2025, 93% of DDEV users recommended it, against 51% for Lando. DDEV's opt-in telemetry shows about 20,000 weekly users and 30,682 unique users in September 2026. Drupal project types are about 38% of the project-type entries. By users, macOS is 53%, Linux 21.5%, WSL2 about 21% and traditional Windows 3.7%. Two Docker-free tools now exist in the Drupal ecosystem: the Drupal CMS launcher (Electron, static PHP, SQLite), which Drupal's own docs call "a trial tool", and Drupack, a single-binary alpha from September 2026.

### Cited Findings
**Repository, package and image numbers** (observed 2026-10-07)
- GitHub: 3,906 stars, 715 forks, 56 watchers, Apache-2.0, created 2017-02-01. 380 contributors, or 410 counting anonymous e-mail-only contributors (contributors API, last-page Link header). ([ddev/ddev](https://github.com/ddev/ddev))
- Release-asset downloads for the last seven releases (v1.24.9 to v1.25.4, November 2025 to September 2026): 1,519,266 in total. By type: Linux tarballs 750,345, `checksums.txt` 615,216, macOS tarballs 88,805, Windows installers 48,791, deb/rpm 13,296, Windows zip 2,500. The checksum and Linux counts point to scripts and CI, so this is not a user count. (GitHub releases API; [releases](https://github.com/ddev/ddev/releases))
- Homebrew: DDEV ships from its own tap (`brew install ddev/ddev/ddev`), not homebrew-core, so `formulae.brew.sh/api/formula/ddev.json` returns 404. The aggregate analytics list `ddev/ddev/ddev` with 4,524 installs in 30 days, 18,989 in 90 days and 85,314 in 365 days (rank 484). ([Homebrew install analytics, 365 days](https://formulae.brew.sh/api/analytics/install/365d.json))
- Docker Hub cumulative pulls, including CI: ddev/ddev-webserver 5,284,734; ddev/ddev-traefik-router 4,757,503; ddev/ddev-ssh-agent 3,189,627; ddev/ddev-xhgui 1,799,975. ([ddev-webserver on Docker Hub](https://hub.docker.com/r/ddev/ddev-webserver))

**DDEV's own telemetry** ([DDEV Live Usage Statistics](https://ddev.com/usage-stats/), "Data as of October 6, 2026"; "Most sections cover about the last 7 days")
- Quality warning on the page: Amplitude's quota means "The Amplitude-based stats below are significantly incomplete as a result" (see [issue 8614](https://github.com/ddev/ddev/issues/8614)). A 2023 post adds: "We have no way of knowing how many people opt out." ([Amplitude contributor training](https://ddev.com/blog/amplitude-ddev-analytics-contributor-training/), 2023-10-31, historical)
- Unique monthly users: 24,706 in November 2025, rising to 30,682 in September 2026.
- Weekly users over time: "about 10,000 weekly users, about doubling in the past year" (historical, [2023 review](https://ddev.com/blog/2023-review/), 2024-02-29); "nearly 20,000 developers and teams worldwide every week" ([2025-12-08](https://ddev.com/blog/upsun-thank-you-new-sponsors-needed/)); about 19,000 a week ([April 2026 newsletter](https://ddev.com/blog/ddev-april-2026-newsletter/), 2026-04-30); "DDEV has exceeded 20,000 weekly users some weeks" ([June 2026 newsletter](https://ddev.com/blog/ddev-june-2026-newsletter/), 2026-06-22).
- Operating system, by users: macOS 53.2% (13,072), Linux 21.5% (5,292), WSL2 19.2% (4,717), traditional Windows 3.7% (915), WSL2 mirrored mode 2.2% (542), Codespaces 17, WSL2 VirtioProxy 7.
- Docker provider on macOS: Docker Desktop 55.6% (7,297), OrbStack 32.7% (4,293), Colima 1,095, Rancher Desktop 276, rootless Lima 104, Lima 23. On WSL2: Docker CE 64.4% (3,102), Docker Desktop 35.1% (1,690), Rancher Desktop 13, Podman rootless 5. On Linux: Docker 95.5% (5,070), Docker Desktop 106.
- Project types: drupal11 18.6% (5,764), php 16.1% (4,989), drupal10 13.4% (4,134), then WordPress, Laravel, TYPO3 and Craft CMS. The page defines this chart as "Every project type DDEV is configured for". All `drupal*` types together are 11,617 of 30,913 entries, 37.6% (my sum of the page's counts: drupal11 5,764, drupal10 4,134, drupal9 1,097, drupal7 392, drupal8 127, drupal12 63, drupal 28, drupal6 12).
- PHP versions: 8.3 34.5%, 8.4 32.0%, 8.2 13.2%, then smaller shares for 8.5, 8.1 and 7.4. Databases: MariaDB 63.4%, MySQL 30.3%, PostgreSQL 6.3%.
- Commands in the past week: `exec` 6,474,279, `drush` 1,995,197, `mysql` 1,765,631. The page warns that these counts "are inflated by scripting and automated workflows".

**Drupal community position**
- Drupal.org: "DDEV is the recommended local development tool for Drupal." The page lists "Consistent, Docker-based environments", "Built-in Composer, HTTPS and mail support" and "Drush, Mailpit and Xdebug integration". It also offers "Install Drupal without Docker or review other options below", and says "While DDEV is recommended, Drupal is supported by other Docker-based tools." ([Local server setup, Wayback copy of 2026-05-20](https://web.archive.org/web/20260520170757/https://www.drupal.org/docs/develop/local-server-setup))
- "The Drupal community selected DDEV as the recommended local development environment in June 2024." ([Install Drupal using DDEV, last updated 11 May 2026, Wayback copy](https://web.archive.org/web/20260521111419/https://www.drupal.org/docs/getting-started/installing-drupal/install-drupal-using-ddev-for-local-development))
- The ideas issue "Document using DDEV as the recommended Drupal local development environment" was opened on 2018-04-24 and closed on 2024-06-28. (GitLab API; [ideas work item 2965681](https://git.drupalcode.org/project/ideas/-/work_items/2965681)) The 2024 survey dates the decision earlier: "late last year DDEV became the officially recommended solution for Drupal". So approval came in late 2023, and the issue closed in mid-2024.
- The Drupal CMS guide installs with `ddev config --project-type=drupal11 --docroot=web` and `ddev composer create-project drupal/cms`. ([Drupal CMS: Install with DDEV](https://project.pages.drupalcode.org/drupal_cms/get-started/ddev/), observed 2026-10-07)
- Drupal core's own `INSTALL.txt` does not mention DDEV. Its quick start "uses PHP's built-in web server and is not intended for production use": `composer create-project drupal/recommended-project drupal`, then `php -d memory_limit=256M vendor/bin/dr quick-start standard`. ([core/INSTALL.txt on 11.x](https://git.drupalcode.org/project/drupal/-/blob/11.x/core/INSTALL.txt), observed 2026-10-07)
- The drupal.org quick-start page: "The quick-start command is intended only for launching a local demo version of Drupal." (last updated 15 December 2024) ([Wayback copy of 2026-05-11](https://web.archive.org/web/20260511000849/https://www.drupal.org/docs/getting-started/installing-drupal/drupal-quick-start-command))

**Surveys**
- Ironstar Drupal Developer Survey 2024 (648 responses, 65 countries), historical: "late last year DDEV became the officially recommended solution for Drupal. Partly for that reason we can see a big increase in DDEV's usage from 42% last year to 60% this year." ([Wayback copy](https://web.archive.org/web/20251122215514/https://www.ironstar.io/devsurvey24/), results presented 2024-05-07)
- Ironstar 2025, by Michael Richardson, 2025-08-27 (753 responses, 58 countries): "93% of DDEV users recommend it. The nearest alternative recommendation is Homebrew, with 72%. Lando is towards the back with only 51%." The author reads one chart as showing that DDEV has "almost universal adoption within the Drupal community". The text gives no usage share for 2025. ([Wayback copy](https://web.archive.org/web/20260212040038/https://www.ironstar.io/devsurvey25/))
- Outside Drupal, a CraftQuest survey of Craft CMS developers reported DDEV at "72% Market Share". ([DDEV February 2026 newsletter](https://ddev.com/blog/ddev-february-2026-newsletter/), 2026-02-19)

**DrupalCon and contribution days**
- Atlanta 2025: Randy Fay worked Contribution Day. His report adds: "Docksal seems to be in trouble", because its maintenance "has recently fallen off". ([DrupalCon Atlanta 2025 post](https://ddev.com/blog/drupalcon-atlanta-2025/), 2025-04-03)
- Vienna 2025: Randy attended "thanks to sponsorship from Tag1 and Upsun". ([October 2025 newsletter](https://ddev.com/blog/ddev-october-2025-newsletter/), 2025-10-13)
- Chicago 2026: coder.ddev.com was used at Contribution Day: "Some folks used this for contributions at the DrupalCon Chicago Contribution Day." ([March 2026 newsletter](https://ddev.com/blog/ddev-march-2026-newsletter/), 2026-03-31)
- Funding gap upstream: "Key upstream groups like the TYPO3 Association and Drupal Association still are not figuring out how to fund DDEV." ([2025 review](https://ddev.com/blog/2025-review/), 2026-01-15)

**Docker-free tools in the Drupal ecosystem**
- Drupal CMS launcher: [drupal/cms-launcher](https://github.com/drupal/cms-launcher), MIT, created 2025-02-04, 46 stars, latest release v1.0.9 on 2026-03-23, last push 2026-04-30. The README says it needs no Docker, compiles PHP with static-php-cli, runs in Electron, stores the site in SQLite and has no HTTPS. It is maintained by "the Drupal Association and community contributors". It calls DDEV "Drupal's Docker-based local development platform of choice" and adds: "If you'd rather avoid Docker, Laravel Herd is a fine choice." (observed 2026-10-07)
- Launcher downloads from GitHub releases: macOS zip 6,568 and dmg 3,193, Windows exe 2,766, Linux AppImage 642 (13,169 installers, my sum). The trial download on drupal.org is form-gated, so other copies are not counted. (GitHub releases API, observed 2026-10-07; [cms-launcher releases](https://github.com/drupal/cms-launcher/releases))
- Drupal CMS docs: "If you're planning to set up and configure a site that you will move to a hosting provider, you should install Drupal CMS locally with DDEV instead. The Drupal CMS desktop launcher is currently a trial tool, because it uses a SQLite database". ([Drupal CMS launcher page](https://project.pages.drupalcode.org/drupal_cms/get-started/cms-launcher/), observed 2026-10-07)
- Drupack: [tresbientech/drupack](https://github.com/tresbientech/drupack), "Drupal as a single binary", GPL-2.0, created 2026-09-19, release 1.0.0-alpha5 on 2026-10-05, 4 stars. All 100 commits are by Théodore Biadala. The README mentions Caddy, SQLite, Drush and DDEV databases. (observed 2026-10-07)

**Comparisons with other tools** (observed 2026-10-07)
- GitHub stars: Laragon 5,427, Lando 4,243, DDEV 3,906, Valet 2,604, docker4drupal 1,265, Docksal 721. ([leokhoa/laragon](https://github.com/leokhoa/laragon); [lando/lando](https://github.com/lando/lando); [laravel/valet](https://github.com/laravel/valet); [wodby/docker4drupal](https://github.com/wodby/docker4drupal); [docksal/docksal](https://github.com/docksal/docksal))
- Latest releases: Lando v3.26.9 (2026-08-20), Laragon 8.7.0 (2026-08-14), docker4drupal 6.2.12 (2026-10-06), Valet v4.12.0 (2026-03-10), Docksal v1.19.0 (2024-05-24). Docksal has not released in over two years. ([Lando releases](https://github.com/lando/lando/releases); [Docksal releases](https://github.com/docksal/docksal/releases))
- Homebrew cask installs over 365 days: docker-desktop 527,473, orbstack 183,966, rancher 29,649, herd (Laravel Herd) 4,161, local (WP Engine) 2,924, lando 399, devkinsta 114. These tools mostly ship as direct downloads, so this is not like-for-like with the DDEV tap's 85,314. ([Homebrew cask analytics](https://formulae.brew.sh/api/analytics/cask-install/365d.json))

**Briefing corrections**
- Correction: the Drupal developer survey is Ironstar's (Michael Richardson), not DrupalEasy's. Mike Anello of DrupalEasy is the DDEV Foundation's Treasurer (section 5), so DrupalEasy sources are not independent of DDEV.
- Correction: the surveys do not publish DDEV, Lando, Docksal and native shares as text for 2025. The text gives only DDEV's 42% (2023) and 60% (2024) and the 2025 recommendation rates.
- Correction: drupal.org dates the recommendation to June 2024, but the decision was made in late 2023. The wording is "recommended", not "official".
- Correction: Homebrew counts exist, but under the tap name `ddev/ddev/ddev`, not under homebrew-core.

### Inferences
- About 11,600 Drupal project-type entries show up in roughly a week of DDEV telemetry, and the true number is higher because of opt-outs and the quota. That is the order of magnitude of the pool a Drupal-first competitor would compete for.
- Traditional Windows is only 3.7% of DDEV users. Windows developers have mostly moved to WSL2 (about 21%). A native Windows tool would compete for the WSL2 group, who have already accepted WSL2's setup cost, and for people who never started because of it. Telemetry cannot count the second group.
- Docker Desktop still has 55.6% of DDEV's macOS users. So DDEV's free-provider guidance has not moved most users, and many of them are exposed to Docker's licence terms.
- The Drupal project already ships a Docker-free tool (the launcher), but it limits it to trials (SQLite, no HTTPS, one site). The gap between "trial" and "DDEV" is the space a FreeUnit-based tool would fill. Drupack aims at the same space and started in September 2026.

### Gaps
- The usage shares in the Ironstar charts for Lando, Docksal and native tools are not in the page text, and the live pages return 404. I read Wayback copies only.
- Live drupal.org pages served a bot challenge. The discussion in the ideas issue could not be read: its GitLab notes API returned HTTP 401. The issue thread reportedly moved the wording from "official" to "default" to "recommended" after objections from Lando users, but I could not confirm this.
- I found no DDEV booth or DrupalCon sponsorship, but I did not read sponsor lists. I did not check DrupalCon Rotterdam 2026 (2026-09-28 to 10-01) sessions.
- No public user counts exist for Laravel Herd, Lando or DevKinsta.

## 7. Pain points that users and maintainers report

### Takeaway
DDEV's 2025 and 2026 issues show steady friction at the edges where Docker meets the host: provider and OS updates (Docker 28, Docker 29, macOS 15.7), corporate VPNs and proxies, DNS and the hosts file, and Windows filesystem placement. Most issues close fast, and many close with a docs pointer. The tracker has only 164 open issues, so pain shows up in closed issues, in docs pages written to work around it, and in benchmarks, not in the open count. Start takes about 12 to 21 s on macOS and 20 to 38 s on Windows in DDEV's own nightly runs, and a first install downloads about 0.7 GB of images.

### Cited Findings
**Start time, image downloads and disk**
- Compressed v1.25.4 image sizes on Docker Hub (amd64): ddev-webserver 424 MiB, ddev-dbserver-mariadb-11.8 148 MiB, ddev-traefik-router 75 MiB, ddev-ssh-agent 57 MiB. That is about 704 MiB for a default project before any per-project build (my sum). The arm64 web image is 418 MiB. ([ddev-webserver tags](https://hub.docker.com/r/ddev/ddev-webserver/tags), observed 2026-10-07)
- Each release ships new images, and every release note repeats: "Consider `ddev delete images` or `ddev delete images --all` after upgrading to free up disk space used by previous Docker image versions." Open issue 8865 asks to "Restructure the ddev-webserver Dockerfile so images share layers across releases". ([v1.25.4 notes](https://github.com/ddev/ddev/releases/tag/v1.25.4); [issue 8865](https://github.com/ddev/ddev/issues/8865), 2026-09-25)
- DDEV's nightly benchmark, medians I computed for 2026-08-31 to 2026-09-30 on the main branch (27 to 31 runs per leg). `ddev_start_cold_s` is "`ddev poweroff`, then `ddev start` to ready", with images already present. `drupal_install_s` is a browser-driven Drupal Umami install. ([history.ndjson](https://ddev.github.io/ddev/perf/history.ndjson); [metric definitions](https://github.com/ddev/ddev/blob/v1.25.4/perf/README.md); [dashboard](https://ddev.github.io/ddev/perf/))
  - macOS, Apple Silicon, with Mutagen: start 12.2 s (OrbStack), 14.4 s (Colima), 15.8 s (Lima), 16.4 s (Rancher Desktop), 21.0 s (Docker Desktop), 46.0 s (Podman rootless). Umami install 11.9 to 16.7 s.
  - Linux, Docker CE on a GitHub-hosted runner with cold caches: start 13.0 s, install 24.9 s.
  - WSL2 with Docker Desktop: start 19.9 s, install 43.7 s. WSL2 mirrored mode with Docker CE inside: start 38.4 s, install 45.9 s.
  - Traditional Windows with Docker Desktop: start 30.0 s, install 69.3 s.
  - DDEV warns that the legs run on different hardware and caches, so "the cross-platform bar chart is misleading if you read it as a platform ranking". ([Docker Provider Performance, 2026, Randy Fay](https://ddev.com/blog/docker-performance/), 2026-10-06)
- The same post: "On macOS with Mutagen, your choice of Docker provider is mostly not a performance decision anymore." OrbStack without Mutagen starts faster (9.2 s against 12.2 s) but installs about 25% slower. ([same post](https://ddev.com/blog/docker-performance/))
- Issue 7298: after the upgrade to v1.24.5, `ddev start` timed out while downloading docker-compose on slow networks. rfay: "20 seconds is a fairly long time." Fixed in v1.24.6. ([issue 7298](https://github.com/ddev/ddev/issues/7298), 2025-05-15 to 2025-09-03)
- The docs size example VMs at "4 CPUs, 6GB memory, 100GB storage" (Colima) and "a 100GB VM ... with 4 CPUs, 6GB memory" (Lima). Cleanup advice: "`ddev delete images` and `docker builder prune`", and on macOS and traditional Windows "`ddev mutagen reset` in each project". ([Docker Installation](https://docs.ddev.com/en/stable/users/install/docker-installation/); [Troubleshooting](https://docs.ddev.com/en/stable/users/usage/troubleshooting/))
- Databases live inside the provider's VM. Issue 7328: "All databases are empty, possibly due to unintentional OrbStack reset". ([issue 7328](https://github.com/ddev/ddev/issues/7328), 2025-05-23)

**File sync and filesystem placement**
- Issue 7888, opened by rfay: projects on the Windows filesystem under WSL2 lead to "terrible performance". DDEV now warns at start; the fix shipped in v1.25.0. rfay: "I do know that nobody reads docs." ([issue 7888](https://github.com/ddev/ddev/issues/7888), 2025-11-21 to 2025-12-04; [v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0))
- Issue 7015: Docker Engine 28.0.0 broke Mutagen ("CreateOrResumeMutagenSync Failure"). Fixed in v1.24.3 the next day. The blog called it "a fatal error, completely breaking DDEV's Mutagen support for Docker 28". ([issue 7015](https://github.com/ddev/ddev/issues/7015), 2025-02-24; [Open source for the win](https://ddev.com/blog/open-source-for-the-win/), 2025-02-26)
- macOS 15.7 caused freezes and Mutagen permission errors in `/var/folders/zz`. ([issue 7678](https://github.com/ddev/ddev/issues/7678), 2025-09-30; [issue 7805](https://github.com/ddev/ddev/issues/7805), 2025-11-06)
- On Mutagen, rfay wrote that "more than half of all DDEV users use mutagen on macOS, with nary a complaint" (2025-09-29). A user replied the next day: "Our Mac users use mutagen but they need a fine grained config to have it kind of robust". ([issue 7372](https://github.com/ddev/ddev/issues/7372))

**Provider and OS updates**
- Docker 28 and macOS 15.7, as above.
- Issue 8054, still open: "Docker 29 can't access or delete existing containers". rfay: it has "failed ever since Docker 29 arrived" in DDEV's own CI. ([issue 8054](https://github.com/ddev/ddev/issues/8054), opened 2026-01-23)
- Issue 8529: Podman 6.0.0 rootless on macOS broke `ddev mysql` with MySQL 8.0. Fixed in v1.25.3. ([issue 8529](https://github.com/ddev/ddev/issues/8529), 2026-06-25)
- Historical: issue 4809, "WSL2: Ports randomly in use after Windows update KB5025239". ([issue 4809](https://github.com/ddev/ddev/issues/4809), 2023-04-12)

**Networking: VPNs, proxies, DNS and hosts file**
- Issue 7209: "Certificate errors after switching to Zscaler VPN". The answer was a new docs section on packet-inspection VPNs. ([issue 7209](https://github.com/ddev/ddev/issues/7209), 2025-04-14 to 2025-04-23; [Networking](https://docs.ddev.com/en/stable/users/usage/networking/#corporate-packet-inspection-vpns-including-zscaler-and-global-protect))
- Issue 7044: "Versions 1.24.0 and above fail behind a corporate proxy." Fixed within a day. ([issue 7044](https://github.com/ddev/ddev/issues/7044), 2025-03-04)
- Issue 7412, on proxy support: stasadev answered "This is a general Docker question", and rfay "a PR is welcome". Closed with docs pointers. ([issue 7412](https://github.com/ddev/ddev/issues/7412), 2025-06-27 to 2025-07-14)
- Issue 8448: "Failed to start project because hostname cannot be added to the hosts file in Latest version of windows 11". ([issue 8448](https://github.com/ddev/ddev/issues/8448), 2026-06-04 to 2026-06-15)
- Issue 8567, "Unexplained failures on WSL2 in unexplained network". rfay: "DNS is not working in your WSL2 distro". ([issue 8567](https://github.com/ddev/ddev/issues/8567), 2026-07-08)
- Issue 8189: "Https not working with v1.25 (Windows, Firefox)". ([issue 8189](https://github.com/ddev/ddev/issues/8189), 2026-02-28 to 2026-03-19)
- The networking docs carry whole sections for Zscaler and GlobalProtect, Cloudflare WARP, corporate proxies, Netskope (WSL2 VirtioProxy) and Fritz!Box DNS rebinding. ([Networking](https://docs.ddev.com/en/stable/users/usage/networking/))
- Historical: issue 6308 hit Docker's address-pool limit above 20 projects. ([issue 6308](https://github.com/ddev/ddev/issues/6308), 2024-06-13) I found no 2025 or 2026 subnet-conflict issue.

**Ports 80 and 443**
- DDEV now swaps a busy port for a free one by itself (see section 1). In v1.25.4, "`ddev-router` remembers" the substitute port, so starting another project no longer rebuilds the router. ([v1.25.4 notes](https://github.com/ddev/ddev/releases/tag/v1.25.4))
- Issue 6945: "Getting 'Port 80 is busy' even if the port is not busy" on Colima. rfay: "This is in fact a problem with your system." ([issue 6945](https://github.com/ddev/ddev/issues/6945), 2025-01-29)
- Issue 7921: ESET endpoint security made every port look busy. rfay: "Changing DDEV for every harsh environment is a never-ending problem". v1.25.0 now skips the port check "when all ports appear busy". ([issue 7921](https://github.com/ddev/ddev/issues/7921), 2025-12-04 to 2026-01-28; [v1.25.0 notes](https://github.com/ddev/ddev/releases/tag/v1.25.0))
- The docs list the usual port holders: Apache, nginx, IIS, MAMP, VPNKit, Lando and macOS Screen Time content filtering. ([Troubleshooting](https://docs.ddev.com/en/stable/users/usage/troubleshooting/))

**Corporate restrictions and admin rights**
- Podman and rootless support targets "corporate environments where traditional Docker is not allowed" (section 4).
- Issue 8083: the Windows installer would not continue on Rancher Desktop with WSL2 because it could not detect Docker. ([issue 8083](https://github.com/ddev/ddev/issues/8083), 2026-01-30 to 2026-04-05)
- Hosts-file edits need elevation. Issue 7615: "homebrew linux: Failed to elevate command - sudo: ddev-hostname". Issue 6440: "WSL2: Use better escalation technique for editing /etc/hosts". ([issue 7615](https://github.com/ddev/ddev/issues/7615), 2025-09-12; [issue 6440](https://github.com/ddev/ddev/issues/6440), 2024-07-29 to 2025-06-27)
- Every Windows mode needs WSL2 and hardware virtualization (section 3).

**Beginners and support load**
- Open issue 8836 asks to "Include next steps or fixes wherever possible in error messages, aimed at agents". ([issue 8836](https://github.com/ddev/ddev/issues/8836), 2026-09-16)
- Support often moves to Discord or a call. In one proxy issue rfay offered a slot at "cal.com/randyfay". ([issue 6931](https://github.com/ddev/ddev/issues/6931), 2025-01-28)
- The Drupal project built a Docker-free launcher for evaluators. See section 6.

**What the maintainers say about non-Docker approaches**
- On Apple's container framework: "We definitely want to support it, but until these features are implemented, we can't use it with DDEV" (stasadev, 2025-06-11). "DDEV is built on the Docker API, and we use it in many places to create and remove things like Docker networks, containers, and volumes" (stasadev, 2025-06-26). "Apple is going its own way with native containerization, and we need access to the Docker API to create containers, networks, and volumes" (stasadev, 2026-03-31). ([issue 7372](https://github.com/ddev/ddev/issues/7372))
- rfay on the same issue: "If you can enable the docker shim and compose shim and get `docker ps` and `docker buildx build` to work, we might have a path" (2026-03-31). "I think the time will come that DDEV can use it!" (2026-07-03). A test gave "Socktainer/container: 30s Orbstack: 10s" for the Umami install (2026-08-05), and separate VMs mean projects cannot "share a docker volume" (2026-08-10). ([issue 7372](https://github.com/ddev/ddev/issues/7372))
- On Microsandbox: it "doesn't support the Docker API, or allow for image building (docker buildx) or compose" (2026-09-16), and "It's not a replacement for the Docker ecosystem" (2026-09-17). ([issue 8833](https://github.com/ddev/ddev/issues/8833))
- Historical, on Podman in 2020: "ddev is a wrapper on docker-compose. And if you have to install docker-compose... Why not just install docker?" The issue closed on 2025-12-09 after Podman support shipped. ([issue 2276](https://github.com/ddev/ddev/issues/2276), 2020-05-22)
- On Laravel Herd: stasadev's planning issue for a comparison post lists Herd as the "main competitor" to DDEV. ([ddev.com issue 406](https://github.com/ddev/ddev.com/issues/406), 2025-07-23, open)

**The issue tracker today** (GitHub API, observed 2026-10-07)
- 164 open issues and 14 open pull requests. ([issues](https://github.com/ddev/ddev/issues))
- Most-reacted open issues: 7372, Apple native containerization (74 reactions, 38 comments); then 7350, HTTP/3 (6); 3200, global hooks (6); 5696, config refactor (5); 2988, delete hooks (4). ([open issues sorted by reactions](https://github.com/ddev/ddev/issues?q=is%3Aissue%20is%3Aopen%20sort%3Areactions-desc); [issue 7372](https://github.com/ddev/ddev/issues/7372))
- The repository has 20 labels and none for an OS, a Docker provider, Mutagen, WSL2 or networking. Platform trouble can only be found by text search. ([labels](https://github.com/ddev/ddev/labels))
- Telemetry volume: issue 8614 is titled "Amplitude's new 2M-event/month cap vs. our ~45M/month usage". ([issue 8614](https://github.com/ddev/ddev/issues/8614), 2026-07-24, open)

**Briefing corrections**
- Correction: "VPN and Docker subnet conflicts" did not show up in 2025 and 2026. The VPN pain in that period is TLS interception (Zscaler, GlobalProtect, Netskope) and proxies.
- Correction: ports 80 and 443 are largely handled now. DDEV picks other ports on its own. What remains is odd-port URLs, Podman on macOS, and endpoint-security software.
- Correction: "DDEV stays on Docker" is more exactly "DDEV stays on the Docker API". It runs on Podman and rootless Docker, and works toward Apple's container framework through a Docker-API shim (socktainer). It rejects back ends without that API.

### Inferences
- The pain is not in DDEV's own code. It is in the layers DDEV cannot control: the container VM, the provider's release train, corporate network software, DNS and filesystem bridges. A single native process removes most of these layers at once.
- DDEV's answer to many environment problems is a docs page or a referral to support, which is reasonable for two maintainers but leaves locked-down corporate users on their own.
- Windows remains the slowest and most fragile platform in DDEV's own numbers: Umami installs take 44 to 69 s there, against 12 to 17 s on macOS. This gap is the strongest technical argument for a native Windows tool. (The legs use different hardware, so the size of the gap is indicative only.)

### Gaps
- Reddit, Stack Overflow and Drupal Answers could not be searched (the search tool refuses those domains, and Reddit's API returned HTTP 403). There are no forum examples in this section.
- No measured end-to-end first start with image pulls, and no RAM or CPU figures for a running project.
- I found no 2025 or 2026 issue about virtualization being disabled, Hyper-V or WSL2 being blocked by policy, or locked-down admin rights, although I searched for those terms.
- DDEV's main support channel is Discord, which cannot be searched.

## 8. Roadmap for 2026

### Takeaway
DDEV has no v2 and no Docker-free plan. The 2026 plan is incremental: AI integration and sandboxing, built-in certificates, Coder and devcontainer support, and possibly a "Freemium" model. The plan for the next minor, v1.26.0, makes PHP 8.5 the default for new projects. The most-wanted feature, Apple's native container framework, is being prototyped through a Docker-API shim and is not ready.

### Cited Findings
- The 2026 plan lists board topics including "Consider spending more on AI (Higher level of Claude Code plans)" and "Should we move toward a Freemium model with 'premium' features? What infrastructure and code would be required?" ([2026 plans](https://ddev.com/blog/2026-plans/), 2026-01-16)
- Technical items in the same plan: "Integration of mkcert CA without use of external `mkcert` tool"; a general AI strategy, noting that "Many platforms (like Laravel) have explicit MCPs"; and Coder support for subdomains. ([2026 plans](https://ddev.com/blog/2026-plans/))
- An MCP server pull request, "feat: implement DDEV Model Context Protocol (MCP) Server", was opened on 2025-09-08 and closed without merging. ([PR 7604](https://github.com/ddev/ddev/pull/7604))
- Open milestones, none with a due date: v1.25.5 (11 open, 10 closed), v1.25.6 (1 open), v1.26.0 (1 open). (GitHub API, observed 2026-10-07; [milestones](https://github.com/ddev/ddev/milestones))
- The v1.26.0 plan issue lists: "PHP 8.5 becomes default PHP version for new projects", "Drop PHP 8.2 from base image", "Node updated to current stable", and the global default for `omit-project-name-by-default` "goes to `true`". ([issue 8846](https://github.com/ddev/ddev/issues/8846), 2026-09-21, open)
- Apple container: exploratory PR 8656 says "This is not a merge candidate" (2026-08-02). A refactor to split the shared `ddev-global-cache` volume is open as PR 8895 (2026-10-02). ([PR 8656](https://github.com/ddev/ddev/pull/8656); [PR 8895](https://github.com/ddev/ddev/pull/8895); [issue 7372](https://github.com/ddev/ddev/issues/7372))
- A merged change lets "commands that don't use Docker run without it" (merged 2026-09-30). This removes Docker from commands like `ddev version`; it is not a Docker-free runtime. ([PR 8880](https://github.com/ddev/ddev/pull/8880))
- coder.ddev.com launched on 2026-03-11 as "free, experimental cloud-based DDEV workspaces powered by Coder", with "no local Docker, no local installation needed". Members of organisations that sponsor at USD 100 or more a month can sign in. ([Introducing coder.ddev.com, Randy Fay](https://ddev.com/blog/coder-ddev-com-announcement/), 2026-03-11, modified 2026-05-13)
- Image and start work in flight: shared image layers across releases ([issue 8865](https://github.com/ddev/ddev/issues/8865), 2026-09-25) and "Rebuild custom build services once after a DDEV upgrade" ([issue 8901](https://github.com/ddev/ddev/issues/8901), 2026-10-06). The 2025 review lists "Parallel Docker image pulls for faster project starts". ([2025 review](https://ddev.com/blog/2025-review/), 2026-01-15)
- Telemetry may have to change: Amplitude's new cap is 2M events a month against about 45M used. ([issue 8614](https://github.com/ddev/ddev/issues/8614), milestone v1.25.5)
- Five releases so far in 2026: 2026-02-03, 02-23, 04-22, 07-06 and 09-02. After v1.25.1 the gap has been about 8 to 11 weeks. ([GitHub releases](https://github.com/ddev/ddev/releases))

**Briefing corrections**
- Correction: no DDEV v2 is planned or discussed, as far as issue titles, milestones and plan posts show.

### Inferences
- DDEV's roadmap deepens its Docker base (rootless runtimes, image hygiene, cloud workspaces) instead of leaving it. A Docker-free competitor will not face a DDEV move into its space in 2026.
- coder.ddev.com targets the same "no local Docker" pain as a native tool, but through the cloud, with a sponsor gate and an experimental label. A local native tool and a cloud workspace serve different users, for example offline or locked-down laptops.
- A "Freemium" model is on the board's list. If DDEV adds paid features, a free competitor gains a pricing argument.

### Gaps
- I found no published outcome of the 2026 board topics (fundraising drive, Freemium, AI spending).
- I did not read the full 2026 planning brainstorm discussion ([discussion 7923](https://github.com/orgs/ddev/discussions/7923), 2025-12-04).
