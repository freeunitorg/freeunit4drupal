# freeunit4drupal: tool architecture options

Research date: 2026-10-07. FreeUnit code was read at `origin/master` 872bf041 (full SHA 872bf04170756e919211799ec5574000d628d521). Other code was read at the commit in each permalink. Every claim in the brief was treated as a hypothesis. Where a source refuted the brief, the text says so with the words "Brief said".

## 0. Decision table: what should freeunit4drupal choose for each decision?

### Takeaway
Build a Go single binary that drives one per-user FreeUnit instance, routes many projects by host name, serves them on `<project>.localhost`, issues its own TLS leaves, and supervises databases and Mailpit itself. FreeUnit cannot supervise non-libunit processes. It has no HTTP/2 yet. It has no package repositories or Homebrew formula yet; 1.37.0 only attaches Debian 13 amd64 debs to its GitHub release. That release also fails every libunit application request on Apple Silicon. So the tool builds its own PHP and FreeUnit binaries for every supported OS and CPU, and the macOS MVP waits for the next FreeUnit release.

### Cited Findings
- FreeUnit 1.37.0 on Apple Silicon rejects every shared memory segment, so applications never serve a request; the issue was opened 2026-10-06 and closed 2026-10-07 by a fix on master, and no release carries the fix yet. [freeunit issue 595](https://github.com/freeunitorg/freeunit/issues/595); [FreeUnit releases](https://github.com/freeunitorg/freeunit/releases)
- The FreeUnit docs say official RPM and DEB packages are "coming soon" and that FreeUnit is distributed through Docker images, source builds and unitctl binaries. [installation.rst:12-19@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/installation.rst#L12-L19)
- The `external` application type must complete the libunit handshake; an arbitrary binary never reports ready. See section 8. [src/nxt_external.c:144@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_external.c#L144)

### Inferences
| Decision | Options | Evidence (short) | Recommendation | Risk |
|---|---|---|---|---|
| 1. Language and shape | Go static binary; Rust reusing unitctl; PHP phar; hybrid | DDEV and Symfony CLI build with `CGO_ENABLED=0` and goreleaser; unitctl's client handles unix and TCP control sockets, its OpenAPI client is generated at build time with a Java jar, and it ships no Windows binary; the PHP SDK needs PHP 8.4 (section 1) | Go, CGO off, one binary per OS and architecture. Reuse mkcert-derived trust-store code (BSD licence) and DDEV's config struct and schema (Apache-2.0). Write a small control-API client. Allow PHP or shell for project-level custom commands | A third language next to FreeUnit's C and Rust; Go contributors come from DDEV, not from Drupal module authors |
| 2. Project config | Native file only; DDEV file only; native file plus live DDEV import | DDEV merges `config.*.yaml` in lexicographic order and ignores `config.local.yaml` in git; Drupal's DDEV include is gated on `IS_DDEV_PROJECT` (section 2) | Native `config.yaml` plus gitignored `config.local.yaml` in a tool directory (working name `.freeunit4drupal/`). With no native file, read `.ddev/config*.yaml` live with DDEV's merge rules. In DDEV mode set `IS_DDEV_PROJECT=true` and write a `settings.ddev.php` with native database coordinates and DDEV's signature | DDEV and the tool both rewrite `settings.ddev.php` when one checkout is used with both tools |
| 3. Host names | `*.localhost`; public wildcard (`*.ddev.site` style); dnsmasq plus `/etc/resolver`; hosts file; PAC proxy | Chromium, Edge and Firefox 84+ resolve every `*.localhost` name to loopback without DNS; macOS 26 resolves it in the OS; macOS 15 Safari does not; curl 7.85.0+ resolves it internally; systemd-resolved synthesizes it (section 3) | Default `<name>.localhost` and `*.<name>.localhost`. Fallback: opt-in hosts-file entries through an elevated helper. Keep `additional_fqdns` and `*.ddev.site` names for imported projects. Avoid `.test` and `.local` | Safari on macOS 15 and older and OS-resolver clients on Windows need the fallback |
| 4. Local TLS | mkcert binary; own CA in Go; reuse an existing mkcert CA; no TLS | mkcert leaf 2 years 3 months, CA 10 years; Valet leaf 396 days; Apple and Chrome 398-day limits exempt locally added roots; FreeUnit replaces a bundle in place since 1.37.0 and does SNI with an array of bundles (section 4) | Own CA in Go (mkcert-derived code), or reuse the mkcert CA root when it is already trusted. One leaf per project for `name` and `*.name`, 396 days, renewed on `start` when under 30 days. Upload with `PUT /certificates/<project>`. One `*:443` listener with an SNI array. HTTPS on by default once trusted; HTTP always available | Trust installation needs one elevation per machine; Firefox needs `certutil` |
| 5. Ports and instances | One instance per machine on 80/443; one per user; one per project on high ports | macOS lets a normal user bind ports below 1024 only on the wildcard address; FreeUnit binds listeners in its main process and drops capabilities in children since 1.37.0; FreeUnit ignores systemd sockets in practice (section 5) | One per-user unitd for all projects, routing by `Host`. Linux: `setcap cap_net_bind_service=+ep` on the tool's unitd, bind 127.0.0.1:80/443; macOS: bind `*:80`/`*:443` without root and refuse non-loopback clients with a `source` route; fallback 8080/8443 with no setup | macOS wildcard bind exposes the TCP port to the LAN; a broken shared instance stops all projects |
| 6. PHP provisioning | System packages; static-php-cli pairs shipped by the tool; Herd-style prebuilt pairs | Homebrew's `php` has no embed SAPI; the FreeUnit tap is an archived NGINX Unit 1.34.2 fork; FreeUnit 1.37.0 ships Debian 13 amd64 debs that depend on `libphp8.x-embed`; static-php-cli builds a shared embed library on Linux and macOS with a glibc 2.17 target that can load Xdebug (section 6) | Ship matched pairs per OS and architecture from one static-php-cli build: php CLI, `libphp` (shared embed), `php8.x.unit.so` built against it, and `xdebug.so`. PHP 8.3, 8.4, 8.5 in the MVP. Keep a "bring your own php-config and unitd" mode for distribution packagers | The project owns PHP security updates; build and test infrastructure for macOS is new |
| 7. Databases and services | Native binaries managed by the tool; existing local services; Docker or Podman hybrid | MariaDB ships Linux x86_64 and Windows binaries but no macOS binary; PostgreSQL portable builds exist for all three OSes; Mailpit ships single binaries for all three; Valkey has no Windows build (section 7) | MVP: MariaDB or MySQL from an existing install (Homebrew, distribution) with a per-project data directory and port, plus SQLite. Mailpit downloaded and supervised. PostgreSQL from portable builds after the MVP. After the MVP, use Docker only when the project needs services the tool does not have | MariaDB versions differ by host; DDEV snapshot restore needs version match |
| 8. FreeUnit as supervisor | external apps; schedules; tool supervision | `external` needs the libunit READY message; default `start_timeout` is 0 (wait forever); schedules send HTTP requests to applications (section 8) | The tool supervises services. FreeUnit supervises PHP workers. Use FreeUnit schedules for Drupal cron once they ship (planned for 1.38.0) | Schedules are an open pull request |
| 9. Workflow surface | DDEV-like command set; Herd or Valet style; minimal | DDEV has 28 hook points, `exec`/`exec-host`/`composer` tasks, and custom commands with `##` annotations (section 9) | DDEV-compatible verbs where they make sense on the host: `init start stop restart poweroff list describe launch logs exec drush composer php mysql psql import-db export-db import-files snapshot xdebug share trust self-update clean diagnose`. Hooks: `exec` and `exec-host` both run on the host. CI mode without CA, hosts or prompts | Users expect `ddev`-identical behaviour from imported projects |
| 10. Distribution | GitHub Releases plus script; Homebrew tap; apt/dnf; winget/Scoop/Chocolatey | DDEV: goreleaser, Homebrew tap, Cloudsmith deb/rpm, AUR, signed NSIS installer, notarized macOS binaries; Apple programme USD 99 per year; Microsoft Artifact Signing is limited to US and Canada for individuals (section 10) | First release: GitHub Releases with checksums, an install script and a Homebrew tap formula; notarize macOS binaries. Add deb/rpm repos next. Add winget/Scoop with the native Windows build. Pin one tested FreeUnit version per tool release | Signing costs and identity checks; FreeUnit release cadence (8 releases from April to October 2026) |

### Gaps
- No single source compares end-to-end startup time or memory of the candidate shapes. The experiment is in section 12.

## 1. Implementation language and shape

### Takeaway
Go is the best fit: the two closest precedents (DDEV, Symfony CLI) are Go binaries built with cgo off, and the most reusable code for this tool (DDEV's config model and hostname helper, mkcert's trust stores) is Go. FreeUnit's Rust `unitctl` offers little to reuse: a small control-socket client, a build-time OpenAPI generator that needs Java, Docker and process-scanning dependencies, and no Windows build.

### Cited Findings
- The unitctl workspace has three crates: `unit-openapi`, `unit-client-rs` and `unitctl`. [tools/unitctl/Cargo.toml@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/Cargo.toml)
- `unitctl` is version 1.37.0, needs Rust 1.95, and is Apache-2.0. [tools/unitctl/unitctl/Cargo.toml:4-8@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unitctl/Cargo.toml#L4-L8)
- `unit-client-rs` also needs Rust 1.95 and depends on `hyperlocal` 0.9 (unix sockets), `sysinfo` 0.39 (process scanning) and `bollard` 0.21 (Docker API). [tools/unitctl/unit-client-rs/Cargo.toml:6,27,35,46@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unit-client-rs/Cargo.toml#L27)
- Instance discovery scans the process table with `sysinfo` and parses the main process title, for example `unit: main v1.28.0 [/usr/sbin/unitd --log /var/log/unit.log --pid /var/run/unit.pid]`. [unitd_process.rs:45,154@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unit-client-rs/src/unitd_process.rs#L45)
- `unitctl instances new` deploys a FreeUnit Docker container through `bollard`. [instances.rs:7,122@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unitctl/src/cmd/instances.rs#L122); [unitd_docker.rs:11-13@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unit-client-rs/src/unitd_docker.rs#L11-L13)
- The unitctl commands are `instances`, `edit`, `import` (from a directory), `execute`, `status`, `listeners`, `apps` (list, restart) and `export`. [unitctl.rs:48-203@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unitctl/src/unitctl.rs#L48)
- The generated OpenAPI client source is not committed: `unit-openapi/src` is in `.gitignore`, and the build runs the openapi-generator jar with `java`. [tools/unitctl/.gitignore:14@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/.gitignore#L14); [openapi-generator-cli.sh:51,72@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/build/openapi-generator-cli.sh#L72)
- The client's `ControlSocket` type has three variants: a unix socket path, a Linux abstract socket, and a TCP URI (`http` or `https`). `hyperlocal` serves only the unix variants, and some socket tests are gated on `target_os = "linux"`. [control_socket_address.rs:16-19,109,159-161,411@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/tools/unitctl/unit-client-rs/src/control_socket_address.rs#L16-L19)
- The unitctl/1.37.0 release has four binaries and no Windows build: Linux x86_64 (15,040,576 bytes), Linux aarch64 (13,395,648), macOS x86_64 (13,304,760), macOS aarch64 (11,973,776). [unitctl/1.37.0 release](https://github.com/freeunitorg/freeunit/releases/tag/unitctl%2F1.37.0)
- The FreeUnit PHP SDK is `freeunit/unit-php-sdk` 0.9.0. It requires `php ^8.4`, Guzzle, Carbon, doctrine/inflector, `ext-curl`, and lists `friendsofphp/php-cs-fixer` as a runtime requirement. [composer.json@9807bdf3](https://github.com/freeunitorg/freeunit-php-sdk/blob/9807bdf3137614aba0d26f618a3c3d1540660f83/composer.json)
- The PHP SDK talks to the control socket through cURL with `CURLOPT_UNIX_SOCKET_PATH`. [UnitHttpClient.php:27@9807bdf3](https://github.com/freeunitorg/freeunit-php-sdk/blob/9807bdf3137614aba0d26f618a3c3d1540660f83/src/Http/UnitHttpClient.php#L27)
- static-php-cli's `micro` SAPI joins `micro.sfx` with a phar (`spc micro:combine your-app.phar`) into one executable that needs no PHP installation, on Linux, macOS and Windows. [sapi-reference.md@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/docs/en/guide/sapi-reference.md)
- DDEV builds with `CGO_ENABLED = 0`, cross-builds with goreleaser, builds an NSIS Windows installer with `makensis`, and signs Windows binaries with `signtool`. [Makefile:30,404-429@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/Makefile#L30); [.goreleaser.yml@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/.goreleaser.yml)
- Symfony CLI also builds with `CGO_ENABLED=0` and signs release artifacts with cosign. [.goreleaser.yml:17,74-79@a9011678](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/.goreleaser.yml#L17)
- Licences: DDEV is Apache-2.0, Symfony CLI is AGPL-3.0, mkcert and its fork `symfony-cli/cert` carry the mkcert BSD-style licence, Valet and static-php-cli are MIT. [DDEV LICENSE](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/LICENSE); [Symfony CLI LICENSE](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/LICENSE); [mkcert LICENSE](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/LICENSE); [symfony-cli/cert LICENSE](https://github.com/symfony-cli/cert/blob/e6d19f75c77497556b1467ad02bd2ca47088d20a/LICENSE)
- Contributor counts from the GitHub API on 2026-10-07 (anonymous contributors included): drush-ops/drush 525, ddev/ddev 410, laravel/valet 217, symfony-cli/symfony-cli 58, FiloSottile/mkcert 41. [ddev contributors API](https://api.github.com/repos/ddev/ddev/contributors?per_page=1&anon=true); [drush contributors API](https://api.github.com/repos/drush-ops/drush/contributors?per_page=1&anon=true); [valet contributors API](https://api.github.com/repos/laravel/valet/contributors?per_page=1&anon=true); [symfony-cli contributors API](https://api.github.com/repos/symfony-cli/symfony-cli/contributors?per_page=1&anon=true)
- FrankenPHP is the counter-example of a Go tool that embeds PHP: it links PHP through cgo, and its static builds cannot load dynamic extensions such as Xdebug. [compile.md@233f7936](https://github.com/php/frankenphp/blob/233f793654acf53be41385a4a29ac5e126cf6701/docs/compile.md); [static.md:14@233f7936](https://github.com/php/frankenphp/blob/233f793654acf53be41385a4a29ac5e126cf6701/docs/static.md#L14)
- Go has a Windows service package in its extended standard library, and Rust has the `windows-service` crate. [golang.org/x/sys/windows/svc](https://pkg.go.dev/golang.org/x/sys/windows/svc); [windows-service crate](https://crates.io/crates/windows-service)

### Inferences
- freeunit4drupal does not embed PHP. FreeUnit does. So the tool needs no cgo, and Go cross-compiles it to every target from one runner, as DDEV and Symfony CLI already do.
- Reusable Go code exists for the hard parts: CA creation and trust stores (mkcert, BSD licence), hosts-file elevation on Unix and Windows (DDEV's `ddev-hostname`, Apache-2.0), and the DDEV config struct and JSON schema for the import. Symfony CLI is AGPL-3.0, so copy ideas, not code, from it.
- The FreeUnit control API is small for this tool: `PUT /config/...`, `PUT /certificates/<name>`, `GET /status`, `GET /control/applications/<name>/restart`. A Go client over a unix socket is a short `net/http` transport. The reusable FreeUnit artifact is the OpenAPI document, not the Rust crates.
- Rust stays a valid option if the FreeUnit maintainers want one shared codebase with unitctl. It would need a Windows build, would carry `bollard` and `sysinfo` or need them removed, and draws on a small contributor pool.
- A PHP phar shell is attractive to Drupal developers, but the tool's first job is to install PHP, so a phar cannot bootstrap itself. A static-php-cli `micro` executable solves the bootstrap, but the core is a long-running supervisor of unitd, databases and Mailpit, with signal handling and Windows services later. That work fits Go better than PHP.
- Hybrid shape to recommend: a Go core, plus project-level extension points (hooks and custom commands) that run shell or PHP scripts with the project's own PHP CLI, as DDEV custom commands do. Drupal developers can contribute those without Go.

### Gaps
- Startup time and memory of the three shapes were not measured. Experiment: `hyperfine` on `version` and `status` for a Go build, a Rust unitctl build, and a `micro:combine` phar, on macOS arm64 and Linux x86_64.
- Whether the Go linker's automatic ad-hoc signature on darwin/arm64 is enough for binaries fetched with curl was not verified. Experiment: `codesign -dv` and first run on a clean macOS 15 and 26 machine, with and without the quarantine attribute.
- The FreeUnit maintainers' preference (Go tool next to C and Rust) is unknown. Ask on the FreeUnit tracker before the first commit.

## 2. Project configuration model and DDEV import

### Takeaway
Keep a native per-project YAML file with a gitignored local override, and read `.ddev/config*.yaml` live when no native file exists. Of DDEV's 52 project fields, most map to FreeUnit JSON, php.ini, the CLI environment or tool behaviour; about 15 exist only because of Docker; custom nginx or Apache files, `docker-compose.*.yaml` add-ons and XHGui are unsupported. A zero-edit Drupal import needs two things beyond the field mapping: the environment variable `IS_DDEV_PROJECT=true`, and a generated `settings.ddev.php` that points at the native database.

### Cited Findings
- DDEV's project schema has 52 top-level properties. [pkg/ddevapp/schema.json@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/schema.json)
- DDEV's docs live at docs.ddev.com. [mkdocs.yml:5@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/mkdocs.yml#L5)
- `config.*.yaml` files load in lexicographic order and override `config.yaml`; `config.local.yaml` and `config.*.local.yaml` are gitignored by default. [customization-extendibility.md:311-313@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/extend/customization-extendibility.md#L311-L313)
- `override_config: true` makes a `config.*.yaml` file replace values instead of merging them. [config.md:553@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/configuration/config.md#L553)
- Allowed values: `type` includes `drupal`, `drupal6` to `drupal12`, `backdrop`, `php`, `generic` and others; `php_version` 5.6 to 8.6; `webserver_type` `nginx-fpm`, `apache-fpm`, `generic`; `database.type` `mariadb`, `mysql`, `postgres`, with MariaDB 5.5 to 12.3, MySQL 5.5 to 9.7 and PostgreSQL 9 to 18. [schema.json@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/schema.json)
- Defaults: MariaDB 11.8, PHP 8.4, Node.js 24, PostgreSQL 14, Composer 2. [mariadb_values.go:6@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/nodeps/mariadb_values.go#L6); [php_values.go:21,51@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/nodeps/php_values.go#L21); [values.go:55@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/nodeps/values.go#L55); [postgres_values.go:4@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/nodeps/postgres_values.go#L4)
- For Drupal types DDEV fills the settings template with database name `db`, user `db`, password `db`, host `db` and the container's internal port. [drupal.go:46-55@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/drupal.go#L46-L55)
- The Drupal 11 `settings.ddev.php` template sets the database array, `hash_salt`, `skip_permissions_hardening = TRUE`, `trusted_host_patterns = ['.*']`, a default `config_sync_directory` of `sites/default/files/sync`, Symfony Mailer transports to `localhost:1025`, verbose error logging and `aggregate_gc_threshold = 0`. [drupal11/settings.ddev.php@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/drupal/drupal11/settings.ddev.php)
- The block DDEV appends to `settings.php` includes `settings.ddev.php` only when `getenv('IS_DDEV_PROJECT') == 'true'` and the file exists. [drupal11/settings.php:892-893@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/drupal/drupal11/settings.php#L892-L893)
- DDEV rewrites a settings file only when it still carries the `#ddev-generated` signature, and it writes a `.gitignore` beside `settings.php` that ignores `settings.ddev.php`. [drupal.go:177@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/drupal.go#L177); [nodeps/values.go:41@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/nodeps/values.go#L41); [apptypes.go:376@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/apptypes.go#L376)
- The web container environment includes `IS_DDEV_PROJECT=true`, `DDEV_PRIMARY_URL`, `DRUSH_OPTIONS_URI=$DDEV_PRIMARY_URL`, `TZ`, `DDEV_DOCROOT`, `DDEV_PHP_VERSION`, `DDEV_PROJECT`, `DDEV_SITENAME`, `DDEV_TLD`, `DDEV_FILES_DIRS` and more. [app_compose_template.yaml:194-257@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/app_compose_template.yaml#L194-L257)
- DDEV's PHP 8.4 php.ini sets `max_execution_time = 600`, `memory_limit = 1024M`, `post_max_size = 100M`, `upload_max_filesize = 100M` and `sendmail_path` to `mailpit sendmail` on 127.0.0.1:1025. [php.ini:407,433,701,853,1093@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/containers/ddev-webserver/ddev-php-files/etc/php/8.4/fpm/php.ini#L433)
- DDEV's Drupal 11 nginx config passes any existing `.php` file to PHP-FPM, not only `index.php`. [nginx-site-drupal11.conf:50-54@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/webserver_config_assets/nginx-site-drupal11.conf#L50-L54)
- The FreeUnit Drupal how-to denies hidden and configuration files, runs `index.php` and a few core scripts directly, returns 404 for other `.php` files, and ends with a `share` whose `fallback` runs `index.php`. It warns that the order matters and that a share placed first would serve `settings.php` as text. [howto/drupal.rst:30-175@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/howto/drupal.rst#L30-L175); [published how-to](https://docs.freeunit.org/howto/drupal/)
- An open FreeUnit pull request adds a Drupal core dev kit image whose config uses the same route pattern, PHP admin options (`memory_limit` 1G, uploads 64M) and `targets` `direct` and `index`. [pkg/docker/drupal/unit-drupal.json in PR 453](https://github.com/freeunitorg/freeunit/blob/3733c7287f173042e8dbff9f4d87f662626a31b3/pkg/docker/drupal/unit-drupal.json); [PR 453](https://github.com/freeunitorg/freeunit/pull/453)
- FreeUnit's default `max_body_size` is 8388608 bytes (8 MB). [configuration/index.rst:5850-5857@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L5850-L5857)
- FreeUnit routes can match `host` with wildcards such as `*.example.com` and with negations, and can match the client `source` address. [configuration/index.rst:911,944,1389,1420-1423@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L1389)
- An application of type `php 8.3` selects the loaded PHP module whose version matches the prefix; modules are sorted in descending order, so `php` alone selects the highest version. [src/nxt_application.c:1674-1716@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_application.c#L1674-L1716)
- Applications accept `stdout` and `stderr` file options. [src/nxt_conf_validation.c:1367-1372@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_conf_validation.c#L1367-L1372)
- `upload_dirs` targets `ddev import-files` and, with Mutagen, is bind-mounted instead of synced; Drupal's default is `sites/default/files`. [config.md:711-713@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/configuration/config.md#L711-L713)

### Inferences

**DDEV field mapping.** "Maps" means the tool honours the field with the same meaning. "Partial" means a subset works or the meaning changes. "Irrelevant" means the field only exists because of containers. "Unsupported" means the tool warns and continues. The meaning column follows [schema.json](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/schema.json) and the [config reference](https://docs.ddev.com/en/stable/users/configuration/config/).

| Field | DDEV meaning | freeunit4drupal handling | Class |
|---|---|---|---|
| `name` | Project name; host name prefix | Project name and `<name>.localhost` | Maps |
| `type` | Project type | `drupal8` to `drupal12` and `drupal` map to the Drupal route set; `drupal6`, `drupal7` and `backdrop` need PHP 7.x or 5.6 builds and older rules; other types are out of scope | Partial |
| `docroot` | Relative docroot | `share` root and `targets.*.root` | Maps |
| `php_version` | PHP version | Application `type` `php X.Y` and the matching CLI; only versions the tool ships (8.3 to 8.5 in the MVP) | Partial |
| `webserver_type` | nginx-fpm, apache-fpm, generic | Replaced by FreeUnit routes; `apache-fpm` projects lose custom `.htaccess` rules | Partial |
| `database.type`, `database.version` | Engine and version | Native engine at that version when available, else the nearest installed version with a warning | Partial |
| `dbimage`, `webimage`, `dbimage_extra_packages`, `webimage_extra_packages` | Container images and Debian packages | Ignored; print the package list as a hint | Irrelevant |
| `additional_hostnames` | Extra names under `project_tld` | `<h>.localhost` names in the route `host` match and the certificate SAN; `*` entries allowed | Maps |
| `additional_fqdns` | Extra full names | Route `host` match and SAN; resolution through the hosts-file fallback | Maps |
| `project_tld` | TLD, default `ddev.site` | Kept as an alias set (`<name>.ddev.site`) besides `.localhost` | Partial |
| `use_dns_when_possible` | DNS first, hosts file otherwise | `false` means always write hosts entries for non-`.localhost` names | Maps |
| `bind_all_interfaces` | Bind on all interfaces | Listener address `*` instead of loopback | Maps |
| `router_http_port`, `router_https_port` | Shared router ports | Listener ports | Maps |
| `host_webserver_port`, `host_https_port` | Direct container ports | No containers; ignored | Irrelevant |
| `host_db_port` | Fixed database port on the host | Per-project database port | Maps |
| `mailpit_http_port`, `mailpit_https_port`, `host_mailpit_port` | Mailpit UI ports | Mailpit UI port, or a FreeUnit `proxy` route | Partial |
| `xhgui_http_port`, `xhgui_https_port`, `host_xhgui_port`, `xhprof_mode` | XHGui service and XHProf mode | Not in the MVP | Unsupported |
| `composer_root` | Directory with composer.json | Working directory of `composer` | Maps |
| `composer_version` | Composer major or exact version | Downloads that composer.phar | Maps |
| `nodejs_version`, `nodejs_root` | Node.js version and root | Node through fnm in that directory | Maps |
| `corepack_enable` | Run `corepack enable` | Same, in the fnm-managed Node | Maps |
| `timezone` | Container and PHP time zone | `date.timezone` in php.ini and `TZ` for workers and CLI | Maps |
| `web_environment` | Web container variables | FreeUnit application `environment` and the CLI environment | Maps |
| `hooks` | Tasks around commands | `exec-host` as is; `exec` and `composer` run on the host with the project environment; `service:` other than `web` is limited | Partial |
| `fail_on_hook_fail` | Stop `start` on hook failure | Same | Maps |
| `upload_dirs` | Targets of `import-files` | Targets of `import-files`; also PHP-execution deny rules in the route | Maps |
| `disable_upload_dirs_warning`, `performance_mode` | Mutagen settings | Native file system, no sync | Irrelevant |
| `no_project_mount` | Do not mount the project | Ignored | Irrelevant |
| `omit_containers` | Skip `db` or `ddev-ssh-agent` | `db`: do not manage a database | Partial |
| `default_container_timeout` | Wait for containers | Service start timeout | Partial |
| `disable_settings_management` | Do not write CMS settings | Do not write `settings.ddev.php` | Maps |
| `ddev_version_constraint` | Required DDEV version | Warn only; a native `tool_version_constraint` replaces it | Irrelevant |
| `working_dir` | Default directories for `exec` | `web` value sets the working directory of host commands | Partial |
| `web_extra_daemons` | Extra processes in the web container | Supervised host processes; container paths in the command may break | Partial |
| `web_extra_exposed_ports` | Extra ports through the router | FreeUnit `proxy` route to the local port | Partial |
| `share_default_provider`, `share_provider_args`, `ngrok_args` | Share provider and arguments | `share` command | Maps |
| `xdebug_enabled` | Start with Xdebug | Start with the Xdebug ini | Maps |
| `override_config` | Merge or override | Same merge rules | Maps |

Files beside `config.yaml`:

| Path | Handling | Class |
|---|---|---|
| `.ddev/php/*.ini` | Appended to the application's php.ini (`options.file`) and to the CLI scan directory | Maps |
| `.ddev/mysql/*.cnf`, `.ddev/postgres/` | Included in the per-project database config | Partial |
| `.ddev/nginx_full/`, `.ddev/nginx/`, `.ddev/apache/` | Detected and reported; rules must be translated to FreeUnit routes by hand | Unsupported |
| `.ddev/docker-compose.*.yaml`, add-on metadata | Detected and listed; optional Docker hybrid runs them | Unsupported natively |
| `.ddev/web-build/`, `.ddev/db-build/`, `.ddev/homeadditions/`, `.ddev/traefik/` | Container-only | Irrelevant |
| `.ddev/commands/host/*`, `.ddev/commands/web/*` | Run on the host, see section 9 | Partial |
| `.ddev/db_snapshots/` | Restore needs a matching engine and version, see section 7 | Partial |

**Implicit Drupal behaviour a zero-edit import must reproduce:**
1. `IS_DDEV_PROJECT=true` in the FreeUnit application `environment` and in every CLI command the tool runs. Without it Drupal never loads `settings.ddev.php`.
2. A generated `settings.ddev.php` that keeps DDEV's `#ddev-generated` signature, with host 127.0.0.1 and the per-project port (or a unix socket), the same `hash_salt` rule, `trusted_host_patterns`, `config_sync_directory` default and Mailpit settings. Because the file is gitignored and signed, DDEV and the tool can both regenerate it.
3. `DDEV_PRIMARY_URL` and `DRUSH_OPTIONS_URI` set to the native URL, so Drush builds correct links.
4. php.ini parity: `memory_limit = 1024M`, `max_execution_time = 600`, 100M uploads, and `sendmail_path` to Mailpit. FreeUnit also needs `settings.http.max_body_size` raised from 8 MB to at least 100 MB, or uploads above 8 MB fail with 413.
5. Route parity. FreeUnit's how-to is stricter than DDEV's nginx: it runs only `index.php` and listed core scripts. A contributed module with its own PHP entry point would get 404. The tool should allow extra `direct` paths in config.

**Native schema sketch** (working directory name `.freeunit4drupal/`; `config.local.yaml` beside it is gitignored and merged last):

```yaml
name: example
type: drupal11
docroot: web
php: "8.3"
hostnames: [www]            # becomes www.example.localhost
fqdns: []                   # full names; need the hosts fallback
https: true
database: {type: mariadb, version: "11.8"}
node: "22"
composer: "2"
timezone: Europe/Berlin
environment: {APP_ENV: dev}
php_ini: {memory_limit: 1024M}
upload_dirs: [sites/default/files]
direct_php: []              # extra PHP entry points allowed besides index.php
xdebug: false
hooks:
  post-start:
    - run: vendor/bin/drush cr
cron: {interval: 300}       # a FreeUnit schedule once that feature ships
```

**FreeUnit JSON the example compiles to** (paths are placeholders; the 404 deny list from the how-to is shortened here; the listeners show the macOS shape from section 5, and on Linux they are `127.0.0.1:80` and `127.0.0.1:443`):

```json
{
  "settings": {"http": {"max_body_size": 104857600}},
  "listeners": {
    "*:80": {"pass": "routes/guard"},
    "*:443": {"pass": "routes/guard", "tls": {"certificate": ["example"]}}
  },
  "routes": {
    "guard": [
      {"match": {"source": ["127.0.0.0/8", "::1"]}, "action": {"pass": "routes/hosts"}},
      {"action": {"return": 403}}
    ],
    "hosts": [
      {"match": {"host": ["example.localhost", "*.example.localhost"]}, "action": {"pass": "routes/example"}},
      {"action": {"return": 404}}
    ],
    "example": [
      {"match": {"uri": ["*/.ht*", "/sites/*/files/*.php", "/sites/*/private/*"]}, "action": {"return": 403}},
      {"match": {"uri": ["/vendor/*", "*.yml", "*.twig", "*/composer.json", "*/composer.lock"]}, "action": {"return": 404}},
      {"match": {"uri": ["/core/install.php", "/core/install.php/*", "/core/rebuild.php", "/update.php", "/update.php/*"]}, "action": {"pass": "applications/example/direct"}},
      {"match": {"uri": ["/index.php", "/index.php/*"]}, "action": {"pass": "applications/example/index"}},
      {"match": {"uri": ["*.php", "*.php/*"]}, "action": {"return": 404}},
      {"action": {"share": "/path/to/example/web$uri", "fallback": {"pass": "applications/example/index"}}}
    ]
  },
  "applications": {
    "example": {
      "type": "php 8.3",
      "processes": {"max": 8, "spare": 0, "idle_timeout": 60},
      "limits": {"timeout": 600},
      "environment": {
        "IS_DDEV_PROJECT": "true",
        "DDEV_PRIMARY_URL": "https://example.localhost",
        "DRUSH_OPTIONS_URI": "https://example.localhost",
        "TZ": "Europe/Berlin"
      },
      "options": {"file": "/path/to/state/projects/example/php.ini"},
      "stdout": "/path/to/state/projects/example/php-stdout.log",
      "stderr": "/path/to/state/projects/example/php-stderr.log",
      "targets": {
        "direct": {"root": "/path/to/example/web/"},
        "index": {"root": "/path/to/example/web/", "script": "index.php"}
      }
    }
  }
}
```

- Live reading of `.ddev/` beats a one-shot conversion: teams often keep DDEV for CI or for other members, and a converted copy drifts. `init --from-ddev` can still write a native file for teams that leave DDEV.
- The tool should own the whole FreeUnit config and regenerate it from all project files on every change, then `PUT /config`. Patching subtrees per project is faster but leaves stale routes after a crash. A full PUT is validated as one unit.

### Gaps
- The semantics of `.ddev/.env` files (DDEV has `dotenv` commands) were not read. Experiment: read `cmd/ddev/cmd/dotenv*.go` and test an add-on that uses them.
- Real-world coverage is unknown. Experiment: run the importer over 50 public Drupal repositories that contain `.ddev/config.yaml` (GitHub code search) and count fields, custom nginx files and compose add-ons.
- Whether DDEV's `generic` web server projects with Drupal exist in useful numbers is unknown; same experiment.

## 3. Hostnames and DNS

### Takeaway
Use `<name>.localhost` by default. Chromium-based browsers and Firefox 84+ resolve every `*.localhost` name to loopback without DNS; macOS 26 resolves it in the OS for Safari; systemd-resolved, nss-myhostname and curl 7.85.0+ do the same. The name works offline, needs no elevation and is a secure context. The fallback is an opt-in hosts-file entry written by an elevated helper, for Safari on macOS 15 and older and for OS-resolver clients where `*.localhost` is not synthesized.

### Cited Findings
- RFC 6761 section 6.3 says users may assume localhost names resolve to loopback, and that name resolution APIs SHOULD return loopback for them and SHOULD NOT send them to DNS servers. [RFC 6761 section 6.3](https://www.rfc-editor.org/rfc/rfc6761#section-6.3)
- The W3C Secure Contexts algorithm treats `localhost` and names ending in `.localhost` as potentially trustworthy when the user agent follows the let-localhost-be-localhost resolution rules. [Secure Contexts, "Is origin potentially trustworthy?"](https://w3c.github.io/webappsec-secure-contexts/)
- Chromium's `IsLocalHostname()` is true for `localhost` and any name ending in `.localhost`; the resolver answers those with `::1` and `127.0.0.1` before any DNS lookup, citing let-localhost-be-localhost. [url_util.cc:582-589@f24386a5](https://github.com/chromium/chromium/blob/f24386a5821b5ba5fc100599fe1ad7f4969158f0/net/base/url_util.cc#L582-L589); [host_resolver_manager.cc:334-345,924-929@f24386a5](https://github.com/chromium/chromium/blob/f24386a5821b5ba5fc100599fe1ad7f4969158f0/net/dns/host_resolver_manager.cc#L334-L345)
- Firefox hardcodes `localhost` and `*.localhost` to loopback since Firefox 84. [Mozilla bug 1220810](https://bugzilla.mozilla.org/show_bug.cgi?id=1220810)
- Safari did not resolve localhost subdomains for years; WebKit closed the bug as MOVED, stating it was implemented in OS frameworks below WebKit, and a reporter confirmed `http://foo.bar.localhost:8000` works on macOS 26.0 and fails on macOS 15.7. [WebKit bug 160504](https://bugs.webkit.org/show_bug.cgi?id=160504)
- A Caddy forum thread reports Safari on macOS 15.7.4 cannot find `rootable.localhost` until an `/etc/hosts` entry is added, while macOS 26 resolves it. [Caddy community thread](https://caddy.community/t/localhost-tld-in-safari-not-found/33815)
- curl resolves `localhost` internally since 7.78.0. [Daniel Stenberg, "curl localhost as a local host"](https://daniel.haxx.se/blog/?p=16602)
- curl resolves every `*.localhost` name internally to loopback since 7.85.0, released 2022-08-31, whose change list says "hostip: resolve *.localhost to 127.0.0.1/::1". It ignores `/etc/hosts` for those names, and the maintainers cite RFC 6761. [curl 7.85.0 changes](https://curl.se/ch/7.85.0.html); [curl issue 11104](https://github.com/curl/curl/issues/11104)
- systemd-resolved and nss-myhostname resolve `localhost`, `localhost.localdomain` and any name ending in `.localhost` or `.localhost.localdomain` to 127.0.0.1 and ::1. [systemd-resolved.service.xml:111-113@a31540ec](https://github.com/systemd/systemd/blob/a31540ec8272da2ba9a98c98629917d12ad77c78/man/systemd-resolved.service.xml#L111-L113); [nss-myhostname.xml:44-46@a31540ec](https://github.com/systemd/systemd/blob/a31540ec8272da2ba9a98c98629917d12ad77c78/man/nss-myhostname.xml#L44-L46)
- DDEV uses `*.ddev.site`, a public wildcard record that always returns 127.0.0.1, and documents that it fails offline and behind DNS servers that block rebinding to 127.0.0.1, such as many Fritz!Box routers. [troubleshooting.md:293-318@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/usage/troubleshooting.md#L293-L318)
- When a name does not resolve to a local address, DDEV adds it to the hosts file through `ddev-hostname`, which re-executes itself with `sudo` on Unix and with the `runas` verb of `ShellExecuteExW` (UAC) on Windows. `DDEV_NONINTERACTIVE=true` skips this. [hostname_mgt.go:19-62@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/hostname_mgt.go#L19-L62); [elevate.go:11-19@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/cmd/ddev-hostname/elevate.go#L11-L19); [elevate_windows.go:75,93@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/cmd/ddev-hostname/elevate_windows.go#L75)
- DDEV's offline guide says starting offline adds `<projectname>.ddev.site` to the hosts file and needs administrator rights on every OS. [offline.md@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/usage/offline.md)
- DDEV documents that Cloudflare WARP can stop `*.ddev.site` names from resolving. [networking.md:186@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/usage/networking.md#L186)
- Valet installs dnsmasq through Homebrew, answers `address=/<tld>/127.0.0.1` and `::1`, and writes `/etc/resolver/<tld>`. [DnsMasq.php:11,20,113-116@874dd14d](https://github.com/laravel/valet/blob/874dd14d58b5cec2f3c869f4f755627179b28c16/cli/Valet/DnsMasq.php#L113-L116)
- Herd for macOS runs dnsmasq; Herd for Windows instead updates the hosts file through its HerdHelper Windows service. [Herd for Windows common issues](https://herd.laravel.com/docs/windows/troubleshooting/common-issues)
- Herd's macOS troubleshooting page attributes DNS failures to dnsmasq not running, to a second local DNS server, or to VPN network extensions that intercept DNS. [Herd for macOS common issues](https://herd.laravel.com/docs/macos/troubleshooting/common-issues)
- A public bug report shows macOS 26.3.1 answering names under TLDs outside the IANA root zone (`.test`, `.internal`, `.lan`, `.home.arpa`) from mDNSResponder with cached negative answers. `tcpdump` sees no query reach the dnsmasq named in `/etc/resolver`. `/etc/hosts` entries still work, and commenters report that a `domain` line in the resolver file helps. [gist by adamamyl](https://gist.github.com/adamamyl/81b78eced40feae50eae7c4f3bec1f5a)
- Symfony CLI avoids DNS entirely: its proxy serves `http://127.0.0.1:7080/proxy.pac`, whose `FindProxyForURL` sends `.wip` names to the proxy; the user sets this URL as the OS automatic proxy configuration. [proxy.go:194,333-339@a9011678](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/local/proxy/proxy.go#L333-L339); [config.go:54-56@a9011678](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/local/proxy/config.go#L54-L56); [Symfony CLI docs](https://symfony.com/doc/current/setup/symfony_cli.html)
- Acrylic DNS Proxy for Windows accepts wildcard and regular-expression entries in `AcrylicHosts.txt`. [Acrylic hosts documentation](https://mayakron.altervista.org/support/acrylic/Hosts.htm)
- Cookies are not isolated by port: a cookie readable by a service on one port is readable by a service on another port of the same host. [RFC 6265 section 8.5](https://www.rfc-editor.org/rfc/rfc6265#section-8.5)
- Drupal sets the session cookie domain to `.` plus the host only when the result has more than two dot-separated parts and is not an IP address; for `example.localhost` it becomes `.example.localhost`. [SessionConfiguration.php:128-147@89f3e765](https://github.com/drupal/drupal/blob/89f3e765bb2ef1620ca89302ce8ee6d7ab263332/core/lib/Drupal/Core/Session/SessionConfiguration.php#L128-L147)
- WSL2 in its default NAT mode lets a Windows browser reach a Linux server through `localhost`. Mirrored networking (`networkingMode=mirrored` in `.wslconfig`) needs Windows 11 22H2 or later. [Microsoft Learn, WSL networking](https://learn.microsoft.com/en-us/windows/wsl/networking)

### Inferences
- Local measurement on one Ubuntu 26.04 machine (systemd 259, curl 8.18.0, `hosts: files mdns4_minimal [NOTFOUND=return] dns`): `getaddrinfo("foo.bar.localhost")` returned 127.0.0.1 and ::1, and curl tried `[::1]:9` then `127.0.0.1:9` for `foo.localhost`. This matches the systemd documentation above.
- `<name>.localhost` satisfies every browser that matters today except Safari on macOS 15 and older. It never leaves the machine, so offline use, VPNs, corporate DNS and rebinding protection do not break it. Chrome and Firefox also treat it as a secure context over plain HTTP.
- Server-side requests from PHP depend on the client. Guzzle with the cURL handler uses libcurl, which resolves `*.localhost` itself from 7.85.0. PHP stream wrappers (`file_get_contents()`) use `getaddrinfo()`, which works on Linux with systemd-resolved or nss-myhostname and on macOS 26, but not on macOS 15. Windows behaviour is unverified.
- Per-project host names, not per-project ports, are the right isolation unit: cookies ignore ports, and Drupal scopes its session cookie to `.<name>.localhost`.
- `.test` with dnsmasq needs root (port 53 and `/etc/resolver`) and, per the report above, broke on macOS 26.3.1. `.local` collides with mDNS. Both are worse defaults than `.localhost`.
- The hosts fallback should follow DDEV: a small helper that elevates only for the write, adds exact names (hosts files have no wildcards), and removes them on `clean`. On Windows, Herd's precedent is a helper service; DDEV's is a UAC prompt per change.
- Imported DDEV projects keep working on `*.ddev.site` when online, because that public record returns 127.0.0.1. The tool should serve those names too (route `host` match plus certificate SAN), so links stored in the database still work.
- WSL2 interim path: run unitd inside WSL2 and browse from Windows at `https://<name>.localhost`. Chrome and Edge on Windows resolve the name to loopback themselves, and WSL2 forwards Windows `localhost` to Linux in NAT mode.

### Gaps
- Windows DNS client behaviour for `sub.localhost` (as seen by PHP for Windows, Node and Java) is unverified. Experiment: on Windows 11 24H2, run `Resolve-DnsName foo.localhost`, `ping foo.localhost`, `php -r "var_dump(gethostbynamel('foo.localhost'));"` and `node -e "require('dns').lookup('foo.localhost', console.log)"`.
- macOS 15 `getaddrinfo()` for `foo.localhost` from PHP, and macOS 26 behaviour for PHP and Node, are unverified. Experiment: same commands on both versions.
- The macOS 26.3.1 `/etc/resolver` regression rests on one bug report with packet captures, not on an Apple statement, and later macOS updates may change it. Experiment: install dnsmasq with an `/etc/resolver/test` file on the current macOS 26 release and run `dscacheutil -q host -a name foo.test`.
- Whether `localhost` is on the Public Suffix List, and how each browser treats `Domain=localhost` cookies, was not checked. It matters only for projects served on bare `localhost`.

## 4. Local TLS

### Takeaway
Run a local CA inside the tool (mkcert-derived Go code), or reuse an already trusted mkcert CA, issue one leaf per project for `name.localhost` and `*.name.localhost`, and load it with `PUT /certificates/<project>`. FreeUnit 1.37.0 replaces a bundle in place without a restart and serves many bundles on one listener through SNI. The 398-day limit of Apple and Chrome does not apply to locally added roots, but Apple's 825-day rule does, so a 396-day leaf renewed automatically is the safe choice. FreeUnit has no HTTP/2 yet, which matters more for page load speed than TLS itself.

### Cited Findings
- mkcert issues leaves valid for 2 years and 3 months, "always less than 825 days, the limit that macOS/iOS apply to all certificates, including custom roots"; its CA is valid for 10 years. [cert.go:59-62,340@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/cert.go#L59-L62)
- mkcert warns that "many browsers don't support second-level wildcards" such as `*.localhost`, and reminds that X.509 wildcards match one level only. [cert.go:148-161@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/cert.go#L148-L161)
- mkcert trusts its CA on macOS with `sudo security add-trusted-cert -d -k /Library/Keychains/System.keychain` and then edits the admin trust settings. [truststore_darwin.go:53-100@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/truststore_darwin.go#L53)
- On Linux mkcert copies the CA into the first existing anchor directory (`/etc/pki/ca-trust/source/anchors/`, `/usr/local/share/ca-certificates/`, `/etc/ca-certificates/trust-source/anchors/`, `/usr/share/pki/trust/anchors`) and runs `update-ca-trust extract`, `update-ca-certificates` or `trust extract-compat`. [truststore_linux.go:36-48@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/truststore_linux.go#L36-L48)
- For Firefox and Chromium NSS databases (`~/.pki/nssdb`, snap Chromium, Firefox profiles) mkcert runs `certutil -A -d <profile> -t C,,`, so it needs the NSS `certutil` binary. [truststore_nss.go:21-34,91@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/truststore_nss.go#L91)
- On Windows mkcert opens the `ROOT` store with `CertOpenSystemStoreW` and adds the CA with `CertAddEncodedCertificateToStore`. [truststore_windows.go:72-93@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/truststore_windows.go#L72-L93) Microsoft documents that this function reaches only current-user stores, not the local machine store. [CertOpenSystemStoreW](https://learn.microsoft.com/en-us/windows/win32/api/wincrypt/nf-wincrypt-certopensystemstorew)
- mkcert's README warns that `rootCA-key.pem` "gives complete power to intercept secure requests from your machine". [README.md:32@1c1dc4ed](https://github.com/FiloSottile/mkcert/blob/1c1dc4ed27ed5936046b6398d39cab4d657a2d8e/README.md#L32)
- DDEV signs and ships `mkcert.exe` in its Windows release. [Makefile:417@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/Makefile#L417)
- Symfony CLI's `cert` package (mkcert code under the mkcert licence) creates both its CA and its leaves with 825-day validity, and regenerates a CA whose validity exceeds 825 days. [cert.go:133,293,389@e6d19f75](https://github.com/symfony-cli/cert/blob/e6d19f75c77497556b1467ad02bd2ca47088d20a/cert.go#L293)
- Valet issues leaves for 396 days and a CA for 20 years by default, noting that certificates should not exceed 397 days, and trusts the CA with `sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain`. [Site.php:485,490,666@874dd14d](https://github.com/laravel/valet/blob/874dd14d58b5cec2f3c869f4f755627179b28c16/cli/Valet/Site.php#L490)
- Apple's 398-day maximum covers certificates from preinstalled roots issued on or after 2020-09-01 and "will not affect certificates issued from user-added or administrator-added Root CAs". [Apple support 102028](https://support.apple.com/en-us/102028)
- Apple's iOS 13 and macOS 10.15 rules require TLS server certificates to have a validity of 825 days or fewer. They also require the DNS name in the SAN, the serverAuth EKU, RSA keys of at least 2048 bits and SHA-2 signatures. The page does not exempt user-added roots. [Apple support 103769](https://support.apple.com/en-us/103769)
- Chrome's 398-day limit applies only to CAs trusted in a default installation, not to locally operated CAs that were configured manually. [Chromium certificate lifetimes](https://chromium.googlesource.com/chromium/src/+/HEAD/net/docs/certificate_lifetimes.md)
- CA/Browser Forum ballot SC-081v3 passed and reduces the maximum validity of public TLS certificates from 398 to 47 days between March 2026 and March 2029. [Ballot SC-081v3](https://cabforum.org/2025/04/11/ballot-sc081v3-introduce-schedule-of-reducing-validity-and-data-reuse-periods/)
- A FreeUnit bundle is one PEM body with the leaf, its chain ordered leaf to root, and the private key; it is uploaded with `PUT /certificates/<name>` on the control socket. [certificates.rst:30-52@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/certificates.rst#L30-L52); [published page](https://docs.freeunit.org/certificates/)
- Since 1.37.0 a PUT on an existing name replaces the bundle in place, through a temporary file and a rename. A listener that uses the name gets the new bundle at once. An identical upload is not stored again. The key may come first. A bundle over 1 MiB gets 413. [certificates.rst:63-115@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/certificates.rst#L63-L115)
- Connections accepted before a replacement finish with the old certificate, and `GET /certificates` shows a SHA-256 fingerprint per bundle since 1.37.0; GET also shows each certificate's validity dates. [unit-1.37.0-released.rst:121-127,564-579@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/news/2026/unit-1.37.0-released.rst#L564-L579); [certificates.rst:139-165@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/certificates.rst#L139-L165)
- A listener's `tls.certificate` accepts an array of bundle names for SNI since 1.23.0. [configuration/index.rst:230-250@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L230-L250)
- FreeUnit at 872bf041 has no HTTP/2: `git grep` finds no `nghttp2` and no `ALPN` reference in `src/`, `auto/` or `configure`. HTTP/2 is being added in three open pull requests: stage 1 is open, stages 2 and 3 are drafts. [PR 463](https://github.com/freeunitorg/freeunit/pull/463); [PR 467](https://github.com/freeunitorg/freeunit/pull/467); [PR 470](https://github.com/freeunitorg/freeunit/pull/470)
- Chromium allows at most 6 connections per host for normal HTTP/1.1 socket pools. [client_socket_pool_manager.cc:54-58@f24386a5](https://github.com/chromium/chromium/blob/f24386a5821b5ba5fc100599fe1ad7f4969158f0/net/socket/client_socket_pool_manager.cc#L54-L58)
- Chrome 106 disabled HTTP/2 server push by default and points to 103 Early Hints instead. [Chrome blog, removing push](https://developer.chrome.com/blog/removing-push)
- Drupal sets `cookie_secure` from `$request->isSecure()` and names the session cookie with the prefix `SSESS` on HTTPS and `SESS` on HTTP. [SessionConfiguration.php:55,79@89f3e765](https://github.com/drupal/drupal/blob/89f3e765bb2ef1620ca89302ce8ee6d7ab263332/core/lib/Drupal/Core/Session/SessionConfiguration.php#L79)

### Inferences
- HTTPS is not needed for most secure-context browser APIs on `*.localhost`, because those origins are already potentially trustworthy (section 3). HTTPS is still needed to test what Drupal does differently on HTTPS: the `SSESS` cookie name and the `Secure` flag, mixed-content warnings, HSTS, and OAuth or payment providers that require HTTPS redirect URIs for names other than `localhost`. So HTTPS should be on by default once the CA is trusted, and HTTP should always work too.
- Recommended flow:
  1. One-time `trust` step: create the CA in the tool's data directory with mode 0600 for the key, or reuse an existing mkcert `CAROOT` if its root is already trusted (DDEV users usually have one). Install it in the system store (macOS System keychain, Linux anchors plus the update command, Windows current-user ROOT store) and in NSS databases when `certutil` exists.
  2. On every `start`: issue or renew the project leaf (all project names plus `*.<name>.localhost` for multisite subdomains), 396 days, renewed when fewer than 30 days remain. ECDSA P-256 keeps bundles small.
  3. `PUT /certificates/<project>`, then make sure the `*:443` listener's certificate array lists every project bundle.
  4. In CI mode skip all of this and serve HTTP only.
- Per-project leaves avoid the second-level wildcard `*.localhost`, which mkcert warns that browsers reject.
- FreeUnit's lack of HTTP/2 costs more in development than in production: Drupal development usually disables CSS and JS aggregation, so a page can request dozens of files, and HTTP/1.1 allows 6 parallel connections per host. This is a reason to track the HTTP/2 pull requests, not a reason to put another proxy in front.

### Gaps
- Whether Firefox on macOS and Windows trusts the OS store without NSS import (the `security.enterprise_roots.enabled` behaviour) was not checked. Experiment: install a test CA only in the OS store and load a page in a clean Firefox profile on each OS.
- Chrome's handling of `Secure` cookies on plain `http://*.localhost` was not checked. Experiment: set a `Secure` cookie from `http://a.localhost` and read it back in Chrome, Firefox and Safari.
- Google, GitHub and Microsoft redirect-URI rules for `http://*.localhost` were not read. Experiment: register a test OAuth client with `http://example.localhost/callback` at each provider.

## 5. Ports and the instance model

### Takeaway
Run one FreeUnit instance per user for all projects, with one listener pair that routes by host name. A normal user can already bind ports 80 and 443 on macOS, but only on the wildcard address; on Linux one `setcap` makes 80 and 443 available on loopback, and FreeUnit 1.37.0 keeps that capability in its main process only. Windows has no privileged ports. FreeUnit ignores systemd socket activation in practice.

### Cited Findings
- macOS (XNU): binding a port below 1024 requires the `PRIV_NETINET_RESERVEDPORT` privilege only when the bind address is not the wildcard address; the IPv6 code has the same rule for a non-unspecified address. [in_pcb.c:988-1000@f6217f89](https://github.com/apple-oss-distributions/xnu/blob/f6217f891ac0bb64f3d375211650a4c1ff8ca1ea/bsd/netinet/in_pcb.c#L988-L1000); [in6_pcb.c:371-375@f6217f89](https://github.com/apple-oss-distributions/xnu/blob/f6217f891ac0bb64f3d375211650a4c1ff8ca1ea/bsd/netinet6/in6_pcb.c#L371-L375)
- Linux: `ip_unprivileged_port_start` is per network namespace, defaults to 1024, and ports below it need root or `CAP_NET_BIND_SERVICE`. [Linux ip-sysctl documentation](https://docs.kernel.org/networking/ip-sysctl.html)
- FreeUnit binds listener sockets in the main process. [src/nxt_main_process.c:1763,1814@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_main_process.c#L1763)
- FreeUnit's `nxt_capability_drop()` runs in every forked process; the main process keeps `CAP_NET_BIND_SERVICE` because listeners are created on every reconfiguration. [src/nxt_capability.c:6-21@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_capability.c#L6-L21) `git show` finds `nxt_capability_drop` in the 1.37.0 tag and not in 1.36.1.
- A process with a non-root user ID that executes a binary with file capabilities runs in secure-execution mode: the dynamic linker ignores `LD_LIBRARY_PATH` and preloads only from standard directories. [ld.so(8)](https://man7.org/linux/man-pages/man8/ld.so.8.html)
- Executing a program whose file capabilities raise the permitted set also resets the process's dumpable attribute to `/proc/sys/fs/suid_dumpable` (default 0). A non-dumpable process writes no core dump and cannot be attached with `PTRACE_ATTACH`. [PR_SET_DUMPABLE(2const)](https://man7.org/linux/man-pages/man2/pr_set_dumpable.2const.html)
- FreeUnit reads `LISTEN_FDS` into `rt->inherited_sockets`. [src/nxt_runtime.c:218-262@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_runtime.c#L218-L262) The only function that would reuse those sockets, `nxt_runtime_listen_sockets_create()`, has no caller; it is defined and declared but never called. [src/nxt_runtime.c:1522-1565@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_runtime.c#L1522-L1565); [src/nxt_runtime.h:154@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_runtime.h#L154) Configured listeners are created by `nxt_main_listening_socket()` in the main process, so sockets passed by systemd are never used; the variable only stops unitd from daemonizing. [src/nxt_runtime.c:399@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_runtime.c#L399)
- unitd accepts `--control`, `--control-mode`, `--control-user`, `--control-group`, `--user`, `--group`, `--pid`, `--log`, `--modulesdir`, `--statedir`, `--tmpdir` and `--no-daemon`, so several instances can run side by side with separate paths. [src/nxt_runtime.c:1100-1344@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_runtime.c#L1100-L1344)
- A unitd started by a normal user logs "Unit is running unprivileged, then it cannot use arbitrary user and group." [src/nxt_runtime.c:947@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_runtime.c#L947)
- Wildcard listeners cannot overlap with other listeners on the same port. [configuration/index.rst:37-48@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L37-L48)
- One application can be restarted with `GET /control/applications/<name>/restart`; old processes finish their requests while new ones start. [configuration/index.rst:3625-3650@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L3625-L3650)
- With `limits.start_timeout` set, a process that does not report ready in time during a reconfiguration makes the whole reconfiguration fail, and the previous configuration stays active; the default is 0, which waits indefinitely. [configuration/index.rst:4143-4157@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L4143-L4157)
- Windows: the default dynamic port range is 49152 to 65535, and the lowest allowed start port is 1025. [Microsoft Learn, default dynamic port range](https://learn.microsoft.com/en-us/troubleshoot/windows-server/networking/default-dynamic-port-range-tcpip-chang)
- Windows supports administered port exclusions (`netsh int ipv4 add excludedportrange`), and Microsoft documents a case where a bind to an excluded port fails with WSAEACCES (10013); the workaround it gives is a port outside the dynamic range 49152 to 65535. [Microsoft Learn, error 10013](https://learn.microsoft.com/en-us/troubleshoot/windows-server/networking/error-10013-wsaeacces-is-returned)
- DDEV runs one router for all projects on ports 80 and 443 (`router_http_port`, `router_https_port`) and binds to localhost unless `bind_all_interfaces` is set. [schema.json@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/schema.json)
- Valet runs one Homebrew nginx for all sites and controls Homebrew services with `sudo brew services`. [Nginx.php:10-21@874dd14d](https://github.com/laravel/valet/blob/874dd14d58b5cec2f3c869f4f755627179b28c16/cli/Valet/Nginx.php#L10-L21); [Brew.php:208@874dd14d](https://github.com/laravel/valet/blob/874dd14d58b5cec2f3c869f4f755627179b28c16/cli/Valet/Brew.php#L208)
- Symfony CLI runs one web server per project and an optional proxy on port 7080 for custom domains. [Symfony CLI docs](https://symfony.com/doc/current/setup/symfony_cli.html); [config.go:54-56@a9011678](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/local/proxy/config.go#L54-L56)

### Inferences
- One per-user instance matches DDEV, Valet and Herd, keeps one certificate list for SNI, and lets several PHP versions run side by side (section 6). Per-project instances would each need their own port, and cookies do not isolate by port (section 3).
- Isolation in a shared instance is adequate: each project is its own application with its own processes, and the tool restarts one application at a time. The risk is a config PUT that fails for one project and blocks changes for all. The tool should validate each project's fragment before the full PUT, and use `"spare": 0` so that a reconfiguration never waits on a slow application start.
- Linux: `setcap cap_net_bind_service=+ep` on the tool's own unitd binary (one sudo at install time), then bind `127.0.0.1:80` and `127.0.0.1:443`. FreeUnit 1.37.0 drops the capability in router and worker processes, so PHP code does not inherit it. Changing `ip_unprivileged_port_start` is a system-wide change and should stay a documented alternative.
- The capability has side effects. unitd then runs in secure-execution mode, so the shipped `php8.x.unit.so` must find `libphp` through its RUNPATH (for example `$ORIGIN/../lib`), not through `LD_LIBRARY_PATH`. unitd also becomes non-dumpable, which blocks core dumps and debugger attach by the same user. A file capability lives on the file, so `self-update` must run `setcap` again after it replaces the binary.
- Local measurement: `ip_unprivileged_port_start` is 1024 on one Ubuntu 26.04 machine with kernel 7.0.
- macOS: a user-mode unitd can bind `*:80` and `*:443` with no root. That exposes the ports to the LAN, so the tool's first route step should refuse every `source` outside loopback (section 2 example). The macOS application firewall may also prompt. The alternative is Valet's model: a root-owned launchd daemon with `--user <developer>` and `--control-user <developer>`.
- Windows (later): check `excludedportrange` and port 80 owners at `start` and fall back to 8080/8443. In the WSL2 interim, unitd runs inside WSL2 and Windows reaches it through localhost forwarding.
- Every OS gets a no-privilege fallback: `127.0.0.1:8080` and `127.0.0.1:8443`. This is also the CI default.

### Gaps
- Whether a PUT that adds one application waits for that application's processes to start when `spare` is 0 was not tested. Experiment: PUT a config with a broken PHP application and `spare: 0`, time the response, then request the site.
- Whether macOS shows a firewall prompt for a user-mode unitd bound to `*:443` was not tested. Experiment: run unitd on a clean macOS 15 and 26 machine with the firewall on.
- The "HTTP.sys holds port 80" case on Windows was not documented with a primary source here. Experiment: on Windows 11 with IIS or the World Wide Web Publishing Service enabled, run `netsh http show servicestate` and try to bind port 80.

## 6. PHP provisioning

### Takeaway
FreeUnit needs PHP's embed SAPI, and no mainstream macOS source provides it: Homebrew's `php` formula does not build it, and the FreeUnit tap is an untouched fork of the archived NGINX Unit tap at Unit 1.34.2. The robust answer is the Herd model: the tool ships matched pairs per OS and architecture, built from one static-php-cli run (php CLI, shared `libphp`, the FreeUnit module built against it, and `xdebug.so`). On Linux, distribution packages (`libphp8.x-embed` from the distribution or from Ondřej Surý's repository, the package name that FreeUnit's own 1.37.0 debs depend on) are a workable alternative.

### Cited Findings
- FreeUnit's PHP module is configured with `--config` (php-config), `--module`, `--lib-path` and `--lib-static`; `--lib-static` needs `--lib-path` and links `libphp<major>.a`. [auto/modules/php:17-28,95-100@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/auto/modules/php#L17-L28)
- Configure fails with "no PHP embed SAPI found" when PHP lacks the embed SAPI. [auto/modules/php:142-164@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/auto/modules/php#L142-L164)
- The module file is `<module>.unit.so`, for example `php8.3.unit.so`. [auto/modules/php:588,641@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/auto/modules/php#L588)
- An application's `options.file` sets PHP's `php_ini_path_override` before PHP starts in that application's process; `options.admin` and `options.user` are applied as system-level and user-level INI entries after startup. [src/nxt_php_sapi.c:431-456,760@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_php_sapi.c#L431-L456)
- FreeUnit 1.37.0 (2026-10-04) attaches Debian 13 (trixie) amd64 packages to the release: `freeunit` (5,366,112 bytes, with `/usr/sbin/freeunitd` and systemd units) and `freeunit-php8.3`, `freeunit-php8.4`, `freeunit-php8.5` (about 100 KB each). Each PHP package depends on `libphp8.x-embed` and installs `/usr/lib/freeunit/modules/php8.x.unit.so`. (Inspected with `dpkg-deb`.) [FreeUnit 1.37.0 release](https://github.com/freeunitorg/freeunit/releases/tag/1.37.0)
- Debian 13 builds `libphp8.4-embed` and `php8.4-cli` from its `php8.4` source package; a name search in trixie finds no other versioned `libphp8.x-embed` package. [Debian php8.4 source package](https://packages.debian.org/source/trixie/php8.4); [Debian package search](https://packages.debian.org/search?keywords=libphp&searchon=names&suite=trixie&section=all)
- Brief said the Linux packages at packages.freeunit.org handle PHP modules. The site lists only build-time dependencies (njs, wasi-sysroot, wasmtime sources, libunit-wasm). [packages.freeunit.org](https://packages.freeunit.org/) The docs say official RPM and DEB packages are "coming soon". [installation.rst:12-19,106-114@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/installation.rst#L106-L114)
- Brief said the FreeUnit Homebrew tap handles PHP modules. The tap is a fork of `nginx/homebrew-unit` whose README says the repository is archived; its `unit` formula builds NGINX Unit 1.34.2 from `github.com/nginx/unit`, and `unit-php` depends on a `php-embed` formula at PHP 8.4.2. The FreeUnit docs say the FreeUnit tap "is not yet published". [Formula/unit.rb:1-6@58294f2e](https://github.com/freeunitorg/homebrew-unit/blob/58294f2ea318d31a809e9569f340dd5b65b97181/Formula/unit.rb#L1-L6); [Formula/unit-php.rb:4-11@58294f2e](https://github.com/freeunitorg/homebrew-unit/blob/58294f2ea318d31a809e9569f340dd5b65b97181/Formula/unit-php.rb#L4-L11); [Formula/php-embed.rb:5@58294f2e](https://github.com/freeunitorg/homebrew-unit/blob/58294f2ea318d31a809e9569f340dd5b65b97181/Formula/php-embed.rb#L5); [README.md@58294f2e](https://github.com/freeunitorg/homebrew-unit/blob/58294f2ea318d31a809e9569f340dd5b65b97181/README.md); [installation.rst:940-946@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/installation.rst#L940-L946)
- Homebrew's core `php` formula (8.5.11 at the cited commit) configures `--enable-fpm`, `--enable-phpdbg` and `--with-apxs2`, and no embed SAPI. [Formula/p/php.rb:5,162-177@2b1c5d8c](https://github.com/Homebrew/homebrew-core/blob/2b1c5d8ca5b8399a327fd0979db34e882ced81bf/Formula/p/php.rb#L162-L177)
- static-php-cli builds the `embed` SAPI on Linux, macOS (`libphp.a`) and Windows (`php8embed.lib`); `fpm` is Linux and macOS only; `cli` and `micro` cover all three. [sapi-reference.md:11-17@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/docs/en/guide/sapi-reference.md#L11-L17)
- With `SPC_CMD_VAR_PHP_EMBED_TYPE=shared` static-php-cli produces a shared `libphp.so` (Linux) or `libphp.dylib` (macOS); the default is `static`. [unix.php:331-339@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/src/Package/Target/php/unix.php#L331-L339); [config/env.ini:121@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/config/env.ini#L121)
- The `SPC_TARGET=native-native-gnu.2.17` target builds against glibc 2.17 with Zig and supports `dl()`, FFI and `.so` extensions, but not musl systems. Xdebug can only be built as a shared extension and needs that glibc target on Linux, or macOS. On Windows static-php-cli cannot load extensions dynamically at all. [sapi-reference.md, dynamic extensions@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/docs/en/guide/sapi-reference.md); [extension-notes.md:91-95@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/docs/en/guide/extension-notes.md#L91-L95)
- FrankenPHP's musl static binaries cannot load Xdebug; its glibc static builder can produce `xdebug-zts.so`; its Windows archives contain the official PHP for Windows. [static.md:14,144-166@233f7936](https://github.com/php/frankenphp/blob/233f793654acf53be41385a4a29ac5e126cf6701/docs/static.md#L144-L166); [README.md:41@233f7936](https://github.com/php/frankenphp/blob/233f793654acf53be41385a4a29ac5e126cf6701/README.md#L41)
- Herd ships PHP 8.5 by default and offers older versions. [Herd PHP versions](https://herd.laravel.com/docs/macos/technology/php-versions) Free Herd ships Xdebug builds per PHP version and CPU, enabled by a `zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-83-arm64.so` line; Herd Pro adds automatic breakpoint detection. [Herd Xdebug](https://herd.laravel.com/docs/macos/debugging/xdebug)
- drupal.org lists PHP 8.1 to 8.4 for Drupal 10.4 to 10.6, PHP 8.3 and 8.4 for Drupal 11.1 and 11.2, PHP 8.3 to 8.5 for Drupal 11.3 and 11.4, and only PHP 8.5 for Drupal 12.0. From Drupal 12.0, PHP should be compiled with argon2 support for password hashing. Requirements include PDO, XML, GD, JSON, cURL, mbstring, OpenSSL and `opcache.save_comments`. [Drupal PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements)
- FreeUnit's macOS CI builds and runs only the C test programs on macOS 15 arm64: "No modules, no pytest, no sudo." [build-test-macos.yml:25-30@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/.github/workflows/build-test-macos.yml#L25-L30)
- FreeUnit 1.37.0 makes applications linked against libunit fail every request on Apple Silicon (16 KiB pages); 1.36.1 works. The reporter tested an `external` application; the PHP module uses the same library (`nxt_unit_init()` at [src/nxt_php_sapi.c:530@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_php_sapi.c#L530)). The fix merged on 2026-10-07, and the C tests now run on macOS. [freeunit issue 595](https://github.com/freeunitorg/freeunit/issues/595); [PR 598](https://github.com/freeunitorg/freeunit/pull/598); [PR 602](https://github.com/freeunitorg/freeunit/pull/602)

### Inferences
- The module and the CLI must come from the same PHP build, or Drush sees different extensions and limits than the web workers. One static-php-cli invocation with `--build-cli --build-embed` and the shared embed type gives exactly that: one `php`, one `libphp`, one extension set.
- Proposed artifact per PHP minor version, OS and CPU: `php` CLI, `libphp.so` or `libphp.dylib`, `php8.x.unit.so` built with `./configure php --config=... --lib-path=...` against that `libphp`, and `xdebug.so`. The tool keeps all installed modules in one modules directory, so one unitd serves `php 8.3`, `php 8.4` and `php 8.5` projects at the same time.
- The tool also needs `unitd` itself for macOS, where no FreeUnit package exists. It must be at least the first release that contains the fix for issue 595.
- Xdebug per project: write a second php.ini that adds `zend_extension=<path>/xdebug.so` and point `options.file` at it. A change to the application config restarts only that project's workers.
- Linux has a second path: Sury (Debian and Ubuntu), Remi (Fedora and RHEL) and Alpine packages provide both the CLI and the embed library. FreeUnit's own debs prove this path for Debian 13 amd64: PHP 8.4 comes from Debian itself, PHP 8.3 and 8.5 from Sury. It suits users who want distribution security updates, but every distribution needs its own module build.
- Security patch latency moves to the project with the shipped-pairs model. PHP releases patch versions about monthly, so the pipeline must rebuild and publish within days. Herd runs this model commercially; whether it uses static-php-cli was not confirmed here.
- Windows (later): static-php-cli can build the embed library for Windows, but not load shared extensions, so Xdebug would need to be compiled in. That is a question for the Windows FreeUnit team.

### Gaps
- This research found no FreeUnit module built against static-php-cli's shared embed library. Experiment: build PHP 8.4 with static-php-cli (`--build-cli --build-embed`, shared embed, glibc 2.17 target) on Linux and macOS, run `./configure php --config=<buildroot>/bin/php-config --lib-path=<buildroot>/lib`, load the module in unitd, and run FreeUnit's PHP test suite. Include argon2 for Drupal 12, and give the module a RUNPATH so that it finds `libphp` when unitd carries a file capability.
- Whether static-php-cli installs a usable `php-config` for the embed build was not checked. Same experiment.
- The SQLite library version inside static-php-cli builds was not checked against Drupal 11's 3.45 minimum. Experiment: `php -r 'echo SQLite3::version()["versionString"];'` on a built binary.
- Herd's use of static-php-cli is reported in Herd's launch post, which now returns 404. Source needed: Herd's current docs or a statement from the Herd team.
- Patch latency for each channel was not measured. Experiment: for PHP 8.4.x releases in 2026, compare php.net release dates with the Sury, Homebrew, static-php-cli and Herd changelog dates.
- Whether `libphp8.x-embed` from Sury uses its own ini directory (separate from the CLI) was not checked. Experiment: `dpkg -L libphp8.4-embed` and `php-config --ini-dir` on Debian 13.

## 7. Databases and services without Docker

### Takeaway
Portable binaries exist for most services, but not evenly. MariaDB publishes Linux x86_64 and Windows binaries and no macOS binary. PostgreSQL has third-party portable builds for all three OSes. Mailpit is one Go binary per OS. Valkey has no Windows build. The tool should supervise these processes itself (FreeUnit cannot, see section 8) and keep one data directory per project. It should start with MariaDB or MySQL from an existing local install plus SQLite, and add downloads later.

### Cited Findings
- MariaDB long-term lines and end-of-life dates: 10.6 (2026-07-06), 10.11 (2028-02-16), 11.4 (2029-05-29), 11.8 (2030-02-13), 12.3 (2029-06-12); 13.0 is a rolling release. [MariaDB downloads REST API](https://downloads.mariadb.org/rest-api/mariadb/)
- MariaDB 11.8.9 (released 2026-08-22) publishes a source tarball, a Linux x86_64 systemd binary tarball, and a Windows x64 ZIP and MSI; it lists no macOS binary and no Linux aarch64 binary tarball. [MariaDB 11.8 latest release files](https://downloads.mariadb.org/rest-api/mariadb/11.8/latest/)
- `mariadb-install-db` takes `--datadir`, `--basedir` and `--skip-test-db`; `--auth-root-authentication-method` is `normal` or `socket` (default `socket`); `--user` needs root, so the tool should run it as the same account that runs `mariadbd`. [mariadb-install-db docs](https://mariadb.com/docs/server/clients-and-utilities/deployment-tools/mariadb-install-db)
- MySQL's download page lists 9.7.2 LTS, 8.4.11 LTS, 8.0.46 and the 26.7.0 Innovation release; the Windows ZIP of 26.7.0 is 330.1 MB; macOS is one of the listed operating systems. [MySQL Community downloads](https://dev.mysql.com/downloads/mysql/)
- `mysqld --initialize-insecure` creates `root@localhost` with an empty password in the given `--datadir`. [MySQL 8.4 data directory initialization](https://dev.mysql.com/doc/refman/8.4/en/data-directory-initialization.html)
- theseus-rs `postgresql-binaries` 18.6.0 (2026-08-15) has archives for macOS arm64 (13,081,312 bytes) and x86_64, Linux glibc and musl on many architectures, and Windows x86_64 (ZIP 54,084,095 bytes). [postgresql-binaries releases](https://github.com/theseus-rs/postgresql-binaries/releases)
- zonky.io `embedded-postgres-binaries` publishes PostgreSQL bundles of about 10 MB as Maven artifacts for Darwin, Windows, Linux and Alpine Linux. [embedded-postgres-binaries](https://github.com/zonkyio/embedded-postgres-binaries)
- Drupal 10 needs MySQL 5.7.8, MariaDB 10.3.7, PostgreSQL 12 or SQLite 3.26 (with `json1`). Drupal 11 needs MySQL 8.0, MariaDB 10.6, PostgreSQL 16 or SQLite 3.45. Drupal 12 needs MySQL 8.0, MariaDB 10.11, PostgreSQL 18 or SQLite 3.45. PostgreSQL needs the `pg_trgm` extension. [Drupal database requirements](https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements)
- Mailpit v1.31.4 (2026-10-03) ships archives of about 10 MB for macOS amd64 and arm64, Linux 386, amd64, arm and arm64, and Windows amd64 and arm64. [Mailpit releases](https://github.com/axllent/mailpit/releases)
- Valkey builds on Linux, macOS, OpenBSD, NetBSD and FreeBSD; Windows is not listed. [Valkey README](https://github.com/valkey-io/valkey/blob/unstable/README.md)
- Microsoft Garnet speaks the RESP protocol, runs on .NET on Linux and Windows, and released v2.2.0 on 2026-10-01. [Garnet README](https://github.com/microsoft/garnet); [Garnet releases](https://github.com/microsoft/garnet/releases)
- fnm v1.39.0 (2026-03-06) ships builds for Linux, macOS and Windows. [fnm releases](https://github.com/Schniz/fnm/releases) Volta's README says "Volta is unmaintained" and recommends mise. [Volta README](https://github.com/volta-cli/volta)
- Herd Pro offers MySQL, MariaDB, PostgreSQL, MongoDB, Redis, Laravel Reverb, Typesense, Meilisearch, MinIO and RustFS as services with a binary directory and a data directory, managed with `herd services:create` and `herd services:start`; these are Pro-only. [Herd Pro services](https://herd.laravel.com/docs/macos/herd-pro-services/services)
- Symfony CLI does not run databases; it detects Docker Compose services and exposes them as environment variables such as `DATABASE_URL`, `DATABASE_HOST` and `DATABASE_PORT`. [Symfony CLI docs](https://symfony.com/doc/current/setup/symfony_cli.html)
- DDEV snapshots stream `mariadb-backup` (or `mariabackup`, or `xtrabackup` for MySQL and old MariaDB) with `--stream=mbstream` or `xbstream`, and PostgreSQL snapshots use `pg_basebackup` plus `tar`. [ddevapp.go:3333-3349@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/ddevapp.go#L3333-L3349)
- Snapshot files live in `.ddev/db_snapshots` with names like `<name>-mariadb_10.11.zst`; they are compressed with zstd, or gzip for MariaDB 5.5. [snapshot.go:31-60@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/snapshot.go#L31-L60); [snapshot_test.go:213@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/snapshot_test.go#L213)
- systemd user managers can run without an open session when lingering is enabled with `loginctl enable-linger`, and units restart through `Restart=`. [loginctl.xml:186-190@a31540ec](https://github.com/systemd/systemd/blob/a31540ec8272da2ba9a98c98629917d12ad77c78/man/loginctl.xml#L186-L190); [systemd.service.xml:876@a31540ec](https://github.com/systemd/systemd/blob/a31540ec8272da2ba9a98c98629917d12ad77c78/man/systemd.service.xml#L876)

### Inferences
- Default database for the MVP: follow the project. Imported DDEV projects ask for MariaDB (DDEV's default is 11.8). New projects can start on SQLite, which needs no service, but only if the shipped PHP links SQLite 3.45 or newer for Drupal 11.
- MariaDB and MySQL in the MVP: detect an installed server (Homebrew `mariadb@11.8`, a distribution package, or a configured path), then give each project its own data directory, port and unix socket, initialized with `mariadb-install-db --auth-root-authentication-method=normal --skip-test-db` or `mysqld --initialize-insecure`. One binary install serves many projects. Downloaded binaries can follow on Linux x86_64 and Windows, where MariaDB publishes them. macOS has no official MariaDB binary, so Homebrew stays the source there.
- PostgreSQL after the MVP: the theseus-rs archives cover all three OSes with one format.
- Mail: download Mailpit, run it per user on 127.0.0.1:1025 for SMTP and a UI port, and set `sendmail_path` to `mailpit sendmail`, as DDEV does.
- Cache: Valkey or Redis on Linux and macOS from packages; on Windows, Garnet is the open-source candidate. The Drupal Redis module needs a compatibility test against Garnet.
- Solr needs Java and is out of scope for the MVP. Projects that need Solr or Elasticsearch use the Docker hybrid: if Docker or Podman is present, start only those services from `.ddev/docker-compose.*.yaml`.
- Supervisor: the tool runs a per-user background process that starts unitd, databases and Mailpit as children, restarts them with back-off, and writes one log file per service in the tool's data directory. An optional login item (launchd agent, systemd user unit with lingering, Task Scheduler on Windows) starts it at login. Windows services need administrator rights, so they should not be the default.
- Data layout: keep databases outside the project (for example `<data dir>/projects/<name>/mariadb`) so that file watchers, IDE indexers and backups of the source tree do not see them. Snapshots can stay in `.ddev/db_snapshots` for imported projects, so both tools see them.
- Snapshots: use `mariadb-backup` when the installed server has it, compressed with zstd, which keeps the DDEV format. Fall back to `mariadb-dump` when the versions differ.

### Gaps
- Restoring a DDEV `.zst` MariaDB snapshot into a native server was not tested. Experiment: take a snapshot with DDEV (MariaDB 11.8), then run `zstd -d`, `mbstream -x`, `mariadb-backup --prepare` and `--copy-back` into a native 11.8 data directory, and start it.
- Memurai's current licence and price for developers were not read on Memurai's own site. Source needed: memurai.com licence page.
- Whether the Drupal Redis module (with phpredis or Predis) passes its tests against Garnet is unknown. Experiment: run the module's test suite against Garnet 2.2.0.
- MySQL's macOS archive format and size were not visible in the fetched page. Source needed: the macOS tab of the MySQL download page.
- Snapshot and restore speed versus a SQL dump was not measured. Experiment: time both on a 1 GB Drupal database.

## 8. Can FreeUnit itself supervise processes?

### Takeaway
No, not for arbitrary binaries. An `external` application is started with `execve()`, but it counts as started only after it calls `nxt_unit_init()` and sends the libunit READY message. `mariadbd` or Mailpit would never send it, so FreeUnit waits forever by default, or gives up and kills the process when `limits.start_timeout` is set. FreeUnit's planned schedules send periodic HTTP requests to applications; they help with Drupal cron, not with service supervision.

### Cited Findings
- `nxt_external_start()` clears close-on-exec on the inherited port descriptors, writes the FreeUnit version, stream, port identities and descriptors, shared queue descriptors, shared-memory limit and request limit into the `NXT_UNIT_INIT` variable, then calls `execve(executable, argv, environ)`. [src/nxt_external.c:59-201@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_external.c#L59-L201)
- libunit reads `NXT_UNIT_INIT` in `nxt_unit_init()` and reports readiness with a `_NXT_PORT_MSG_PROCESS_READY` message. [src/nxt_unit.c:1012,1110@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_unit.c#L1110)
- The FreeUnit docs describe the type as running "arbitrary executables, provided they establish communication with Unit". [configuration/index.rst:4635@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L4635)
- The default start timeout is `NXT_APP_START_TIMEOUT`, which is 0. [src/nxt_router.h:144@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_router.h#L144); [src/nxt_router.c:3172@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_router.c#L3172)
- With 0 FreeUnit waits indefinitely; with a value it stops waiting, logs the failure, and fails a reconfiguration that was in progress. [configuration/index.rst:4143-4157@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L4143-L4157)
- When the router gives up, the prototype process kills the worker, because it is the only process that knows the worker's pid. [src/nxt_application.h:151-157@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_application.h#L151-L157); [src/nxt_application.c:995-1011@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_application.c#L995-L1011)
- Without a `processes` object an application gets 1 process, at most 1, no spare processes and a 15-second idle timeout; an integer `processes` sets max and spare to that number. [src/nxt_router.c:3168-3173,3221-3223@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_router.c#L3168-L3173)
- After a worker exits, the router starts a replacement only when queued requests or the spare count need one. [src/nxt_router.c:2371-2376,7756@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_router.c#L2371-L2376)
- `limits.start_timeout` was added by a merged pull request and is in the 1.37.0 tag. [PR 280](https://github.com/freeunitorg/freeunit/pull/280)
- Schedules are an open pull request for 1.38.0, marked "Do not merge before the 1.37.1 tag". A top-level `schedules` object makes FreeUnit send periodic internal requests to an application, with `interval`, `jitter`, `timeout`, `overlap` and `headers`. Each run counts in `/status` and the access log. Drupal cron is the example. [PR 452](https://github.com/freeunitorg/freeunit/pull/452)
- The open Drupal dev kit pull request turns `DRUPAL_CRON_KEY` into such a schedule. [PR 453](https://github.com/freeunitorg/freeunit/pull/453)

### Inferences
- FreeUnit is the supervisor for PHP workers only. It starts them on demand, restarts them after configuration changes, and keeps spare processes alive.
- A wrapper could link libunit, send READY, then fork `mariadbd` and supervise it. That is fragile: FreeUnit would restart the wrapper on every change to that application, would count it in the worker pool, and would expect it to answer requests. The tool should not do this.
- Drupal cron should become a FreeUnit schedule when the feature ships, replacing the cron runs that DDEV users usually configure in the web container. Until then the tool can call `drush cron` on a timer.
- An upstream change that would make FreeUnit a general supervisor (a `service` application type without libunit, with restart policy) is a large feature. It is not needed for this tool.

### Gaps
- What FreeUnit does with an external application that exits right after `execve()` in a loop (restart rate, back-off) was not tested. Experiment: configure `"type": "external"` with `"executable": "/bin/false"` and `"processes": 1`, then watch the log for one minute.

## 9. Developer workflow surface

### Takeaway
Offer DDEV's verbs where they make sense on the host. Run DDEV hooks and custom commands on the host with a DDEV-like environment. Switch Xdebug by swapping the project's php.ini and reloading only that application. Make every system change reversible with one `clean` command. Telemetry stays off unless the user opts in, as in DDEV.

### Cited Findings
- DDEV's command set includes `start`, `stop`, `restart`, `poweroff`, `list`, `describe`, `exec`, `ssh`, `logs`, `import-db`, `export-db`, `import-files`, `snapshot`, `snapshot restore`, `share`, `composer`, `config`, `delete`, `clean`, `hostname`, `pull`, `push`, `xhgui`, `mutagen` and add-on commands. [cmd/ddev/cmd@598e255e](https://github.com/ddev/ddev/tree/598e255e233f060dd371a9abc46c73742695f9b4/cmd/ddev/cmd)
- DDEV ships `drush`, `xdebug`, `php`, `npm` and other commands as web-container custom commands, and `launch`, `mailpit`, `self-upgrade` and database GUI launchers as host commands. [global_dotddev_assets/commands@598e255e](https://github.com/ddev/ddev/tree/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/global_dotddev_assets/commands)
- Custom commands declare metadata in `##` comment lines such as `Description`, `Usage`, `Example`, `ProjectTypes`, `ExecRaw`, `Flags`, `AutocompleteTerms` and `MutagenSync`; `drush` declares `ProjectTypes: drupal,drupal12,...,backdrop`. [commands/web/drush:4-9@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/global_dotddev_assets/commands/web/drush#L4-L9); [commands/web/xdebug:2-8@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/global_dotddev_assets/commands/web/xdebug#L2-L8)
- DDEV has 28 hook points (for example `pre-start`, `post-start`, `post-import-db`, `pre-snapshot`, `post-stop`) and task types `exec` (in a container), `exec-host` and `composer`. [schema.json@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/schema.json)
- `ddev xdebug` accepts `on`, `off`, `enable`, `disable`, `true`, `false`, `toggle`, `status` and `info`; enabling runs `phpenmod xdebug` and `killall -USR2 php-fpm`. [enable_xdebug@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/containers/ddev-webserver/ddev-webserver-base-files/usr/local/bin/enable_xdebug)
- FreeUnit's Drupal dev kit pull request configures Xdebug with `xdebug.mode=debug,develop`, `xdebug.start_with_request=trigger` and `xdebug.client_port=9003`. [drupal-dev.sh:106-113 in PR 453](https://github.com/freeunitorg/freeunit/blob/3733c7287f173042e8dbff9f4d87f662626a31b3/pkg/docker/drupal/drupal-dev.sh#L106-L113)
- FreeUnit restarts one application with `GET /control/applications/<name>/restart`, letting old processes finish while new ones start. [configuration/index.rst:3625-3650@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L3625-L3650); [src/nxt_controller.c:2937@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_controller.c#L2937)
- FreeUnit's `/status` reports modules, connections, requests and per-application data. [src/nxt_status.c:27-38@872bf041](https://github.com/freeunitorg/freeunit/blob/872bf04170756e919211799ec5574000d628d521/src/nxt_status.c#L27-L38)
- DDEV share providers are scripts: `ngrok.sh` and `cloudflared.sh`, plus a `localtunnel` example; `share_default_provider` selects one. [share-providers@598e255e](https://github.com/ddev/ddev/tree/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/dotddev_assets/share-providers); [config.md:617@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/configuration/config.md#L617)
- DDEV checks for new versions against GitHub releases. [updatecheck.go:14@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/updatecheck/updatecheck.go#L14)
- DDEV asks for opt-in usage reporting on first start, stores it as `instrumentation_opt_in`, honours `DDEV_NO_INSTRUMENTATION=true`, and never collects data when `CI=true`. [diagnostics.md@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/docs/content/users/usage/diagnostics.md); [global_config.go:45@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/globalconfig/global_config.go#L45)
- DDEV maintains a GitHub Action to set up DDEV in workflows. [ddev/github-action-setup-ddev](https://github.com/ddev/github-action-setup-ddev)
- Valet's commands include `install`, `park`, `link`, `secure`, `isolate`, `share` (with a choice of share tool), `trust`, `use`, `php`, `composer` and `uninstall`. [cli/app.php@874dd14d](https://github.com/laravel/valet/blob/874dd14d58b5cec2f3c869f4f755627179b28c16/cli/app.php)
- Symfony CLI picks the PHP version from a `.php-version` file in the project or a parent directory. [Symfony CLI docs](https://symfony.com/doc/current/setup/symfony_cli.html)

### Inferences
- Command set for the first release. Lifecycle: `init` (writes native config or reads `.ddev/`), `start`, `stop`, `restart`, `poweroff`, `list`, `describe`, `launch`, `logs` (unitd, PHP stderr, database, Mailpit). Tools: `exec`, `drush`, `composer`, `php`, `mysql` or `psql`. Data: `import-db`, `export-db`, `import-files`, `snapshot` (create, list, restore). Other: `xdebug on|off|status`, `share`, `trust`, `self-update`, `clean`, `diagnose`. There is no `ssh`: `exec` runs a host shell with the project environment.
- `drush`, `composer` and `php` run the project's PHP CLI with the same php.ini as its FreeUnit application, `IS_DDEV_PROJECT=true` in DDEV mode, `DRUSH_OPTIONS_URI`, and `vendor/bin` on `PATH`.
- Hooks: `exec-host` runs as is; `exec` runs on the host in the project root with the same environment; `composer` runs the tool's Composer. `service:` values other than `web` print a warning.
- DDEV custom commands: `.ddev/commands/host/*` run as they are; `.ddev/commands/web/*` run on the host with `DDEV_*` variables set, after a check of `ProjectTypes`. Container paths such as `/var/www/html` need a translation table, so some commands will fail.
- Xdebug toggle: write two php.ini variants per project; `xdebug on` points `options.file` at the Xdebug variant and PUTs that application's config, which restarts only its workers; Xdebug 3 with `start_with_request=trigger` and client port 9003 needs no IDE path mappings natively.
- CI mode (`--ci`, or `CI=true`): no CA, no hosts entries, no prompts, HTTP on 127.0.0.1:8080, SQLite or service-container databases, non-zero exit codes on hook failure. GitHub Actions and GitLab CI then use the same commands as developers.
- Updates: check GitHub releases at most once a day, print a notice, update only on `self-update` or through the package manager.
- Telemetry: off by default, an explicit opt-in prompt, disabled whenever `CI=true`.
- Uninstall: `clean --all` removes the CA from the system store and NSS databases, hosts entries the tool added, the `setcap` capability or launchd daemon, login items, the per-user data directory, and generated `settings.ddev.php` files that still carry the signature.
- Share: start with `cloudflared` quick tunnels and `ngrok`, as DDEV does, and add the tunnel host to the route `host` match. DDEV's generated `trusted_host_patterns = ['.*']` already accepts it.

### Gaps
- Which DDEV web commands depend on container-only paths was not inventoried. Experiment: run every command under `commands/web` of the DDEV clone on the host with `DDEV_*` variables set, and list failures.
- Whether the PhpStorm and VS Code DDEV integrations can be pointed at a native tool was not checked. Source needed: the plugins' repositories.

## 10. Distribution

### Takeaway
Ship like DDEV: goreleaser, GitHub Releases with checksums, an install script, a Homebrew tap formula, notarized macOS binaries, then deb and rpm repositories. Add winget and Scoop with the native Windows build. Do not depend on FreeUnit's Homebrew tap or package repositories yet: the tap is an unmaintained NGINX Unit fork, and packages.freeunit.org hosts build inputs, not packages. Pin one tested FreeUnit version per tool release.

### Cited Findings
- DDEV's goreleaser config publishes a Homebrew tap and an edge tap, deb and rpm packages to Cloudsmith for any distribution, AUR packages, and Windows installers; a Chocolatey block is commented out. [.goreleaser.yml:247,314,368,436,526,662@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/.goreleaser.yml#L526)
- DDEV notarizes macOS binaries with an Apple ID, app-specific password and team ID, and signs Windows binaries and the installer with `signtool`. [Makefile:385-429@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/Makefile#L385-L429)
- Symfony CLI publishes a Homebrew tap, signs artifacts with cosign, and pushes deb, rpm and Alpine packages to Cloudsmith. [.goreleaser.yml:74-92@a9011678](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/.goreleaser.yml#L74-L92); [releaser.yml:145-151@a9011678](https://github.com/symfony-cli/symfony-cli/blob/a9011678fe13f2d6778768a09a01c94ac8e519e9/.github/workflows/releaser.yml#L145-L151)
- The Apple Developer Program costs USD 99 per year and covers Developer ID certificates and notarization for apps distributed outside the Mac App Store. [Apple Developer Program](https://developer.apple.com/programs/)
- Homebrew 5.0.0 deprecated casks without code signing and will disable Homebrew/homebrew-cask casks that fail Gatekeeper checks in September 2026. [Homebrew 5.0.0](https://brew.sh/2025/11/12/homebrew-5.0.0)
- Microsoft Artifact Signing (formerly Trusted Signing) issues Public Trust certificates to organizations in the United States, Canada, the EU, the UK, Australia, New Zealand, Japan, South Korea, Singapore, Switzerland, Norway and Israel. Individual developers must be in the United States or Canada. It needs a paid Azure subscription. [Artifact Signing quickstart](https://learn.microsoft.com/en-us/azure/artifact-signing/quickstart); [Artifact Signing FAQ](https://learn.microsoft.com/en-us/azure/artifact-signing/faq)
- Its Basic tier includes 5,000 signatures per month and Premium 100,000; the pricing page shows prices only after a region is chosen. [Artifact Signing pricing](https://azure.microsoft.com/en-us/pricing/details/trusted-signing/)
- SmartScreen reputation for signed files builds up per file hash with download history. [Artifact Signing FAQ](https://learn.microsoft.com/en-us/azure/artifact-signing/faq)
- FreeUnit released 1.35.1 (2026-04-03), 1.35.2 (2026-04-04), 1.35.3 (2026-04-07), 1.35.4 (2026-04-30), 1.35.5 (2026-05-29), 1.36.0 (2026-07-17), 1.36.1 (2026-08-29) and 1.37.0 (2026-10-04). [FreeUnit releases](https://github.com/freeunitorg/freeunit/releases)
- FreeUnit images are published on GitHub Container Registry. [migration.rst:604-614@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/migration.rst#L604-L614)
- DDEV projects can pin a DDEV version range with `ddev_version_constraint`. [schema.json@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/schema.json)

### Inferences
- A Homebrew formula (not a cask) for a CLI is not affected by the September 2026 cask rule, but notarizing anyway avoids Gatekeeper prompts for users who download the binary in a browser.
- Windows signing for an open-source project run by individuals outside the US and Canada needs an organization identity or another signing service. That decision can wait for the native Windows build; the WSL2 interim path needs only the Linux binary.
- FreeUnit shipped 8 releases in 6 months, including point releases days apart. The tool should pin one FreeUnit version per release, download the matching unitd and PHP pairs, and test the pair in CI on every FreeUnit release.
- Build infrastructure is the real cost: macOS arm64 and x86_64 runners plus Linux runners to build unitd, PHP 8.3 to 8.5 and Xdebug for each OS and CPU, about 10 artifacts per PHP minor version.

### Gaps
- Whether Gatekeeper blocks a notarized but unstapled CLI binary fetched by `curl` was not tested. Experiment: install through the script on a clean macOS 26 machine with and without `xattr -d com.apple.quarantine`.
- winget, Scoop and Chocolatey signing rules were not read. Source needed: their submission policies.
- SignPath Foundation terms for open-source signing were not read. Source needed: signpath.org.

## 11. MVP cut: what does the first public release do and not do?

### Takeaway
The first release should target Linux x86_64 and arm64 and macOS arm64, with Drupal 10.4 and later, 11 and 12 on PHP 8.3 to 8.5. It runs one per-user FreeUnit instance with `.localhost` names and HTTPS. It uses MariaDB or MySQL from a local install plus SQLite, runs Mailpit, and imports DDEV projects live. It waits for a FreeUnit release that contains the Apple Silicon fix.

### Cited Findings
- The Apple Silicon fix for libunit applications merged on 2026-10-07 and is not in a release yet. [freeunit issue 595](https://github.com/freeunitorg/freeunit/issues/595); [FreeUnit releases](https://github.com/freeunitorg/freeunit/releases)
- Drupal 10.4 to 10.6 support PHP 8.1 to 8.4, Drupal 11.3 and 11.4 support PHP 8.3 to 8.5, and Drupal 12.0 supports only PHP 8.5. [Drupal PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements)

### Inferences
**In the MVP:**
- Platforms: Linux x86_64 and arm64 (glibc), macOS arm64. Windows users run the Linux build in WSL2.
- One Go binary. One per-user unitd started and supervised by the tool. Ports 80/443 after one `setcap` on Linux and with no setup on macOS (wildcard bind plus a loopback `source` guard); 8080/8443 otherwise.
- Project config: native YAML plus local override; live read of `.ddev/config*.yaml` with DDEV's merge rules; generated `settings.ddev.php` and `IS_DDEV_PROJECT=true`; FreeUnit Drupal routes from the official how-to.
- Names: `<name>.localhost`, `*.<name>.localhost`, imported `additional_fqdns` and `*.ddev.site` aliases; opt-in hosts entries through an elevated helper.
- TLS: own CA or an existing mkcert CA, per-project leaves for 396 days, in-place renewal through `PUT /certificates/<name>`, SNI on one listener.
- PHP: tool-shipped pairs for PHP 8.3, 8.4 and 8.5 (CLI, shared libphp, FreeUnit module, Xdebug) built with static-php-cli; Drupal's required extensions.
- Services: MariaDB or MySQL from a local install with per-project data directories, SQLite, Mailpit. Node through fnm.
- Commands from section 9, hooks, DDEV custom commands on the host, snapshots with `mariadb-backup` or a dump, `xdebug on|off`, `share` through cloudflared or ngrok, CI mode, `clean`.
- Distribution: GitHub Releases, install script, Homebrew tap formula, notarized macOS binaries.

**Not in the MVP:**
- Native Windows (waits for the FreeUnit Windows build), macOS x86_64 (an extra build target, add when demand shows).
- PostgreSQL (portable builds exist; add next), Redis or Valkey, Solr, Elasticsearch, XHGui and Blackfire.
- Custom nginx or Apache rules from `.ddev/`; Docker Compose add-ons (the Docker hybrid comes later).
- Drupal 7 and older and other DDEV project types.
- deb and rpm repositories, winget, Scoop, Chocolatey; telemetry beyond an opt-in stub.
- HTTP/2 (waits for FreeUnit's HTTP/2 pull requests); cron schedules (wait for FreeUnit 1.38.0); multi-user machine-wide instances.

### Gaps
- Demand for macOS x86_64 and for PostgreSQL among Drupal developers is unknown. Source needed: the Drupal developer survey or DDEV's public usage statistics at https://ddev.com/usage-stats/.

## 12. Open questions and the experiment that answers each

### Takeaway
Six experiments decide whether the architecture holds. They test the static-php-cli plus FreeUnit module build, the Apple Silicon fix in a release, and the isolation of one broken project in a shared unitd. They also test DDEV snapshot restore, OS resolver behaviour for `*.localhost` on Windows and macOS 15, and a corpus of real DDEV Drupal projects.

### Cited Findings
- Experiment 1 rests on static-php-cli's shared embed option and glibc target. [unix.php:331-339@932096fe](https://github.com/crazywhalecc/static-php-cli/blob/932096fe288c801e9d978870de2b014414a3ccb8/src/Package/Target/php/unix.php#L331-L339)
- Experiment 2 rests on the Apple Silicon failure in 1.37.0. [freeunit issue 595](https://github.com/freeunitorg/freeunit/issues/595)
- Experiment 3 rests on the documented reconfiguration behaviour of `start_timeout`. [configuration/index.rst:4143-4157@c5a37256](https://github.com/freeunitorg/docs/blob/c5a372568cd3ebb7d4ed9ef13b23ef5d2584f028/source/configuration/index.rst#L4143-L4157)
- Experiment 4 rests on DDEV's snapshot format. [ddevapp.go:3333-3349@598e255e](https://github.com/ddev/ddev/blob/598e255e233f060dd371a9abc46c73742695f9b4/pkg/ddevapp/ddevapp.go#L3333-L3349)
- Experiment 7 rests on the macOS 26.3.1 resolver report. [gist by adamamyl](https://gist.github.com/adamamyl/81b78eced40feae50eae7c4f3bec1f5a)

### Inferences
- Run experiments 1 and 2 before any tool code: if FreeUnit cannot load a module built against static-php-cli's shared embed library, the PHP provisioning decision changes to "distribution packages on Linux and a Homebrew-based formula on macOS".

### Gaps
| # | Open question | Experiment that answers it |
|---|---|---|
| 1 | Can FreeUnit load a PHP module built against static-php-cli's shared embed library on Linux (glibc 2.17 target) and macOS, with Xdebug as a shared extension? | Build PHP 8.4 with `--build-cli --build-embed` and `SPC_CMD_VAR_PHP_EMBED_TYPE=shared`, configure FreeUnit with that `php-config` and `--lib-path`, load the module, run FreeUnit's PHP tests and a Drupal 11 install, then load `xdebug.so` through `options.file`. Repeat with argon2 compiled in and with `cap_net_bind_service` set on unitd, so the module must find `libphp` through its RUNPATH |
| 2 | Does the next FreeUnit release serve PHP on Apple Silicon? | Run the Drupal 11 install and FreeUnit's PHP tests on macOS 15 and 26 arm64 with the first release after 1.37.0 |
| 3 | Does one broken project block reconfiguration of the shared instance? | PUT a config with a broken PHP application (`spare: 0`, then `spare: 1`, with and without `start_timeout`), time the response, and check the other projects |
| 4 | Can a DDEV MariaDB snapshot be restored natively? | DDEV snapshot of MariaDB 11.8, then `zstd -d`, `mbstream -x`, `mariadb-backup --prepare` and `--copy-back` into a native 11.8 data directory |
| 5 | Do PHP stream wrappers, Node and Java resolve `foo.localhost` on Windows 11 and macOS 15? | `php -r "var_dump(gethostbynamel('foo.localhost'));"`, `node -e "require('dns').lookup('foo.localhost',console.log)"` and a Java `InetAddress.getByName` call on both systems |
| 6 | How many real DDEV Drupal projects import with zero edits? | Run the importer over 50 public repositories that contain `.ddev/config.yaml` and count unsupported fields, custom web server files and compose add-ons |
| 7 | Is the macOS 26 `/etc/resolver` regression still present? | dnsmasq plus `/etc/resolver/test` on the current macOS 26 release, then `dscacheutil -q host -a name foo.test` |
| 8 | Does a user-mode unitd on macOS bound to `*:443` trigger firewall prompts, and does the `source` guard route block LAN clients? | Run it on macOS 15 and 26 with the firewall on and request the site from another machine |
| 9 | How fast do the candidate shapes start? | `hyperfine` on `version` and `status` for Go, Rust and `micro:combine` builds |
| 10 | Does the shipped PHP link SQLite 3.45 or newer, as Drupal 11 needs? | `php -r 'echo SQLite3::version()["versionString"];'` on each build |
| 11 | Does the Drupal Redis module work against Garnet on Windows? | Run the module's tests against Garnet 2.2.0 |
| 12 | Which DDEV web custom commands break on the host? | Run each command from DDEV's `commands/web` directory on the host with `DDEV_*` variables and list failures |
