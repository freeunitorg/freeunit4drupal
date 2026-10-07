# Drupal developer requirements for a local environment, and how a new local tool gets adopted

State as of 2026-10-07. These notes feed a requirements catalogue and an adoption analysis for freeunit4drupal, a planned Docker-free local Drupal environment built on the FreeUnit application server. FreeUnit is a fork of NGINX Unit that runs PHP inside its own worker processes.

How to read the citations:

- **C12** is Drupal core commit 0f0b0d6db66 on branch 12.0.x (2026-10-03). A file cited as `core/composer.json` (C12) is at https://git.drupalcode.org/project/drupal/-/blob/0f0b0d6db666feca62732faba7da3b673d839d72/core/composer.json.
- **C12b1** is tag 12.0.0-beta1, commit ecb3345b0cc (2026-09-30): https://git.drupalcode.org/project/drupal/-/tree/12.0.0-beta1.
- **C11** is tag 11.4.8, commit 61044508071 (2026-09-26): https://git.drupalcode.org/project/drupal/-/blob/61044508071749629aaf3380aa85971e94df9e84/ followed by the path.
- **C10** is tag 10.6.18, commit 9d47634cb71 (2026-09-26): https://git.drupalcode.org/project/drupal/-/blob/9d47634cb715e6460026af444314950b99ee7b75/ followed by the path.
- **DDEV main** is https://github.com/ddev/ddev at commit 598e255e233 (2026-10-06). The latest DDEV release is v1.25.4 (2026-09-02, https://github.com/ddev/ddev/releases/tag/v1.25.4).
- **FU** is https://github.com/freeunitorg/freeunit at commit 872bf041 (2026-10-07). **FU docs** is https://github.com/freeunitorg/docs; each file is cited with its last commit.
- Requirement IDs (PLAT, SQL, PHP, SET, TEST, SVC, PERM, HOST, UNIT) and adoption IDs (ADOPT, MKT) are inferences for an MVP. Their "Drupal mechanism" and "DDEV today" cells repeat facts that are cited in the findings above each table.
- Method note: www.drupal.org blocks scripted downloads, so drupal.org pages were read through a summarising fetch tool with requests for verbatim quotes. Quotes from drupal.org are therefore close to, but not guaranteed to be, character-exact. Code facts were read directly from git.

## Platform requirements: Drupal 10.x, 11.x and 12

### Takeaway
Drupal 12 is not released on 2026-10-07. 12.0.0-beta1 came out on 2026-09-30, and 12.0.0 is planned for the week of 2026-12-07, the same week Drupal 10 reaches end of life. Drupal 12 needs PHP 8.5, MySQL 8.0 or MariaDB 10.11, PostgreSQL 18 and SQLite 3.45. Drupal 11 needs PHP 8.3 (core CI runs 8.5 as primary), MariaDB 10.6 and PostgreSQL 16. The Drush docs list Drush 13 for Drupal 10.2+ and 11 (its newest release installs only on 10.4+), and Drush 14 for Drupal 11.3+ and 12; Drush 14 has no tagged release yet.

### Cited Findings

#### Release status and support windows
- Planned dates: "Drupal 12 will be released on the week of December 7, 2026." The schedule, a plan last updated before beta1 shipped, lists 12.0.0-beta1 and 11.5.0-beta1 for the week of Sep 14, 2026, 12.0.0-rc1 for the week of Nov 09, 2026, and 12.0.0 with 11.5.0 for the week of Dec 07, 2026. [Drupal core release schedule](https://www.drupal.org/about/core/policies/core-release-cycles/schedule) (last updated 2026-08-27)
- "Drupal 10 will reach end of life on December 9, 2026." The schedule row for the week of Dec 07, 2026 reads: "Drupal 12.0.0 and 11.5.0 released. End of security support for 11.3.x and 10.6.x." The row for the week of Jun 29, 2026 reads: "Drupal 11.4.0 released. End of security support for 11.2.x and 10.5.x." So on 2026-10-07 the security-supported lines are 11.4.x, 11.3.x and 10.6.x. [Drupal core release schedule](https://www.drupal.org/about/core/policies/core-release-cycles/schedule) (last updated 2026-08-27)
- 12.0.0-beta1 was released on 30 September 2026. [Drupal 12.0.0-beta1 release notes](https://www.drupal.org/project/drupal/releases/12.0.0-beta1) (2026-09-30). In git, tag 12.0.0-alpha1 is commit 7fc0c4407fa (2026-09-02) and tag 12.0.0-beta1 is commit ecb3345b0cc (2026-09-30). There is no 12.0.0 tag. `core/lib/Drupal.php` has VERSION '12.0.0-dev' (C12), '11.4.8' (C11) and '10.6.18' (C10).
- "Drupal 11 will continue to be supported until mid-late 2028, at least until the release of Drupal 13." [Announcing Drupal 12.0.0 platform requirements](https://www.drupal.org/about/core/blog/announcing-drupal-1200-platform-requirements) (Gábor Hojtsy, 2026-01-14)

#### PHP
- `core/lib/Drupal.php` sets MINIMUM_PHP '8.5.0' and RECOMMENDED_PHP '8.5' (C12 and C12b1), '8.3.0' and '8.4' (C11), '8.1.0' and '8.4' (C10). MINIMUM_PHP_MEMORY_LIMIT is '64M' in all three. `core/composer.json` requires "php": ">=8.5.0" (C12), ">=8.3.0" (C11) and ">=8.1.0" (C10).
- "Drupal 12 will require PHP 8.5. Older versions of PHP are not supported." [Announcing Drupal 12.0.0 platform requirements](https://www.drupal.org/about/core/blog/announcing-drupal-1200-platform-requirements) (2026-01-14). The beta1 notes repeat: "Drupal 12 requires PHP 8.5." [12.0.0-beta1 release notes](https://www.drupal.org/project/drupal/releases/12.0.0-beta1) (2026-09-30)
- "From Drupal 12.0, PHP should be compiled with the argon2 support for password hashing." [PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements) (last updated 2026-08-04)
- Core CI on 11.4.8 sets PRIMARY_PHP "8.5-ubuntu", MINIMUM_PHP "8.3-ubuntu" and NEXT_PHP_MAJOR "8.6-ubuntu" (`.gitlab-ci/php.yml` lines 3 to 5, C11). On 12.0.x both PRIMARY_PHP and MINIMUM_PHP are "8.5-ubuntu" (`.gitlab-ci/php.yml` lines 3 to 5, C12). The contrib CI templates set CORE_STABLE '11.4.8', CORE_PHP_MIN '8.3' and CORE_PHP_MAX '8.5'. [gitlab_templates include.drupalci.hidden-variables.yml](https://git.drupalcode.org/project/gitlab_templates/-/blob/bd20d223/includes/include.drupalci.hidden-variables.yml) (commit bd20d223, 2026-10-06)

#### Databases
- Minimum versions in the install task classes, read at each commit:

  | Driver constant | Drupal 10.6.18 (C10) | Drupal 11.4.8 (C11) | Drupal 12.0.x (C12) |
  |---|---|---|---|
  | MYSQL_MINIMUM_VERSION | 5.7.8 | 8.0 | 8.0 |
  | MARIADB_MINIMUM_VERSION | 10.3.7 | 10.6 | 10.11 |
  | PGSQL_MINIMUM_VERSION | 12 | 16 | 18 |
  | SQLITE_MINIMUM_VERSION | 3.26 | 3.45 | 3.45 |

  Files: `core/modules/mysql/src/Driver/Database/mysql/Install/Tasks.php`, `core/modules/pgsql/src/Driver/Database/pgsql/Install/Tasks.php`, `core/modules/sqlite/src/Driver/Database/sqlite/Install/Tasks.php`.
- The drupal.org table matches the code for all three majors and adds: InnoDB as the storage engine, and "the `pg_trgm` extension is enabled" for PostgreSQL. [Database server requirements](https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements) (last updated 2026-09-04). The PostgreSQL installer runs `CREATE EXTENSION IF NOT EXISTS pg_trgm` and fails if it is missing (`core/modules/pgsql/src/Driver/Database/pgsql/Install/Tasks.php` lines 259 to 273, C11).
- The 2026-01-14 announcement gives MySQL 8.0, MariaDB 10.11 (raised from 10.6), PostgreSQL 18 (raised from 16) and SQLite 3.45. [Announcing Drupal 12.0.0 platform requirements](https://www.drupal.org/about/core/blog/announcing-drupal-1200-platform-requirements) (2026-01-14)
- Conflict: the beta1 notes say "The PostgreSQL database driver requires PostgreSQL 19." [12.0.0-beta1 release notes](https://www.drupal.org/project/drupal/releases/12.0.0-beta1) (2026-09-30). Contradicted by PGSQL_MINIMUM_VERSION = '18' at C12b1 and C12, by `core/INSTALL.txt` (C12) "PostgreSQL 18 (or greater)", and by the database requirements page (18).
- Drupal 12 core also contains a `core/modules/mysqli` driver module (C12). Core CI on 12.0.x uses mysql-8.4 as its default database and has jobs for mysql-8, mysql-8.4, mysql-9, mysqli on mysql-9, mariadb-10.11, mariadb-12.3, pgsql-18 and sqlite-3 (`.gitlab-ci.yml` lines 79 to 590, C12).

#### Web server and memory
- `core/INSTALL.txt` (C12) lists "A web server with PHP support, for example: Apache 2.4.7 (or greater) ... Nginx 1.1 (or greater)", and sends readers to "Local server setup" (https://www.drupal.org/node/157602) for local environments.
- The web server requirements page names Apache ("the most commonly used web server for Drupal"), Nginx, Microsoft IIS (not supported as of Drupal 11.0.0) and the PHP built-in web server. It names no other server: no NGINX Unit, FrankenPHP or Caddy. It also says "Drupal and PHP should have read and write access to the /sites/default/files directory." [Web server requirements](https://www.drupal.org/docs/getting-started/system-requirements/web-server-requirements) (last updated 2026-09-13)
- The status report shows `SERVER_SOFTWARE` as the web server (`core/modules/system/src/Hook/SystemRequirementsHooks.php` lines 203 to 209, C12). The interactive installer runs its clean URL test only when that string contains "Apache" (`core/modules/system/src/Install/Requirements/SystemRequirements.php` lines 104 to 120, C12).
- Memory: the PHP requirements page gives a "64MB" minimum and says production systems typically use "128 MB or 256 MB". [PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements) (last updated 2026-08-04). The quick start in `core/INSTALL.txt` (C12 and C11) runs `php -d memory_limit=256M vendor/bin/dr quick-start standard`.

#### Drush
- The Drush compatibility table says Drush 14 (PHP 8.3+, end of life TBD) supports Drupal 11.3+ and 12. Drush 13 (PHP 8.3+) supports Drupal 10.2+ and 11. These cells carry the mark the legend defines as "Supported and recommended". Drush 12 (PHP 8.1+) has the other mark for Drupal 10, which the legend defines as "Compatible but no longer supported". [Drush install docs](https://www.drush.org/13.x/install/) (accessed 2026-10-07; https://www.drush.org/latest/install/ redirects here)
- "Drush only supports one install method. It requires that your Drupal site be built with Composer and Drush be listed as a dependency." [Drush install docs](https://www.drush.org/13.x/install/) (accessed 2026-10-07)
- The newest Drush tag is 13.8.0 (2026-09-08). There is no 14.x tag. Branch 14.x was last updated on 2026-09-29, and its composer.json conflicts with drupal/core "< 11.3". [drush-ops/drush tags](https://github.com/drush-ops/drush/tags) and [branch 14.x](https://github.com/drush-ops/drush/tree/14.x) (accessed 2026-10-07)
- Drush 13.8.0 requires "php": ">=8.3" and declares the conflict "drupal/core": "<10.4 <12.0". Composer reads a space as AND, so as written this blocks cores below 10.4 only. The newest Drush 13 therefore installs on Drupal 10.4 or newer, although the docs table says 10.2+. [drush 13.8.0 composer.json](https://github.com/drush-ops/drush/blob/13.8.0/composer.json) (2026-09-08)

#### Composer
- `core/INSTALL.txt` lists "Composer 2.9.3" as a quick start prerequisite (C12 and C11). The platform announcement names Composer 2.9.3 as the latest secure release. [Announcing Drupal 12.0.0 platform requirements](https://www.drupal.org/about/core/blog/announcing-drupal-1200-platform-requirements) (2026-01-14)
- `core/composer.json` requires composer-runtime-api ^2.1 and php-tuf/composer-stager ^2.0 (C12 and C11). Package Manager accepts Composer `^2.7` (`ComposerInspector::SUPPORTED_VERSION`, `core/modules/package_manager/src/ComposerInspector.php` line 57, C12 and C11).
- `core/composer.json` declares the binary `scripts/dr`, which Composer installs as `vendor/bin/dr` (C12 and C11).

#### Development tool versions that run on the PHP CLI
- `composer/Metapackage/DevDependencies/composer.json` requires phpunit/phpunit ^12.5.22, drupal/coder ^9.0, phpstan/phpstan ^2.2.14 and mglaman/phpstan-drupal ^2.2.1 (C12). At C11 it requires phpunit/phpunit ^11.5.50 and drupal/coder ^8.3.30.

#### What DDEV does
- DDEV reads `core/lib/Drupal.php` to detect the Drupal major. The project type drupal12 sets PHP 8.5 and enables corepack. The plain "drupal" type falls through to the drupal11 defaults, while the settings template follows the major that DDEV detects in core/lib/Drupal.php (lines 316 to 337). [pkg/ddevapp/drupal.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/drupal.go) lines 316 to 458 (last changed e1ba2419df3, 2026-07-31)
- DDEV defaults: PHPDefault = PHP84 ([pkg/nodeps/php_values.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/nodeps/php_values.go)), DatabaseDefault = MariaDB 11.8 ([pkg/ddevapp/ddevapp.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/ddevapp.go) line 64 and [mariadb_values.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/nodeps/mariadb_values.go)), PostgresDefaultVersion = Postgres14 ([postgres_values.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/nodeps/postgres_values.go)), NodeJSDefault "24" and WebserverDefault nginx-fpm ([values.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/nodeps/values.go)). All at DDEV main (2026-10-06).
- The DDEV 2025 review confirms PHP 8.4, Node.js 24 and MariaDB 11.8 as defaults for new projects. [DDEV 2025 Year in Review](https://ddev.com/blog/2025-review/) (2026-01-15)

### Inferences
- Drupal 12 makes PHP 8.5 mandatory. A tool that cannot ship a PHP 8.5 embed build cannot serve Drupal 12 at all.
- Drupal 10 leaves support on 2026-12-09. For a tool that ships in 2027, PHP 8.1 and 8.2 have little value. PHP 8.4 (Drupal 11 recommendation, Drupal CMS Launcher default) and 8.5 (Drupal 12, primary PHP in core CI for 11.4) are the MVP pair.
- MariaDB 10.11 or newer, or MySQL 8.0 or newer, satisfies every supported major. PostgreSQL needs 16 for Drupal 11 and 18 for Drupal 12. DDEV's PostgreSQL default (14) is below both floors, so DDEV users must pick the version by hand. A new tool can choose the database version from the detected Drupal major.
- The 12.0.0-beta1 tag landed about two weeks after the scheduled beta week (2026-09-14 versus 2026-09-30). The 12.0.0 date could also move.
- Drupal 12 users who need Drush must use an unreleased Drush 14 branch today. A tool should run the project's own `vendor/bin/drush` and never ship a global Drush.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| PLAT-01 | Ship PHP 8.4 and 8.5, each as a CLI binary and as the embed library that FreeUnit's PHP module links | `Drupal::MINIMUM_PHP`, `RECOMMENDED_PHP`, `"php"` in core/composer.json; installer and status report check them | Per-project PHP version in one container image; drupal12 type sets 8.5, default 8.4 | MVP | core/lib/Drupal.php (C12, C11); drupal.go; php_values.go |
| PLAT-02 | Offer PHP 8.3 for Drupal 11 sites pinned to the floor, and 8.1 to 8.2 only while Drupal 10 is supported | Same constants (C11, C10) | Images for PHP 5.6 to 8.5 | Later | core/lib/Drupal.php (C11, C10); php-packages.yaml |
| PLAT-03 | Detect the Drupal major from `core/lib/Drupal.php` and choose PHP and database versions from it | `Drupal::VERSION` | `drupal.go` reads core/lib/Drupal.php and maps project types | MVP | drupal.go lines 316 to 458 |
| PLAT-04 | Provide a database server that meets the floors: MariaDB 10.11+ or MySQL 8.0+ for all majors; PostgreSQL 16+ (Drupal 11) or 18+ (Drupal 12) with pg_trgm | `Install/Tasks.php::minimumVersion()` checked at install; status report `database_system_version` | `database:` type and version per project; default MariaDB 11.8 | MVP for MariaDB or MySQL; later for PostgreSQL | Tasks.php (C10, C11, C12); ddevapp.go |
| PLAT-05 | Put Composer 2.9.3 or newer on the PATH of both the shell and the PHP worker | INSTALL.txt prerequisite; Package Manager `^2.7` | Composer in the web container; `ddev composer` | MVP | INSTALL.txt (C12); ComposerInspector.php |
| PLAT-06 | Run the project's own `vendor/bin/drush` with the project's PHP | Drush supports only Composer installs | `ddev drush` runs Drush inside the container | MVP | drush.org install docs |
| PLAT-07 | Give CLI runs `memory_limit` of at least 256M | INSTALL.txt runs quick-start with `-d memory_limit=256M` | Not verified in this research | MVP | INSTALL.txt (C12) |

### Gaps
- The PostgreSQL floor for Drupal 12 conflicts between the beta1 notes (19) and the code, INSTALL.txt and requirements page (18). I could not find a correction or an issue that explains it.
- The PHP requirements page has a version matrix that the fetch tool could not render reliably. It reported Drupal 11 as "highest supported: PHP 8.4", which conflicts with core CI running PHP 8.5 as primary on 11.4.8. I treat core CI as the stronger evidence, but the page wording is unverified.
- Drush 14 has no release date. I found no announcement.
- I did not verify DDEV's default PHP memory_limit.

## SQLite as a development database

### Takeaway
Drupal core supports SQLite fully (3.45 or newer with JSON for Drupal 11 and 12) and uses it for every zero-install path: `vendor/bin/dr quick-start`, `dr install` and the Drupal CMS Launcher. The same core and community documents say this path suits evaluation, not ongoing development, and SQLite has a documented history of "database is locked" errors under concurrent writes. A zero-install SQLite default fits evaluators and site builders. Professional developers expect parity with production MySQL, MariaDB or PostgreSQL: in DDEV's usage data, 63.4% of projects use MariaDB, 30.3% MySQL and 6.3% PostgreSQL.

### Cited Findings
- SQLITE_MINIMUM_VERSION is '3.45' (C12, C11) and '3.26' (C10). The driver notes: "Use to build sqlite library with json1 option for JSON datatype support" (`core/modules/sqlite/src/Driver/Database/sqlite/Install/Tasks.php` line 21, C11). The beta1 notes say Drupal 12 "requires SQLite 3.45 with the json1 extension". [12.0.0-beta1 release notes](https://www.drupal.org/project/drupal/releases/12.0.0-beta1) (2026-09-30)
- The SQLite driver turns on write-ahead logging by default with `PRAGMA journal_mode=WAL` and emulates MySQL functions such as concat, substring_index, rand and regexp in PHP (`core/modules/sqlite/src/Driver/Database/sqlite/Connection.php` lines 125 to 149, C12; see https://www.drupal.org/node/2348137).
- `dr install` refuses to run without pdo_sqlite: "You must have the pdo_sqlite PHP extension installed. See core/INSTALL.sqlite.txt for instructions." It writes the database to `<site path>/files/.sqlite` and accepts an install profile or a recipe directory (`core/lib/Drupal/Core/Command/InstallCommand.php` lines 55 to 63, 73 to 74 and 175 to 188, C12).
- The `server` command listens on 127.0.0.1 by default, picks a free port from 8888 to 9999, and opens a one-time login URL in the browser unless `--suppress-login` is given. It runs PHP's built-in server with `.ht.router.php` (`core/lib/Drupal/Core/Command/ServerCommand.php` lines 52 to 226, C12). No file in `core/lib/Drupal/Core/Command/` sets PHP_CLI_SERVER_WORKERS (grep, C12).
- "This quick start solution uses PHP's built-in web server and is not intended for production use." (`core/INSTALL.txt`, C12)
- `.ht.router.php` returns 403 unless `PHP_SAPI` is 'cli-server' (`.ht.router.php` line 27, C12).
- Drupal issue #1120020, "SQLite database locking errors cause fatal errors": SQLite defers the write lock to the first write, and Drupal runs hooks such as hook_entity_presave() between the first read and the first write, so concurrent requests deadlock with "database is locked". The fix uses `BEGIN IMMEDIATE` transactions, at a cost of about 5 to 20% in test run time. Created 2011-04-07, closed (fixed), last updated 2022-12-18. [drupal.org/i/1120020](https://www.drupal.org/i/1120020) (2022-12-18)
- The 2018 to 2024 issue that made DDEV the recommendation describes the single-command install "#2911319: Provide a single command to install & run Drupal" ([drupal.org/i/2911319](https://www.drupal.org/i/2911319)) (PHP's built-in server and SQLite) as follows: "This may help the evaluator experience but may not be sufficient for ongoing development of a Drupal site." [project/ideas #2965681](https://git.drupalcode.org/project/ideas/-/work_items/2965681) (closed 2024-06-28)
- The Drupal CMS Launcher "Only supports SQLite databases, since SQLite is compiled into the PHP interpreter and has no additional dependencies." Its database is `web/sites/default/files/.ht.sqlite`. It uses the PHP built-in server, which "is meant for testing, does _not_ support HTTPS". It supports one site at a time and uses PHP 8.4. [cms-launcher README](https://github.com/drupal/cms-launcher/blob/9d81aebf729/README.md) (commit 9d81aebf729, 2026-01-19)
- The Launcher's `settings.local.php` points `$databases` at `sites/default/files/.ht.sqlite` with the sqlite driver. [settings.local.php](https://github.com/drupal/cms-launcher/blob/c5c55584383/settings.local.php) (commit c5c55584383, 2026-02-18)
- Test tooling defaults to SQLite: `core/.env.example` (C12) sets `DRUPAL_TEST_DB_URL=sqlite://localhost/sites/default/files/db.sqlite`, and the contrib CI templates export `SIMPLETEST_DB=sqlite://localhost/sites/default/files/db.sqlite` for SQLite jobs. [include.drupalci.main.yml](https://git.drupalcode.org/project/gitlab_templates/-/blob/bd20d223/includes/include.drupalci.main.yml) (commit bd20d223, 2026-10-06)
- DDEV's live statistics show the configured database type of DDEV projects (a count of project settings, not of users) as mariadb 63.4% (16,580), mysql 30.3% and postgres 6.3% (1,636). The page warns that the Amplitude data is "significantly incomplete" because of a free-tier event quota. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07; most sections cover about the last 7 days)

### Inferences
- SQLite is the right default for an "evaluate or site-build" mode, because core, the Launcher and the CI templates already treat it as the zero-dependency choice. It is the wrong only option for developers: about 94% of DDEV projects use MariaDB or MySQL, which matches production hosting.
- FreeUnit runs several PHP worker processes at once (`processes.max`), while PHP's built-in server runs one process unless PHP_CLI_SERVER_WORKERS is set. More concurrent writers mean more chances of "database is locked", even with WAL. In SQLite mode the tool should keep the worker count low, or accept the risk and document it. This needs a measurement.
- Moving a site from SQLite to MariaDB later is a data migration, not a settings change. The tool should ask for the database type at project creation.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| SQL-01 | Ship pdo_sqlite and sqlite3 linked against SQLite 3.45 or newer with JSON support | SQLITE_MINIMUM_VERSION, `dr install` pdo_sqlite check | sqlite3 PHP package in the web image | MVP | Tasks.php (C12); InstallCommand.php (C12); php-packages.yaml |
| SQL-02 | Offer a zero-install SQLite mode that writes the database under `sites/default/files/` (`.ht.sqlite` or `.sqlite`) | `dr install` writes `<site>/files/.sqlite`; Launcher writes `.ht.sqlite` | No SQLite mode found in DDEV's documentation in this research | MVP | InstallCommand.php; Launcher settings.local.php |
| SQL-03 | Offer MariaDB or MySQL as the default for developer projects and make the choice explicit at creation | Database drivers and `$databases` in settings.php | Default MariaDB 11.8 | MVP | ddevapp.go; usage stats |
| SQL-04 | In SQLite mode, limit concurrent PHP writers or document the "database is locked" risk | SQLite write lock semantics; WAL pragma | Not applicable | MVP (documented), later (measured tuning) | [drupal.org/i/1120020](https://www.drupal.org/i/1120020); Connection.php (C12) |
| SQL-05 | Make sure the deny rules hide `.sqlite` and `.ht.sqlite` files from web access | Apache `FilesMatch` denies dot files; Launcher relies on the PHP server | nginx config denies dot files (not verified in this research) | MVP | htaccess (C12); see UNIT rows |

### Gaps
- I found no survey or poll that asks Drupal developers directly whether a SQLite default is acceptable. The evidence is indirect: the 2024 decision text, the Launcher's stated audience and DDEV's database shares.
- I did not find a current core issue list of contrib modules that break on SQLite. The emulated MySQL functions in the driver show the problem exists, but I have no per-module data.
- I did not measure FreeUnit with SQLite under concurrent requests.

## The PHP CLI dependency and PHP extensions

### Takeaway
Most Drupal work happens on the PHP CLI: Drush, Composer, `vendor/bin/dr` (quick-start, install, recipes), PHPUnit, PHPStan and phpcs. Drupal also starts the PHP CLI from inside web requests: Package Manager runs Composer, and the Drupal CMS installer finds a `php` binary through Symfony's PhpExecutableFinder. The CLI binary and the FreeUnit worker therefore have to be the same PHP build with the same extensions and ini settings. Core requires 14 extensions and recommends OPcache and APCu. DDEV's image ships about 27 PHP packages, including apcu, intl, imagick, redis, memcached, uploadprogress, xdebug and xhprof. The Launcher's static PHP has 25 extensions and no intl, apcu, pdo_mysql or pdo_pgsql.

### Cited Findings

#### What core requires
- `core/composer.json` requires ext-PDO, ext-SPL, ext-SimpleXML, ext-date, ext-dom, ext-filter, ext-gd, ext-hash, ext-json, ext-pcre, ext-session, ext-tokenizer, ext-xml and ext-zlib (C12 and C11). C10 has the same list without ext-zlib. C11 and C10 also "suggest" ext-zip: "Needed to extend the plugin.manager.archiver service capability with the handling of files in the ZIP format." C12 has no suggest entry.
- At runtime the installer and status report check the same 14 extensions: date, dom, filter, gd, hash, json, pcre, pdo, session, SimpleXML, SPL, tokenizer, xml, zlib (`core/modules/system/src/Install/Requirements/SystemRequirements.php` lines 223 to 241, C12).
- The status report calls OPcache "highly recommended" (`php_opcache`), warns when APCu has less than 32 MB (`php_apcu_enabled`), and warns at 75% or errors at 90% APCu memory use (`php_apcu_available`) (`core/modules/system/src/Hook/SystemRequirementsHooks.php` lines 321 to 330 for OPcache and 335 to 379 for APCu, C12; the install-time OPcache check is in SystemRequirements.php lines 271 to 282, C12). Other checks include `xdebug_max_nesting_level`, `output_buffering` and `php_session_samesite`.
- Core uses APCu when it is present: `core/lib/Drupal/Core/DrupalKernel.php`, `core/lib/Drupal/Core/Site/Settings.php` and `core/lib/Drupal/Core/Cache/ApcuBackend.php` all reference it (C12).
- The PHP requirements page says (quoted lines): "The PHP Data Objects (PDO) extension must be activated for Drupal 9 and higher to install and run correctly."; "PHP XML extension."; "An image library for PHP such as the GD library is a required extension in Drupal 9 and higher."; "ImageMagick is also supported for image manipulations in Drupal core."; "The PHP OpenSSL extension is recommended to allow Drupal to make outgoing requests using HTTPS."; "The PHP cURL extension is required for automated testing in Drupal 9 and higher."; "The PHP mbstring extension provides multibyte specific string functions used for Drupal installation."; "If the native opcache is enabled, settings opcache.save_comments must be enabled." [PHP requirements](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements) (last updated 2026-08-04)
- PHP 8.5 makes OPcache a fixed part of PHP: it is "statically compiled into PHP", `--disable-opcache` is removed, and `opcache.enable` still switches it on or off at runtime. [PHP RFC: Make OPcache a non-optional part of PHP](https://wiki.php.net/rfc/make_opcache_required) (status Implemented, PHP 8.5)
- Package Manager errors if OpenSSL is missing ("which is a security risk") and warns if Xdebug is loaded: "Xdebug is enabled, which may have a negative performance impact on Package Manager and any modules that use it." (`core/modules/package_manager/src/Validator/PhpExtensionsValidator.php` lines 44 to 72, C12)

#### Tools that run on the CLI
- Drush 13 needs PHP 8.3 or newer; Drush supports only a project-local Composer install. [Drush install docs](https://www.drush.org/13.x/install/) (accessed 2026-10-07)
- Core's dev metapackage pins PHPUnit ^12.5.22, drupal/coder ^9.0, PHPStan ^2.2.14 and mglaman/phpstan-drupal ^2.2.1 for Drupal 12 (`composer/Metapackage/DevDependencies/composer.json`, C12).
- `vendor/bin/dr` refuses to run outside the CLI: `if (\PHP_SAPI !== 'cli') { throw new \Exception('This script must be run from the command line.'); }` (`core/scripts/dr`, C12).
- DDEV's own telemetry shows how much CLI work developers do. Weekly invocations of built-in commands: exec 6,474,279; drush 1,995,197; mysql 1,765,631; composer 303,435; php 211,490; xdebug 114,907; npm 73,617. The page notes that exec and drush counts are inflated by scripts. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)

#### PHP started from inside a web request
- Package Manager finds Composer in this order: the project's own `composer/composer` package in vendor, then the `package_manager_composer_path` setting, then the PATH. It finds rsync from the `package_manager_rsync_path` setting or the PATH (`core/modules/package_manager/src/ExecutableFinder.php` lines 51 to 66, C12). Its help text says "Package Manager requires a Composer executable whose version satisfies `^2.7`, and PHP must have permission to run it." (`core/modules/package_manager/src/Hook/PackageManagerHooks.php` line 29, C12)
- The Drupal CMS installer runs Composer with `(new PhpExecutableFinder())->find()` plus `composer/composer/bin/composer` from vendor, through Symfony Process with a 300 second timeout. It sets COMPOSER_HOME to `<project>/.composer` when the variable is empty. [drupal_cms_installer/src/ComposerExecutor.php](https://git.drupalcode.org/project/drupal_cms/-/blob/2.x/drupal_cms_installer/src/ComposerExecutor.php) lines 47 to 85 (branch 2.x, last commit ae5fdf33, 2026-10-05)
- Symfony's PhpExecutableFinder (symfony/process v8.1.7; core requires symfony/process ^8.1 in `core/composer.json`, C12) checks, in order: the PHP_BINARY environment variable; the PHP_BINARY constant, but only when it is not empty and PHP_SAPI is cli, cli-server or phpdbg; the PHP_PATH and PHP_PEAR_PHP_BIN variables; `PHP_BINDIR/php`; then `php` on the PATH. [PhpExecutableFinder.php](https://github.com/symfony/process/blob/v8.1.7/PhpExecutableFinder.php) lines 34 to 81 (v8.1.7)
- FreeUnit's PHP module registers its SAPI under the name "cli-server" and sets `executable_location` to NULL (`src/nxt_php_sapi.c` lines 300 to 337, FU 872bf041). In Drupal core only `.ht.router.php` branches on 'cli-server' (grep of core/lib, core/modules, core/includes, index.php, C12).

#### What DDEV ships
- PHP packages in DDEV's web image for PHP 8.4: apcu bcmath bz2 cli common curl fpm gd imagick intl ldap mbstring memcached mysql opcache pgsql readline redis soap sqlite3 uploadprogress xdebug xhprof xml xmlrpc yaml zip. For PHP 8.5 the list is the same without the separate opcache package. The amd64 and arm64 lists are identical. [containers/ddev-webserver/generic-files/etc/php-packages.yaml](https://github.com/ddev/ddev/blob/8f8ac2f1598/containers/ddev-webserver/generic-files/etc/php-packages.yaml) (commit 8f8ac2f1598, 2026-09-28)
- DDEV's PHP 8.5 Xdebug ini: `xdebug.client_host=host.docker.internal`, `xdebug.discover_client_host=1`, `xdebug.client_port=9003`, `xdebug.mode=debug,develop`, `xdebug.start_with_request=yes`, `xdebug.max_nesting_level=1000`. Xdebug itself stays off until `ddev xdebug on`. [containers/ddev-webserver/ddev-php-files/etc/php/8.5/mods-available/xdebug.ini](https://github.com/ddev/ddev/blob/35793b41499/containers/ddev-webserver/ddev-php-files/etc/php/8.5/mods-available/xdebug.ini) (commit 35793b41499, 2026-07-29); [DDEV step debugging](https://docs.ddev.com/en/stable/users/debugging-profiling/step-debugging/) (accessed 2026-10-07)
- DDEV runs CLI tools in the same container as the web server, so CLI and web share one PHP build and one ini set: `ddev drush`, `ddev composer`, `ddev php` and `ddev exec` appear in its command statistics above. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)

#### What the Drupal CMS Launcher ships
- The Launcher builds PHP 8.4 with static-php-cli and these extensions: bz2, ctype, curl, dom, filter, gd, iconv, mbstring, opcache, openssl, pcntl, pdo, pdo_sqlite, phar, posix, session, simplexml, sodium, sqlite3, tokenizer, xml, xmlwriter, yaml, zip, zlib. It builds only the CLI SAPI (`--build-cli`). [.github/workflows/build-linux.yml](https://github.com/drupal/cms-launcher/blob/c10f626b2b9/.github/workflows/build-linux.yml) lines 17 to 78 (commit c10f626b2b9, 2026-02-11)
- static-php-cli's released line supports PHP 8.1 to 8.5 (README on branch main; latest release 2.8.5, 2026-04-18) and has a `--build-embed` option, "Build embed SAPI (not available on Windows)", plus `--build-all` for cli, micro, fpm, embed and frankenphp. [src/SPC/command/BuildPHPCommand.php](https://github.com/crazywhalecc/static-php-cli/blob/956fa13e7c3/src/SPC/command/BuildPHPCommand.php) lines 34 to 84 (branch main at 956fa13e7c3, 2026-08-22; file last changed in 8332ed87e0f, 2025-11-07). The unreleased v3 branch, now the default branch, lists PHP 8.6 as a pre-release

#### How FreeUnit gets its PHP
- The DrupalCI FreeUnit image builds PHP from source with the embed SAPI, then builds FreeUnit's PHP module against it: "Build FreeUnit and its PHP module against the embed SAPI built above", `./configure php --module=php --config=/usr/local/bin/php-config --lib-path=/usr/local/lib`. [php/8.5-ubuntu-unit/Dockerfile](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/-/blob/dev/php/8.5-ubuntu-unit/Dockerfile) lines 25 to 191 (branch dev, commit ed209773, 2026-10-07)
- Debian packages the embed SAPI separately: libphp8.4-embed ships `/usr/lib/libphp8.4.so` and a marker file `/usr/lib/php/8.4/sapi/embed`. [libphp8.4-embed file list, Debian trixie](https://packages.debian.org/trixie/amd64/libphp8.4-embed/filelist) (accessed 2026-10-07)

#### Contrib modules
- The redis module supports "The PhpRedis PHP extension", "The Predis PHP implementation, does not require an extension" and Relay, and works with "compatible alternatives/forks such as Valkey". Latest stable 8.x-1.11 (2025-11-09) for Drupal ^9.3, ^10 and ^11; 2.0.0-alpha2 (2026-02-10). [Redis project page](https://www.drupal.org/project/redis) (accessed 2026-10-07)
- I could not verify the PHP extension needs of Commerce, Address, Encrypt or Real AES in this pass (see Gaps).

#### Extension matrix
| Extension | Drupal core status (C12) | Contrib or tool that needs it | DDEV PHP 8.5 image | Launcher bundle | MVP for freeunit4drupal |
|---|---|---|---|---|---|
| gd | Required (composer.json, runtime check) | Image styles | yes | yes | yes |
| imagick | Not required; ImageMagick "also supported" | ImageMagick toolkit module (not verified) | yes | no | later |
| intl | Not in core list | Common contrib (not verified) | yes | no | yes (inference: cheap, DDEV parity) |
| opcache | Recommended; built into PHP 8.5 | Performance | built in (8.5), package (8.4) | yes | yes |
| pdo_mysql / mysqli | Needed for the mysql or mysqli driver | MariaDB and MySQL sites | yes (mysql package) | no | yes |
| pdo_pgsql | Needed for the pgsql driver | PostgreSQL sites | yes (pgsql package) | no | later |
| pdo_sqlite, sqlite3 | Needed by `dr install` and quick-start | SQLite sites, tests | yes (sqlite3 package) | yes | yes |
| mbstring | Named by the PHP requirements page, not in core's check | Installer text handling | yes | yes | yes |
| dom, SimpleXML, xml, xmlwriter | dom, SimpleXML, xml required | phpcs (xmlwriter, not verified) | yes (xml package) | yes | yes |
| zip | Suggested (C11, C10) for the archiver | Composer archives (not verified) | yes | yes | yes |
| apcu | Recommended, checked for size | Class loader and caches | yes | no | yes |
| redis | No | redis module with PhpRedis | yes | no | later (Predis needs no extension) |
| memcached | No | memcache module | yes | no | later |
| xdebug | No; Package Manager warns when loaded | Step debugging | yes, off until toggled | no | yes, off by default |
| sodium | No | Not verified | not a separate package; presence not verified | yes | yes (cheap) |
| bcmath | No | Not verified (Commerce is the usual claim) | yes | no | later |
| curl | "Required for automated testing" | Guzzle HTTP client | yes | yes | yes |
| uploadprogress | Not checked in C12 | Progress bars (not verified) | yes | no | later |
| openssl | Recommended; Package Manager errors without it | HTTPS requests, Composer | built in (not verified) | yes | yes |
| pcntl | No | FreeUnit howto uses pcntl_alarm() to bound jobs | not listed | yes | yes for CLI |
| xhprof | No | Profiling | yes | no | later |

### Inferences
- One PHP build must serve three roles: the CLI, the FreeUnit embed worker, and the `php` that Drupal itself spawns. If they differ, Package Manager and the Drupal CMS installer can run Composer under a PHP with other extensions or another version.
- Under FreeUnit the PHP_BINARY constant should be empty, because `executable_location` is NULL and PHP derives PHP_BINARY from it (PHP internals, not tested here). Symfony then skips it and searches PHP_PATH, PHP_BINDIR and the PATH. The simplest fix is to set the PHP_BINARY environment variable in the FreeUnit application's `environment` object to the matching CLI binary, because Symfony checks that variable first.
- On Debian-style packaging the embed SAPI and the CLI read separate ini directories (inference from the per-SAPI marker file; not verified). A native tool should write one ini fragment and load it in both.
- static-php-cli's released version can build both a static CLI and an embed library from one extension list, on Linux and macOS but not Windows. That fits a "same build for CLI and worker" rule. Whether FreeUnit can link a static libphp needs a test.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| PHP-01 | Build the CLI and the embed library from the same PHP source, version and extension list | Core extension checks run in the web process; Drush and Composer run in the CLI | One container image serves web and CLI | MVP | php-packages.yaml; SystemRequirements.php (C12) |
| PHP-02 | Load the same ini settings in the CLI and the worker (memory_limit, opcache.save_comments, apc.shm_size of at least 32M) | Status report checks for OPcache, APCu size, memory_limit | Shared `.ddev/php/*.ini` in one container (not verified in this research) | MVP | SystemRequirementsHooks.php (C12); PHP requirements page |
| PHP-03 | Ship the extension set marked "yes" in the matrix above | composer.json ext-*, runtime checks, PHP requirements page | Default package list | MVP | Matrix sources |
| PHP-04 | Set PHP_BINARY (and PATH) in the FreeUnit application environment so web code can find the matching `php` | Symfony PhpExecutableFinder used by the Drupal CMS installer | Probably not needed, because PHP-FPM and the CLI share one container (inference, not tested) | MVP | PhpExecutableFinder.php; ComposerExecutor.php |
| PHP-05 | Provide `drush`, `composer`, `php` and `dr` wrappers that select the project's PHP, without a global Drush | Drush is project-local; `dr` is CLI-only | `ddev drush`, `ddev composer`, `ddev php`, `ddev exec` | MVP | Drush docs; core/scripts/dr (C12); usage stats |
| PHP-06 | Keep Xdebug installed but disabled until the user turns it on | Package Manager warns when Xdebug is loaded | `ddev xdebug on/off/toggle` | MVP | PhpExtensionsValidator.php (C12); DDEV docs |
| PHP-07 | Add redis, memcached, imagick, pdo_pgsql, bcmath, uploadprogress and xhprof as optional extensions | Contrib modules | All in the default image | Later | php-packages.yaml |

### Gaps
- I did not verify whether DDEV's image includes sodium; the package list has no separate entry for it.
- I did not verify the extension needs of Commerce (bcmath, intl), Address, Encrypt or Real AES, or of Drupal Rector. These are common claims but I have no primary source from this pass.
- I did not run FreeUnit to confirm that PHP_BINARY is empty in its workers. The conclusion rests on the NULL `executable_location` and PHP's documented behaviour, which I did not re-read in php-src.
- The JetBrains and Stack Overflow surveys give no data on PHP extension usage.

## Settings conventions, Drupal CMS, recipes, Project Browser and Package Manager

### Takeaway
Drupal keeps environment settings in `sites/default/settings.php`, and local tools inject their own file through an include guarded by an environment variable. DDEV's `settings.ddev.php` sets the database, hash salt, `trusted_host_patterns` ['.*'], a config sync directory and mail transports, and DDEV sets IS_DDEV_PROJECT=true in the container environment (pkg/ddevapp/app_compose_template.yaml lines 66 and 241, DDEV main). Drupal CMS and its Launcher read that DDEV variable to hide a permissions warning, so "being DDEV" is already a signal inside Drupal CMS. Drupal CMS 2.x ships Project Browser and Automatic Updates, which rely on Package Manager. Package Manager is still experimental and hidden in core, and it needs a writable codebase, Composer `^2.7` that PHP may execute, rsync or direct-write mode, OpenSSL and `proc_open`.

### Cited Findings

#### settings.php conventions in core
- `sites/default/default.settings.php` (C12): `$settings['hash_salt'] = '';` (line 289); `$settings['config_sync_directory']` commented out (line 259); reverse proxy settings `reverse_proxy`, `reverse_proxy_addresses`, `reverse_proxy_trusted_headers` (lines 364 to 409); `file_public_path` (line 509), `file_private_path` (line 583) and `file_temp_path` = '/tmp' (line 596), all commented; `$settings['container_yamls'][]` for services.yml (line 714); `$settings['trusted_host_patterns'] = [];` commented (line 763); a commented include of `settings.local.php` (lines 830 to 831).
- The interactive installer writes `$databases`, `hash_salt` (`Crypt::randomBytesBase64(55)`) and, when none is set, a randomised config sync directory into settings.php with `SettingsEditor::rewrite()` (`core/lib/Drupal/Core/Installer/Form/SiteSettingsForm.php` lines 244 to 272, C12). So settings.php must be writable during installation.
- When `trusted_host_patterns` is empty, the status report shows: "The trusted_host_patterns setting is not configured in settings.php. This can lead to security vulnerabilities. It is highly recommended that ..." (`core/modules/system/src/Hook/SystemRequirementsHooks.php` lines 1294 to 1302, C12).
- `sites/example.settings.local.php` (C12) adds `sites/development.services.yml` (line 37), verbose error logging (line 45), disables CSS and JS aggregation (lines 50 to 51), sets `rebuild_access` TRUE (line 115) and `skip_permissions_hardening` TRUE (line 127).
- Core contains no reference to IS_DDEV_PROJECT (grep of C12 and C11).

#### DDEV settings management
- DDEV has two paths. When settings.php does not exist, it writes a full settings.php from its template, which ends with "// Automatically generated include for settings managed by ddev." and `if (getenv('IS_DDEV_PROJECT') == 'true' && file_exists(__DIR__ . '/settings.ddev.php')) { include __DIR__ . '/settings.ddev.php'; }` ([pkg/ddevapp/drupal/drupal12/settings.php](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/drupal/drupal12/settings.php) lines 833 to 835, last changed 9ecaba8cd5f, 2026-07-19). When settings.php exists, it appends `settingsIncludeStanza`: `$ddev_settings = __DIR__ . '/settings.ddev.php'; if (getenv('IS_DDEV_PROJECT') == 'true' && is_readable($ddev_settings)) { require $ddev_settings; }` ([drupal.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/drupal.go) lines 73 to 95). IS_DDEV_PROJECT=true is set in the web container environment ([app_compose_template.yaml](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/app_compose_template.yaml) lines 66 and 241).
- DDEV's Drupal 12 `settings.ddev.php` template sets: `$databases['default']['default']` (database, username, password, host, port, driver from the project), `$settings['hash_salt']` from a template value, `$settings['skip_permissions_hardening'] = TRUE`, `$settings['trusted_host_patterns'] = ['.*']`, `config_sync_directory` = 'sites/default/files/sync' when empty, SMTP transports to localhost:1025 for symfony_mailer, mailer_transport and symfony_mailer_lite, `system.logging` error_level 'verbose', and `aggregate_gc_threshold` 0. It sets no `file_private_path` and no `file_temp_path`. [pkg/ddevapp/drupal/drupal12/settings.ddev.php](https://github.com/ddev/ddev/blob/82ff9db725d/pkg/ddevapp/drupal/drupal12/settings.ddev.php) (commit 82ff9db725d, 2026-08-31)
- DDEV ships separate templates for backdrop and drupal6 to drupal12 under `pkg/ddevapp/drupal/` (DDEV main tree, 2026-10-06).
- `disable_settings_management` defaults to false: "When `true`, DDEV will not create or update CMS-specific settings files." [DDEV config.yaml options](https://github.com/ddev/ddev/blob/b65a22e12d7/docs/content/users/configuration/config.md) (commit b65a22e12d7, 2026-09-30)
- DDEV's default upload directory for Drupal is `sites/default/files` (`getDrupalUploadDirs()` in drupal.go lines 270 to 274).

#### The Launcher and Drupal CMS reuse the DDEV signal
- The Launcher's `settings.local.php` sets `skip_permissions_hardening` TRUE ("Make it easier for Project Browser to install things into the local site."), `package_manager_allow_direct_write` TRUE ("Allow use of the direct-write mode added to Package Manager in Drupal 11.2."), `testing_package_manager` TRUE ("Don't warn about using Package Manager, despite its being experimental."), `trusted_host_patterns[] = '^localhost$'`, `config_sync_directory` = '../config', a SQLite database, and finally `putenv('IS_DDEV_PROJECT=1');` with the comment "Suppress the warning raised by `skip_permissions_hardening`." [settings.local.php](https://github.com/drupal/cms-launcher/blob/c5c55584383/settings.local.php) (commit c5c55584383, 2026-02-18)
- The Drupal CMS installer hides the "remove write permissions" message when that variable is set: "When running in DDEV, this message serves no purpose." followed by `if (getenv('IS_DDEV_PROJECT')) { ... 'All necessary changes to %dir and %file have been made, so you should remove write permissions to them now ...' }`. [drupal_cms_installer/src/Silencer.php](https://git.drupalcode.org/project/drupal_cms/-/blob/2.x/drupal_cms_installer/src/Silencer.php) lines 32 to 35 (branch 2.x, last commit 3c962f06, 2026-08-26)

#### Drupal CMS and recipes
- On Packagist, drupal/cms 1.0.0 was released on 2025-01-14, 2.0.0 on 2026-01-27 and 2.2.2 on 2026-09-30. Version 2.2.2 requires drupal/core-recommended ^11.4 and drupal/core-recipe-unpack ^11.4. [drupal/cms on Packagist](https://packagist.org/packages/drupal/cms) (repo.packagist.org metadata, accessed 2026-10-07)
- drupal/drupal_cms_admin_ui 2.1.6 (2026-09-23) requires drupal/project_browser ^2.1.5 and drupal/automatic_updates ^4.1. [drupal/drupal_cms_admin_ui on Packagist](https://packagist.org/packages/drupal/drupal_cms_admin_ui) (accessed 2026-10-07)
- The Drupal CMS repository itself ships a `.ddev` directory (config.dev.yaml, a Selenium configuration, commands) for its contributors. [project/drupal_cms tree, branch 2.x](https://git.drupalcode.org/project/drupal_cms/-/tree/2.x) (accessed 2026-10-07)
- The download page offers: the desktop Launcher ("For the first time, you can try Drupal CMS directly on your desktop, with no additional software required."), a Composer command ending in `php -d memory_limit=256M web/core/scripts/drupal quick-start demo_umami`, the DDEV-based Local Development Guide, and hosting providers. It lists "php" and "composer" as requirements. [Download Drupal](https://new.drupal.org/download) (accessed 2026-10-07)
- `dr install` takes either an install profile or a recipe directory, for example `core/recipes/standard` (`core/lib/Drupal/Core/Command/InstallCommand.php` lines 55 to 63, C12).

#### Package Manager and Project Browser
- Package Manager's info file says `lifecycle: experimental` and `hidden: true` (`core/modules/package_manager/package_manager.info.yml`, C12 and C11).
- Its help text lists requirements: "The Drupal application's codebase must be writable in order to use Automatic Updates. This includes Drupal core, modules, themes and the Composer dependencies in the `vendor` directory."; "Package Manager requires a Composer executable whose version satisfies `^2.7`, and PHP must have permission to run it."; `composer.json` must pass `composer validate`; Composer must be configured for secure downloads (`disable-tls` false). Limits: "It does not support Drupal multi-site installations."; only supported Composer plugins; one sandbox at a time. Its FAQ covers a disabled `proc_open()`, a missing `composer` executable and rsync (`core/modules/package_manager/src/Hook/PackageManagerHooks.php` lines 28 to 73, C12).
- The help text still shows `$config['package_manager.settings']['executables']['rsync']` (line 73, C12), while the C12 code reads the `package_manager_rsync_path` setting (`ExecutableFinder.php` line 54, C12). The legacy config key is still honoured at C11 (`ExecutableFinder.php` lines 53 to 61, C11).
- Validators at C12 include WritableFileSystemValidator ("The Drupal directory "@dir" is not writable.", the project root, the vendor directory and the stage root), RsyncValidator, PhpExtensionsValidator, SymlinkValidator, MultisiteValidator, ComposerPluginsValidator, DiskSpaceValidator, PhpTufValidator and EnvironmentSupportValidator (`core/modules/package_manager/src/Validator/`, C12).
- EnvironmentSupportValidator lets a hosting environment declare Package Manager unsupported through the environment variable DRUPAL_PACKAGE_MANAGER_NOT_SUPPORTED_HELP_URL; the message is "Package Manager is not supported by your environment." (`EnvironmentSupportValidator.php` lines 36 to 51, C12)
- Direct-write mode skips the separate sandbox when the `package_manager_allow_direct_write` setting is TRUE and the operation allows it (`core/modules/package_manager/src/SandboxManagerBase.php` line 895 and `src/Attribute/AllowDirectWrite.php`, C11). The Launcher comment dates this mode to Drupal 11.2.
- Project Browser 2.1.5 (2026-09-23) supports Drupal ^11.2 || ^12. Its page says "The 'web user' will need write access to your project codebase for automatic installation to work." and "The intention is these are run in local development environments, for example DDEV." [Project Browser](https://www.drupal.org/project/project_browser) (accessed 2026-10-07)

### Inferences
- A native tool needs its own settings include, gated by its own environment variable, that sets the database, a stable hash salt, `trusted_host_patterns` for the chosen host names, a config sync directory, and private and temp paths outside the web root. DDEV's template is a good model, but its `['.*']` host pattern is too loose to copy as is.
- Drupal CMS and the Launcher treat IS_DDEV_PROJECT as "a managed local environment". freeunit4drupal should not pretend to be DDEV. A better path is to propose a neutral variable to Drupal CMS. Until then the "remove write permissions" message will show during installs, which is cosmetic.
- Project Browser and Automatic Updates are part of Drupal CMS 2.x. Making Package Manager work under FreeUnit is part of supporting Drupal CMS, not an extra. That means: the worker runs as the developer's user, Composer and rsync are on the worker's PATH (or direct-write is on), `proc_open` is allowed, OpenSSL is loaded, Xdebug is off, and the environment variable that disables Package Manager is not set.
- Composer runs started from a web request can take minutes (the Drupal CMS installer allows 300 seconds per command). FreeUnit's `limits.timeout` defaults to 0, so FreeUnit adds no timer, but PHP's own `max_execution_time` still applies to the worker.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| SET-01 | Generate `settings.freeunit.php` with `$databases`, `hash_salt`, `trusted_host_patterns`, `config_sync_directory`, `file_private_path`, `file_temp_path`, and include it from settings.php behind an environment variable | settings.php is plain PHP read on every request; the installer rewrites it | `settings.ddev.php` plus the IS_DDEV_PROJECT stanza | MVP | default.settings.php (C12); DDEV templates |
| SET-02 | Offer a switch that stops the tool from touching settings files | Developers keep settings.php in git | `disable_settings_management` | MVP | DDEV config.md |
| SET-03 | Optionally enable `settings.local.php` with development.services.yml, verbose errors and no aggregation | example.settings.local.php | Not done by default (DDEV sets verbose errors only) | Later | example.settings.local.php (C12); settings.ddev.php |
| SET-04 | Make Package Manager pass its readiness checks: writable codebase, Composer ^2.7 reachable, rsync or direct-write, OpenSSL, `proc_open`, Xdebug off | Package Manager validators | Works inside the container when the codebase is writable (Project Browser points to DDEV) | MVP | PackageManagerHooks.php, validators (C12); Project Browser page |
| SET-05 | Set PATH, PHP_BINARY and COMPOSER_HOME in the FreeUnit application environment | ExecutableFinder, Symfony PhpExecutableFinder, Drupal CMS ComposerExecutor | Container environment | MVP | ExecutableFinder.php (C12); ComposerExecutor.php |
| SET-06 | Raise `max_execution_time` for the web worker in development mode, or document the limit for Composer runs started from the UI | Package Manager and Drupal CMS installer run Composer in-request (300 s timeout) | Not verified | MVP | ComposerExecutor.php |
| SET-07 | Ask Drupal CMS for a neutral "managed local environment" signal instead of reusing IS_DDEV_PROJECT | Silencer.php checks IS_DDEV_PROJECT | DDEV sets IS_DDEV_PROJECT | Later (community work) | Silencer.php; Launcher settings.local.php |

### Gaps
- I did not research the Lando (LANDO_INFO), Upsun (PLATFORM_RELATIONSHIPS), Pantheon or Acquia settings include patterns in this pass.
- I did not check whether core parses a DATABASE_URL style variable anywhere. The only URL-style database settings I saw are SIMPLETEST_DB and DRUPAL_TEST_DB_URL for tests.
- I did not confirm which Drupal minor first shipped Package Manager's direct-write mode; the only source is the Launcher comment ("added to Package Manager in Drupal 11.2").
- I did not test Package Manager under PHP-FPM in DDEV, so the DDEV column for SET-04 rests on the Project Browser page, not a test.

## Testing needs and Drupal's CI

### Takeaway
Drupal's test types need, beyond PHPUnit on the CLI, a web server that serves the same codebase at SIMPLETEST_BASE_URL, a test database (SQLite works), a WebDriver endpoint for FunctionalJavascript tests, and Node 24 with Yarn 4 for Nightwatch. Core CI and the contrib CI templates run every web test against Apache images. A public FreeUnit trace in Drupal CI does exist: a `php-8.5-ubuntu-unit` image on the `dev` branch and registry of the DrupalCI image project since 2026-10-01. It is not used by core CI or by gitlab_templates. The same branch has carried FrankenPHP images since 2024 that never reached the production branch, so a dev-branch image is not a sign of adoption by itself.

### Cited Findings

#### PHPUnit, Nightwatch and Node
- `core/phpunit.xml.dist` (C12) declares the environment variables SIMPLETEST_BASE_URL, SIMPLETEST_DB, BROWSERTEST_OUTPUT_BASE_URL, MINK_DRIVER_CLASS, MINK_DRIVER_ARGS and MINK_DRIVER_ARGS_WEBDRIVER, all empty by default.
- `core/package.json` (C12): engines node ">= 24.0" and yarn ">= 4.1.1", packageManager "yarn@4.18.0", and a `test:nightwatch` script.
- `core/.env.example` (C12) for Nightwatch: DRUPAL_TEST_BASE_URL (example http://localhost:8888), DRUPAL_TEST_DB_URL=sqlite://localhost/sites/default/files/db.sqlite, DRUPAL_TEST_WEBDRIVER_HOSTNAME=localhost, DRUPAL_TEST_WEBDRIVER_PORT=9515, DRUPAL_TEST_CHROMEDRIVER_AUTOSTART=true, DRUPAL_NIGHTWATCH_OUTPUT=reports/nightwatch, and DRUPAL_NIGHTWATCH_IGNORE_DIRECTORIES that includes sites/simpletest.
- Drupal 12 moves to PHPUnit 12.5 (`phpunit/phpunit ^12.5.22`, C12) from 11.5 (`^11.5.50`, C11). The beta1 notes say "Using PHPUnit attributes instead of annotations is now required." [12.0.0-beta1 release notes](https://www.drupal.org/project/drupal/releases/12.0.0-beta1) (2026-09-30)

#### Core CI
- Core CI pulls `$_CONFIG_DOCKERHUB_ROOT/php-$_TARGET_PHP-apache:production` with `_CONFIG_DOCKERHUB_ROOT` = registry.gitlab.com/drupal-infrastructure/drupalci/drupalci-environments, database images `$_TARGET_DB:production`, and `selenium/standalone-chrome:133.0` (`.gitlab-ci.yml` lines 67, 120, 136 and 140, C12). It starts Apache with `sudo service apache2 start` (`.gitlab-ci/scripts/server-setup.sh` line 4, C12). JavaScript jobs use the `node:24` image (`.gitlab-ci.yml` line 917, C12).
- Core CI passes Chrome `--ignore-certificate-errors` in MINK_DRIVER_ARGS_WEBDRIVER (`.gitlab-ci.yml` line 91, C12).
- A grep of core and its CI files at C12 for "nginx unit", "freeunit", "unitd" and "unit.nginx" finds nothing (the only hits are "PhpUnit" strings).

#### Contrib CI (gitlab_templates)
- The contrib template builds the PHP image name as `$_CONFIG_DOCKERHUB_ROOT/php-$PHP_VERSION-$PHP_IMAGE_VARIANT:$PHP_IMAGE_TAG` and says "'ubuntu-apache' is the default, and 'apache' is the other possible value" for `_TARGET_PHP_IMAGE_VARIANT`. [include.drupalci.hidden-variables.yml](https://git.drupalcode.org/project/gitlab_templates/-/blob/bd20d223/includes/include.drupalci.hidden-variables.yml) lines 273 to 303; [include.drupalci.main.yml](https://git.drupalcode.org/project/gitlab_templates/-/blob/bd20d223/includes/include.drupalci.main.yml) line 560 (commit bd20d223, 2026-10-06)
- Its `.setup-webserver` step links the web root to /var/www/html, runs `chown -R www-data:www-data $CI_PROJECT_DIR` and then `service apache2 start` unconditionally. It sets SIMPLETEST_DB per database type, for example `sqlite://localhost/sites/default/files/db.sqlite`. It uses `selenium/standalone-chrome:127.0` and has a Nightwatch job that `SKIP_NIGHTWATCH` turns off. [include.drupalci.main.yml](https://git.drupalcode.org/project/gitlab_templates/-/blob/bd20d223/includes/include.drupalci.main.yml) lines 26, 286 to 299 and 395; `SKIP_NIGHTWATCH` is defined in [include.drupalci.variables.yml](https://git.drupalcode.org/project/gitlab_templates/-/blob/bd20d223/includes/include.drupalci.variables.yml) line 313 (commit bd20d223, 2026-10-06)

#### DrupalCI images and the FreeUnit trace
- The DrupalCI image project is https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments. On its `production` branch every PHP image is an Apache image: `php/8.3-ubuntu-apache`, `php/8.4-ubuntu-apache`, `php/8.5-ubuntu-apache`, `php/8.6-ubuntu-apache`, `php/8.7-ubuntu-apache` and older `-apache` images. [repository tree, branch production](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/-/tree/production/php) (accessed 2026-10-07)
- On the `dev` branch the project adds `php/8.5-ubuntu-unit` with a Dockerfile, `conf/unit/drupal.json`, `unit-start` and `unit-foreground`. The Dockerfile pins FreeUnit to commit 872bf04170756e919211799ec5574000d628d521 from github.com/freeunitorg/freeunit. `unit-start` calls itself "The equivalent of "service apache2 start" for CI jobs." Commits: 4e0adb89 "PHP: 8.5 FreeUnit 1.37.0 image (ubuntu-unit)" (2026-10-04 23:14 UTC) and ed209773 "PHP: 8.5 ubuntu-unit FreeUnit master 872bf041" (2026-10-07 05:34 UTC). [php/8.5-ubuntu-unit on branch dev](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/-/tree/dev/php/8.5-ubuntu-unit) (accessed 2026-10-07)
- The project's container registry holds `php-8.5-ubuntu-unit`, created 2026-10-01T21:57Z, with only a `dev` tag. For comparison `php-8.5-ubuntu-apache` has `dev` and `production` tags. [Container registry API, project 24322546](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/container_registry) (accessed 2026-10-07)
- The fifteen most recent commits on the `dev` branch, including both FreeUnit commits and the PHP 8.3 to 8.7 image updates, have one author: Andy Postnikov. The project has no merge requests; changes land on branches. [commits on branch dev](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/-/commits/dev) (accessed 2026-10-07). GitHub user andypost (profile name Andy Postnikov) opened FreeUnit pull requests [452](https://github.com/freeunitorg/freeunit/pull/452) and [453](https://github.com/freeunitorg/freeunit/pull/453). The same contributor maintains FreeUnit, so these commits are not independent evidence of Drupal adoption.
- The `dev` branch also holds `php/8.3-ubuntu-frankenphp` (first commit 55ac1f87, 2024-09-20), `php/8.5-ubuntu-frankenphp` (first commit 1b50976f, 2026-02-11) and `php/8.2-cli`. Their registry repositories have only `dev` tags. The issue "Support FrankenPHP as a webserver" (#3438767, opened 2024-04-04) is still open. [drupalci_environments work item 3438767](https://git.drupalcode.org/project/drupalci_environments/-/work_items/3438767) (accessed 2026-10-07)
- The image's `drupal.json` sets `max_body_size` 1073741824 (1 GiB) and `chunked_transform` true; adds MIME types for `.js`, `.mjs`, `.svg` and `.svgz`; denies dot paths except `.well-known`, the FilesMatch source extensions, `/vendor`, `sites/*/private` and `sites/*/temp`, and the Umami default content; runs `/index.php`, `/update.php`, `core/*.php` and the test front controllers (`http.php` and `https.php` through one pattern, and `test_index.php`) as PHP; serves pre-compressed brotli and gzip CSS and JS aggregates; and defines the application with user www-data, `processes` max 64, spare 5, idle_timeout 60, `"admin": {"disable_functions": "fastcgi_finish_request"}` and `assert.active` 0. [conf/unit/drupal.json](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/-/blob/dev/php/8.5-ubuntu-unit/conf/unit/drupal.json) (commit 4e0adb89, 2026-10-05)
- I found no drupal.org issue that announces or discusses this image (see Gaps).

#### DDEV test tooling
- The ddev-selenium-standalone-chrome add-on "Makes it easy to run Functional, FunctionalJavascript, Nightwatch, Behat and Drupal Test Traits tests in DDEV." It runs PHPUnit through `ddev exec -d /var/www/html/web "../vendor/bin/phpunit -c ./core/phpunit.xml.dist ..."`, runs Nightwatch with `yarn test:nightwatch`, and exposes a noVNC viewer on port 7900. [README](https://github.com/ddev/ddev-selenium-standalone-chrome) (last commit e87ba940e54, 2026-05-20)
- The ddev-drupal-contrib add-on adds `ddev phpunit`, `ddev nightwatch` (which "requires DDEV Selenium Standalone Chrome"), `ddev phpcs`, `ddev phpstan`, `ddev eslint`, `ddev stylelint`, `ddev poser` and `ddev symlink-project`. [README](https://github.com/ddev/ddev-drupal-contrib) (last commit a7219988e01, 2026-09-30)
- Monthly unique users among DDEV users with at least one add-on: ddev-selenium-standalone-chrome 1,242 and ddev-drupal-contrib 262 (data marked incomplete). [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)
- The Drupal CMS repository ships `.ddev/config.selenium-standalone-chrome.yaml` for its own tests. [project/drupal_cms tree, branch 2.x](https://git.drupalcode.org/project/drupal_cms/-/tree/2.x) (accessed 2026-10-07)

#### FreeUnit's own Drupal test kit
- FreeUnit pull request #453 (open draft, 2026-09-24) proposes "one image for Drupal core development: FreeUnit + PHP 8.5, Composer, drupal/coder, SQLite, and Xdebug (off unless `XDEBUG=1`)" with the commands `serve`, `si` (site install), `test`, `lint` and `shell`. [freeunitorg/freeunit#453](https://github.com/freeunitorg/freeunit/pull/453) (opened 2026-09-24)

### Inferences
- Functional tests install child sites under `sites/simpletest` and then fetch them over HTTP. The FreeUnit worker must therefore serve the same checkout, write to `sites/simpletest` and the browser output directory, and run as the same user as the PHPUnit process.
- FunctionalJavascript and Nightwatch need Chrome and chromedriver. A Docker-free tool can use a local Chrome and download a matching chromedriver; DDEV solves this with a Selenium container.
- The DrupalCI FreeUnit image is the most concrete link between FreeUnit and Drupal's CI found in this research. It is not evidence of independent demand: it comes from a FreeUnit contributor, and the FrankenPHP images on the same branch have stayed dev-only since 2024. If freeunit4drupal generates the same `drupal.json` that CI uses, "works locally" and "works in CI" mean the same thing. Today the image is dev-only, and `gitlab_templates` would also need a change, because it always runs `service apache2 start`.
- The CI `drupal.json` disables `fastcgi_finish_request`. That forces terminate-phase work (automated_cron, other destructable services) to finish inside the request. A local tool should make the same choice or document the difference.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| TEST-01 | Export SIMPLETEST_BASE_URL and SIMPLETEST_DB (and BROWSERTEST_OUTPUT_DIRECTORY) for the project, pointing at the FreeUnit listener and a test database | `core/phpunit.xml.dist` environment variables | Set in the add-on configuration or by hand | MVP | phpunit.xml.dist (C12); Selenium add-on README |
| TEST-02 | Serve `sites/simpletest/*` child sites from the same worker, with the worker able to write there | BrowserTestBase installs child sites over HTTP | Same container serves them | MVP | gitlab_templates `.setup-webserver` |
| TEST-03 | Provide a WebDriver endpoint (local Chrome plus chromedriver) and MINK_DRIVER_ARGS_WEBDRIVER | FunctionalJavascript tests, Nightwatch | Selenium standalone Chrome add-on | Later | phpunit.xml.dist; .env.example (C12); add-on README |
| TEST-04 | Provide Node 24 and Yarn 4 (via corepack) for core JavaScript tooling and Nightwatch | `core/package.json` engines and packageManager | `nodejs_version` (default 24), corepack for drupal11 and drupal12 types | Later | package.json (C12); drupal.go; values.go |
| TEST-05 | Reuse the DrupalCI `drupal.json` routes and limits as the local default so local and CI behave the same | CI web server config | Uses its own nginx or Apache config | MVP | drupalci-environments branch dev |
| TEST-06 | Offer `phpunit`, `phpcs` and `phpstan` wrappers that use the project PHP | core dev metapackage | ddev-drupal-contrib commands | Later | DevDependencies composer.json (C12); add-on README |

### Gaps
- I found no drupal.org issue or change record about the DrupalCI `php-8.5-ubuntu-unit` image. A reviewer searched the drupalci_environments issue queue on git.drupalcode.org through its API for "FreeUnit" and "nginx" (no results) and "unit" (two unrelated issues); I did not repeat that search. The GitLab image project has no merge requests.
- I did not verify whether gitlab_templates can run a non-Apache image if a project sets `_TARGET_PHP_IMAGE_VARIANT` to 'ubuntu-unit'. The unconditional `service apache2 start` suggests it cannot without a template change.
- I did not check which environment variables the DDEV Selenium add-on sets in its config file.

## Services beyond PHP

### Takeaway
Beyond PHP, Drupal developers mostly need a MariaDB or MySQL server, a database UI, Redis, mail capture, cron, Xdebug and Node.js, and less often Solr, Elasticsearch or OpenSearch. DDEV builds MariaDB 11.8, Mailpit, Node 24 and Xdebug into every project and leaves Redis, Solr, cron and the rest to add-ons. Its add-on telemetry ranks phpMyAdmin (9,032 users), Redis (5,976), Solr (3,003), Adminer (1,767) and cron (1,621) highest. Cron is a special case under FreeUnit: automated_cron runs after the response, and FreeUnit's Drupal howto says to run `drush cron` from a system timer instead.

### Cited Findings

#### Databases
- DDEV's default database is MariaDB 11.8; the PostgreSQL default is 14 (see the Platform section). Database type shares across DDEV projects: mariadb 63.4%, mysql 30.3%, postgres 6.3%. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)
- Weekly DDEV command use shows how often developers reach the database from the CLI: `ddev mysql` 1,765,631, `ddev psql` 83,610, `ddev import-db` 27,123. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)

#### Add-ons as a demand signal
- "The most popular DDEV add-ons in use, based on unique users in the last month, among users who have at least one add-on installed": phpmyadmin 9,032; redis 5,976; solr 3,003; adminer 1,767; ddev-cron 1,621; elasticsearch 1,444; ddev-opensearch 1,338; ddev-selenium-standalone-chrome 1,242; memcached 965; varnish 817. Further down: ddev-browsersync 441, ddev-drupal-contrib 262, ddev-xhgui 6. The page warns the Amplitude data is "significantly incomplete". [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)

#### Caches
- The redis module works with PhpRedis, Predis (no extension needed) or Relay, and with "compatible alternatives/forks such as Valkey". [Redis project page](https://www.drupal.org/project/redis) (accessed 2026-10-07)
- Core uses APCu when present (DrupalKernel.php, Settings.php, ApcuBackend.php, C12) and checks its size in the status report (see the PHP section).

#### Search
- Search API Solr 4.4.0 (2026-07-28) works with Drupal ^11.3. Solr 7.x to 9.x are supported directly, 3.6 to 6.x through a legacy submodule, and "Solr 10.x support is available now, but should be considered experimental." The page notes "There is a Solr plugin for DDEV". [Search API Solr](https://www.drupal.org/project/search_api_solr) (accessed 2026-10-07)

#### Mail
- Core's default mail configuration is `interface: default: 'php_mail'` with `mailer_dsn` scheme 'sendmail', host 'default' (`core/modules/system/config/install/system.mail.yml`, C12).
- DDEV: "Mailpit is a mail catcher that's configured to capture and display emails sent by PHP in the development environment." The web UI is at `https://mysite.ddev.site:8026` or `ddev mailpit`. "Mailpit will not intercept emails if your application is configured to use SMTP or a third-party ESP integration." For Drupal, `settings.ddev.php` points Symfony Mailer and Symfony Mailer Lite transports at it. [developer-tools.md](https://github.com/ddev/ddev/blob/15084bfa0da/docs/content/users/usage/developer-tools.md) lines 130 to 138 (commit 15084bfa0da, 2026-10-02)
- The Drupal CMS Launcher "Might not be able to send email, since it relies on whatever mail transfer program is (or isn't) on your system." [cms-launcher README](https://github.com/drupal/cms-launcher/blob/9d81aebf729/README.md) (2026-01-19)

#### Node.js
- Core needs Node ">= 24.0" and Yarn 4.18.0 through `packageManager` (`core/package.json`, C12). DDEV's default Node.js is 24 and its drupal11 and drupal12 project types enable corepack (values.go and drupal.go at DDEV main). `ddev npm` ran 73,617 times in a week. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)

#### Cron and queues
- automated_cron's default interval is 10800 seconds (`core/modules/automated_cron/config/install/automated_cron.settings.yml`, C12).
- FreeUnit's Drupal howto: "A stock Drupal install enables automated_cron. This module runs cron in the terminate phase of a random visitor's request, at most once every 10800 seconds (3 hours)." Core raises the time limit for that run to 240 seconds. "Uninstall automated_cron, or set its interval to 0, and run cron outside the request: `drush cron`. Run this from a systemd timer or from system cron. Handle queues the same way, with drush queue:run." [source/howto/drupal.rst](https://github.com/freeunitorg/docs/blob/8326bb78610/source/howto/drupal.rst) lines 292 to 312 (commit 8326bb78610, 2026-10-04)
- The same howto explains that up to FreeUnit 1.36.1 a worker running terminate-phase work after `fastcgi_finish_request()` was counted as idle and could be reaped by `idle_timeout` (default 15 seconds); FreeUnit 1.37.0 fixes this (freeunit#405). [source/howto/drupal.rst](https://github.com/freeunitorg/docs/blob/8326bb78610/source/howto/drupal.rst) lines 242 to 290 (2026-10-04). FreeUnit 1.37.0 was released on 2026-10-04. [FreeUnit releases](https://github.com/freeunitorg/freeunit/releases) (accessed 2026-10-07)
- FreeUnit pull request #452 (open, opened 2026-09-24; its GitHub milestone is 1.37.1 while its description says the feature is for 1.38.0) adds a top-level `"schedules"` object: "FreeUnit itself sends periodic internal requests to an application, like cron, with no client and no sidecar process. Example: Drupal cron." [freeunitorg/freeunit#452](https://github.com/freeunitorg/freeunit/pull/452) (opened 2026-09-24)
- DDEV handles cron with the ddev-cron add-on (1,621 monthly users, above).

#### Xdebug
- DDEV commands: `ddev xdebug` or `ddev xdebug on`, `ddev xdebug off`, `ddev xdebug toggle`, `ddev xdebug status`. "Xdebug is disabled by default for performance reasons". PhpStorm maps the project to `/var/www/html`; VS Code uses `pathMappings` in `.vscode/launch.json`. WSL2 needs extra steps, such as `xdebug_ide_location`, an IPv4 option for PhpStorm, and `ddev utility xdebug-diagnose --interactive`. [DDEV step debugging](https://docs.ddev.com/en/stable/users/debugging-profiling/step-debugging/) (accessed 2026-10-07)
- DDEV's PHP 8.5 Xdebug ini sets client_host host.docker.internal, discover_client_host 1, port 9003, mode debug,develop and start_with_request yes (see the PHP section). `ddev xdebug` ran 114,907 times in a week (usage statistics).
- Xdebug's compatibility table lists PHP 8.5 under Xdebug 3.6 and 3.5 only, and PHP 8.4 under 3.6, 3.5 and 3.4. [Xdebug compatibility](https://xdebug.org/docs/compat) (accessed 2026-10-07). Xdebug 3.5.0 (2025-12-04) added "PHP 8.5 support" and "Native Xdebug Path Mapping"; 3.5.3 followed on 2026-06-08 and 3.6.0alpha1 on 2026-09-08. [Xdebug updates](https://xdebug.org/updates) (accessed 2026-10-07)
- In the JetBrains State of PHP 2025 (1,720 respondents whose main language is PHP), 39% use a debugger such as Xdebug and 59% use var_dump-style debugging. [The State of PHP 2025](https://blog.jetbrains.com/phpstorm/2025/10/state-of-php-2025/) (published 2025-10-15 per [Laravel News](https://laravel-news.com/jetbrains-state-of-php-2025))

#### Profiling
- XHProf in DDEV: "In DDEV v1.24.4+ you can switch to the XHGui profiling mode (permanently) with: `ddev config global --xhprof-mode=xhgui && ddev restart`", then `ddev xhgui on` and `ddev xhgui launch`. [xhprof-profiling.md](https://github.com/ddev/ddev/blob/639d80bf38a/docs/content/users/debugging-profiling/xhprof-profiling.md) lines 5 to 25 (commit 639d80bf38a, 2025-08-18). The web image ships the xhprof package and an `xhprof_prepend.php` for the default prepend mode (php-packages.yaml; `containers/ddev-webserver/ddev-webserver-base-files/usr/local/bin/xhprof/xhprof_prepend.php` at DDEV main).
- Blackfire in DDEV: credentials go into the global web environment, then `ddev blackfire on`, `ddev blackfire off` and `ddev blackfire status`. [blackfire-profiling.md](https://github.com/ddev/ddev/blob/6a3cc8b1273/docs/content/users/debugging-profiling/blackfire-profiling.md) lines 10 to 30 (commit 6a3cc8b1273, 2025-12-02)
- Tideways: DDEV core has no Tideways integration (no match in the DDEV main tree); a third-party add-on exists, [OpenForgeProject/ddev-tideways](https://github.com/OpenForgeProject/ddev-tideways) (last push 2026-08-31).

### Inferences
- A Docker-free MVP must manage, or reliably detect, a MariaDB or MySQL server. That is the service almost every DDEV Drupal project uses. A bundled database UI (phpMyAdmin or Adminer) is the most installed add-on and is cheap to offer as a PHP app on the same FreeUnit.
- Mail capture is expected: DDEV builds it in and the Launcher's lack of it is a stated limitation. A single Mailpit binary plus `sendmail_path` and SMTP transport settings covers it without Docker.
- Cron should not depend on visitor traffic under FreeUnit. For the MVP, a user-level systemd timer or launchd job that runs `drush cron` works on Linux and macOS. FreeUnit's proposed `schedules` would remove that external piece once it ships.
- On a native setup Xdebug needs no path mappings, because the server and the IDE see the same paths. That removes the most documented DDEV Xdebug friction (mappings and WSL2 networking).
- Redis, Solr, Elasticsearch and OpenSearch can wait. Each is a separate server that a user can install on the host, and none is required by Drupal core.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| SVC-01 | Provide or detect a MariaDB (or MySQL) server per project, with import, export and a CLI client | mysql and mysqli drivers, `$databases` | db container, `ddev mysql`, `ddev import-db` | MVP | ddevapp.go; usage stats |
| SVC-02 | Capture all outgoing mail and show it in a web UI | `system.mail` php_mail and `mailer_dsn`; Symfony Mailer contrib | Built-in Mailpit; settings.ddev.php SMTP overrides | MVP | system.mail.yml (C12); developer-tools.md |
| SVC-03 | Run Drupal cron and queues on a timer, not in visitor requests | automated_cron (10800 s), `drush cron`, `drush queue:run` | ddev-cron add-on | MVP | automated_cron.settings.yml (C12); FreeUnit howto |
| SVC-04 | Xdebug on demand, with `start_with_request` and port 9003, no path mapping needed | Standard Xdebug 3.5+ on PHP 8.5 | `ddev xdebug on`; host.docker.internal; /var/www/html mapping | MVP | DDEV ini; Xdebug compat |
| SVC-05 | Offer a database UI (Adminer or phpMyAdmin) | Not a Drupal mechanism | phpmyadmin and adminer add-ons | Later (cheap) | usage stats |
| SVC-06 | Node.js 24 and corepack for themes and core tooling | core/package.json; theme build steps | `nodejs_version`, `ddev npm`, corepack | Later | package.json (C12); values.go |
| SVC-07 | Redis or Valkey on request, with settings for the redis module | redis module cache backend | ddev-redis add-on | Later | Redis project page; usage stats |
| SVC-08 | Solr, Elasticsearch or OpenSearch on request | Search API backends | ddev-solr, ddev-elasticsearch, ddev-opensearch add-ons | Later | Search API Solr page; usage stats |
| SVC-09 | Profiling (XHProf or SPX) on request | None in core | `ddev xhprof`, Blackfire integration | Later | DDEV docs tree; php-packages.yaml |

### Gaps
- I did not verify how Xdebug behaves in long-lived FreeUnit workers (for example whether `start_with_request=trigger` works the same as under PHP-FPM).
- I did not read the ddev-cron or Solr add-ons in detail, and I did not test the third-party Tideways add-on.
- The Elasticsearch and OpenSearch connector modules were not checked.

## File ownership and permissions

### Takeaway
Drupal needs the web server user to write `sites/default/files`, `sites/simpletest` and, for Package Manager and Project Browser, the whole codebase. It also wants `settings.php` read-only after installation unless `skip_permissions_hardening` is set, which DDEV and the Launcher both set. DDEV avoids ownership problems by building a per-project image whose user has the host user's uid and gid, and it fights slow file sharing on macOS and Windows with Mutagen. A native tool can skip both problems by running the FreeUnit main process and the PHP workers as the developer's own user; FreeUnit supports running unprivileged, but then it cannot switch workers to another user.

### Cited Findings
- "Drupal and PHP should have read and write access to the /sites/default/files directory." "Universal permission like 0777 is not secure and usage of such is discouraged." [Web server requirements](https://www.drupal.org/docs/getting-started/system-requirements/web-server-requirements) (last updated 2026-09-13)
- The installer needs write access to `sites/default` to create settings.php and then tells the user to remove it (`core/INSTALL.txt` lines 150 to 165, C12).
- The status report's "Configuration files" check verifies settings files with `FILE_NOT_WRITABLE` and only tries to fix permissions when `skip_permissions_hardening` is not set (`core/modules/system/src/Hook/SystemRequirementsHooks.php` lines 597 to 645, C12).
- `skip_permissions_hardening` is TRUE in `sites/example.settings.local.php` (line 127, C12), in DDEV's `settings.ddev.php` (commit 82ff9db725d) and in the Launcher's `settings.local.php` (commit c5c55584383).
- Package Manager needs Drupal core, modules, themes and `vendor` writable (`PackageManagerHooks.php` line 28, C12), and Project Browser says "The 'web user' will need write access to your project codebase for automatic installation to work." [Project Browser](https://www.drupal.org/project/project_browser) (accessed 2026-10-07)
- Drupal writes an `.htaccess` into public files with `SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006` and `php_flag engine off`, and into private files with `Require all denied` (`core/lib/Drupal/Component/FileSecurity/FileSecurity.php` lines 68 to 96, C12). These files only protect Apache sites.
- DDEV builds each project's web image with a user that matches the host: `ARG uid`, `ARG gid`, then `groupadd --gid "$gid" "$username"` and `useradd ... --uid "$uid" "$username"`, with values from `dockerutil.GetContainerUser()`. [pkg/ddevapp/config.go](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/config.go) lines 957 and 1356 to 1369 (last changed daedbf42af4, 2026-09-29)
- "Mutagen is enabled by default on Mac and Traditional Windows." [DDEV config.yaml options, performance_mode](https://github.com/ddev/ddev/blob/b65a22e12d7/docs/content/users/configuration/config.md) (2026-09-30). DDEV explains why: "Docker bind-mounts ... check every file access against the file on the host. Docker's way of doing these checks macOS and Windows is not very performant." [DDEV performance docs](https://docs.ddev.com/en/stable/users/install/performance/) (accessed 2026-10-07)
- Mutagen caveats from the same page: `upload_dirs` are excluded from sync; the first sync takes up to 30 seconds; "If you change files ... while DDEV is stopped, Mutagen has no way to know about it" and needs `ddev mutagen reset`; large `node_modules` directories slow the sync; on Windows symlinks "must be strictly limited to relative links". Mutagen also stores the code twice, on the host and in a Docker volume. [DDEV performance docs](https://docs.ddev.com/en/stable/users/install/performance/) (accessed 2026-10-07); [performance.md line 69](https://github.com/ddev/ddev/blob/598e255e233/docs/content/users/install/performance.md)
- "WSL2 has intolerably slow access to the Traditional Windows NTFS/CIFS filesystem, but the WSL2 filesystem is blazingly fast. It's really important to move your project to the WSL2 native filesystem." [DDEV FAQ](https://github.com/ddev/ddev/blob/3af9704770e/docs/content/users/usage/faq.md) line 309 (commit 3af9704770e, 2026-08-31)
- DDEV added "Docker rootless functionality ... for Linux environments" in 2025. [DDEV 2025 Year in Review](https://ddev.com/blog/2025-review/) (2026-01-15)
- FreeUnit logs "Unit is running unprivileged, then it cannot use arbitrary user and group." when it lacks the capability to change user IDs (`src/nxt_runtime.c` lines 938 to 948, FU 872bf041). Its security howto describes the main process as one that "Runs as a daemon, spawning Unit's non-privileged and app processes; requires numerous system capabilities and privileges for operation." [source/howto/security.rst](https://github.com/freeunitorg/docs/blob/695e8ead2a5/source/howto/security.rst) line 620 (commit 695e8ead2a5, 2026-09-27)
- The archived NGINX Unit Drupal howto tells users to run `chown -R unit:unit /path/to/app/` so Unit can access the code. [Drupal on NGINX Unit](https://unit.nginx.org/howto/drupal/) (accessed 2026-10-07; the page says NGINX Unit is archived as of October 2025)
- Contrib CI runs `chown -R www-data:www-data $CI_PROJECT_DIR` before starting Apache, and the DrupalCI FreeUnit image runs the application as www-data (gitlab_templates `include.drupalci.main.yml` lines 292 to 299; drupal.json on branch dev).
- FreeUnit's Drupal howto warns that on a case-insensitive file system its deny list "misses /core/composer.JSON, /WEB.CONFIG, an uppercase .YML and the rest" and that "The dependable fix is to keep the document root on a case-sensitive filesystem". [source/howto/drupal.rst](https://github.com/freeunitorg/docs/blob/8326bb78610/source/howto/drupal.rst) lines 181 to 192 (2026-10-04)

### Inferences
- Running FreeUnit unprivileged as the developer means files written by PHP belong to the developer, Package Manager can write the codebase, and no `chown` or uid mapping is needed. The cost on Linux is that an unprivileged process cannot bind ports below 1024 without CAP_NET_BIND_SERVICE (Linux capabilities, not re-read here), so the tool should use high ports or a separate privileged port forwarder. A reviewer reports that macOS has allowed unprivileged binds below 1024 since 10.14 (Apple Developer Forums thread 674179); I could not open that page.
- A native tool has no bind-mount layer, so it avoids Mutagen and its sync caveats on macOS. On Windows it must run inside WSL2 (FreeUnit does not run on Windows; see the market section), and the project must live in the WSL2 file system for the same reason DDEV gives.
- macOS and Windows often use case-insensitive file systems (not verified in this research). Under FreeUnit the deny rules are matched by URI, not by file, so case-insensitive matching (regex with `(?i)`) is needed on those hosts. The DrupalCI `drupal.json` uses regular expressions for most of its deny rules, which makes it the better starting point, but none of them uses `(?i)` and four rules are plain wildcards (`/vendor`, `/vendor/*`, the Umami default content path, `*.svgz`), so it still needs case-insensitive rewrites.
- Drupal's `.htaccess` files in `sites/default/files` and the private directory have no effect under FreeUnit. The route configuration must deny private and temp paths and must never execute PHP below `sites/`.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| PERM-01 | Run unitd and the PHP workers as the developer's own user, so all files written by PHP belong to the developer | Public files, simpletest, Package Manager and Project Browser need write access | Per-project image user with the host uid and gid | MVP | config.go lines 1356 to 1369; nxt_runtime.c |
| PERM-02 | Set `skip_permissions_hardening` in development mode and say so in the generated settings | Status report "Configuration files" check | settings.ddev.php sets it TRUE | MVP | SystemRequirementsHooks.php (C12); settings.ddev.php |
| PERM-03 | Listen on high ports by default; add an optional privileged helper for ports 80 and 443 | Not a Drupal mechanism | ddev-router (Traefik) container in front of the web container | MVP (high ports), later (helper) | nxt_runtime.c; traefik-router.md |
| PERM-04 | Use case-insensitive deny patterns on macOS and Windows hosts | Apache `FilesMatch` is the reference | nginx config inside a Linux container (case-sensitive file system) | MVP | FreeUnit howto lines 181 to 192 |
| PERM-05 | Deny web access to private and temp directories and to PHP files below `sites/` in the route config | FileSecurity .htaccess files (Apache only) | nginx config template (not checked here) | MVP | FileSecurity.php (C12); DrupalCI drupal.json |
| PERM-06 | On Windows, require WSL2 and warn when the project is under `/mnt/c` | File system performance | Same warning in the DDEV FAQ | MVP | DDEV faq.md line 309 |

### Gaps
- I did not test unitd unprivileged on macOS, and I could not open the Apple forum thread about unprivileged binds below port 1024.
- I did not verify the default case sensitivity of macOS APFS or of WSL2 mounts from a primary source.
- I did not read DDEV's nginx Drupal template to see how it handles private files.

## Hostnames and TLS

### Takeaway
Drupal derives its base URL from the request and only needs `trusted_host_patterns` to match the host. `*.localhost` names are attractive for a native tool: browsers treat them as secure contexts even over plain HTTP, curl resolves them to loopback since 7.85.0, and Drupal sets a proper cookie domain for them. DDEV instead uses `*.ddev.site` public DNS plus a mkcert local CA behind a Traefik router, and documents the failure cases (DNS rebinding filters, Firefox trust stores). HTTPS still matters for Secure and SSESS cookies, OAuth providers on non-localhost names, and parity with production.

### Cited Findings
- Drupal names the session cookie with prefix SSESS on HTTPS and SESS on HTTP, and sets `cookie_secure` from `$request->isSecure()` (`core/lib/Drupal/Core/Session/SessionConfiguration.php` lines 51 to 79, C12).
- Drupal sets the cookie domain to "." plus the host only when the result has more than two dot-separated parts and is not an IP address: "Cookies for domains without an embedded dot will be rejected by user agents in order to defeat malicious websites attempting to set cookies for top-level domains. Also IP addresses may not be used in the domain attribute" (`SessionConfiguration.php` lines 128 to 146, C12). So `localhost` and `127.0.0.1` get a host-only cookie, and `mysite.localhost` gets `.mysite.localhost`.
- `trusted_host_patterns` is commented out in default.settings.php (line 763, C12) and the status report flags it when empty (see the Settings section). DDEV sets it to `['.*']`; the Launcher sets `'^localhost$'`.
- Reverse proxy support is opt-in: `reverse_proxy`, `reverse_proxy_addresses` and `reverse_proxy_trusted_headers` in settings.php (default.settings.php lines 364 to 409, C12). The Drupal `.htaccess` also sets a `protossl` variable from `%{HTTPS}` and passes `Authorization` through to PHP (`core/assets/scaffold/files/htaccess` lines 66 to 75, C12).
- `dr server` serves `http://127.0.0.1:<port>` (ServerCommand.php, C12). The Launcher's PHP server "does _not_ support HTTPS" (README, 2026-01-19).
- An origin is potentially trustworthy (a secure context) when its host is in 127.0.0.0/8 or ::1/128, is "localhost" or "localhost.", or ends with ".localhost" or ".localhost.". Service workers, among other features, need a secure context. [MDN: Secure contexts](https://developer.mozilla.org/en-US/docs/Web/Security/Defenses/Secure_Contexts) (last modified 2026-09-14)
- curl hard-codes `localhost` to 127.0.0.1 and ::1 since 7.78.0. [curl localhost as a local host](https://daniel.haxx.se/blog/2021/05/31/) (2021-05-31). curl issue #9192, "curl does not automatically resolve `*.localhost` like Chrome does", was closed on 2022-08-11 by commit b5c0fe20 ("hostip: resolve *.localhost to 127.0.0.1/::1", PR 9296), which first shipped in curl 7.85.0 (2022-08-31). [curl/curl#9192](https://github.com/curl/curl/issues/9192) and [curl 7.85.0 release](https://github.com/curl/curl/releases/tag/curl-7_85_0) (accessed 2026-10-07)
- Google's OAuth rules: "Redirect URIs must use the HTTPS scheme, not plain HTTP. Localhost URIs (including localhost IP address URIs) are exempt from this rule." and "Hosts cannot be raw IP addresses. Localhost IP addresses are exempted from this rule." [Using OAuth 2.0 for Web Server Applications](https://developers.google.com/identity/protocols/oauth2/web-server) (accessed 2026-10-07)
- DDEV: "The normal use of DDEV involves project URLs (and hostnames) like `*.ddev.site`". "There are a few DNS servers, mostly local Fritz!Box routers, which do not allow a DNS lookup to result in `127.0.0.1`"; then DDEV asks for `sudo` to add the name to the hosts file. [DDEV networking](https://docs.ddev.com/en/stable/users/usage/networking/) (accessed 2026-10-07)
- DDEV options: `project_tld` defaults to `ddev.site`; `use_dns_when_possible` defaults to true, and when false "DDEV will always update the `/etc/hosts` file". [config.md](https://github.com/ddev/ddev/blob/b65a22e12d7/docs/content/users/configuration/config.md) (2026-09-30)
- DDEV's install steps run `mkcert -install`. Its troubleshooting page lists "mkcert CA not installed in OS trust store", "Firefox on Windows does not use the Windows certificate store", and Linux Firefox needing `certutil` from libnss3-tools. [ddev-installation.md](https://github.com/ddev/ddev/blob/598e255e233/docs/content/users/install/ddev-installation.md) lines 18 to 90; [troubleshooting.md](https://github.com/ddev/ddev/blob/18ecfec928e/docs/content/users/usage/troubleshooting.md) lines 329 to 344 (commit 18ecfec928e, 2026-09-23)
- "DDEV uses the well-known open-source Traefik for its router container." [traefik-router.md](https://github.com/ddev/ddev/blob/598e255e233/docs/content/users/extend/traefik-router.md) line 3 (DDEV main)
- Core CI runs Chrome with `--ignore-certificate-errors` (`.gitlab-ci.yml` line 91, C12).
- DDEV sets `DRUSH_OPTIONS_URI=$DDEV_PRIMARY_URL` in the web container, so Drush builds links with the project URL. [app_compose_template.yaml](https://github.com/ddev/ddev/blob/598e255e233/pkg/ddevapp/app_compose_template.yaml) line 227 (last changed 61de59579ff, 2026-08-24)
- FreeUnit TLS: certificate bundles are uploaded with `curl -X PUT --data-binary @bundle.pem --unix-socket ... http://localhost/certificates/<name>` and named in the listener's `tls` object; the page links a certbot howto and a section on automatic renewal with an ACME client. [source/certificates.rst](https://github.com/freeunitorg/docs/blob/bf6f370269c/source/certificates.rst) lines 13 to 49 (commit bf6f370269c, 2026-09-27)
- FreeUnit listeners accept a `forwarded` object with `client_ip` and `protocol` options for headers such as X-Forwarded-For. [source/configuration/index.rst](https://github.com/freeunitorg/docs/blob/0c64361efa4/source/configuration/index.rst) lines 465 to 634 (commit 0c64361efa4, 2026-10-04)

### Inferences
- `<project>.localhost` on a high port is a good MVP default: no DNS, no hosts file, no sudo, a secure context in Chrome and Firefox, correct Drupal cookies, and curl 7.85+ (and so Guzzle with libcurl) resolves it. Two risks need checks: Safari (third-party reports say it does not resolve `*.localhost`; see Gaps) and PHP code that resolves names through the system resolver rather than libcurl.
- HTTPS can come after the MVP, but not much later. Production parity (SSESS cookies, `Secure` flags), OAuth providers that only accept HTTPS on non-localhost names, and service workers on non-localhost names all need it. FreeUnit's `/certificates` API plus a mkcert-style local CA covers it without Docker.
- A native tool with one FreeUnit instance per machine needs a per-project host routing scheme (one listener with `match: host` routes, or one port per project). DDEV uses Traefik for the same job.
- Generated `trusted_host_patterns` should list the exact local names, not `.*`.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| HOST-01 | Serve each project at `<name>.localhost:<port>` with a FreeUnit route that matches the host | Base URL from the request; cookie domain logic | `*.ddev.site` public wildcard DNS, hosts file fallback | MVP | SessionConfiguration.php (C12); MDN; DDEV networking |
| HOST-02 | Write exact `trusted_host_patterns` for the chosen names | Trusted host check and status report | `['.*']` | MVP | settings.ddev.php; SystemRequirementsHooks.php (C12) |
| HOST-03 | Offer HTTPS with a locally trusted CA and FreeUnit certificate bundles | `isSecure()` drives SSESS cookies and `cookie_secure` | mkcert CA plus Traefik router | Later (soon after MVP) | SessionConfiguration.php; certificates.rst; DDEV docs |
| HOST-04 | Set `DRUSH_OPTIONS_URI` (or Drush site aliases) so CLI-generated links use the project URL | Drush needs a URI for links built outside a request (not verified in this research) | `DRUSH_OPTIONS_URI=$DDEV_PRIMARY_URL` in the web container | MVP | app_compose_template.yaml line 227 |
| HOST-05 | If a proxy sits in front, configure FreeUnit `forwarded` and Drupal `reverse_proxy` settings together | Drupal reverse proxy settings | Traefik in front of nginx inside the stack | Later | default.settings.php (C12); FreeUnit configuration docs |
| HOST-06 | Document OAuth limits: `localhost` redirect URIs work over HTTP at Google; other names need HTTPS | Contrib OAuth modules | HTTPS on `*.ddev.site` | Later | Google OAuth docs |

### Gaps
- Safari's handling of `*.localhost` is reported as broken in third-party sources ([Caddy community thread](https://caddy.community/t/localhost-tld-in-safari-not-found/33815), [WebKit bug 160504](https://www2.webkit.org/show_bug.cgi?id=160504)), but I did not verify the current Safari behaviour.
- I did not confirm whether Google accepts `mysite.localhost` (as opposed to `localhost`) as a "localhost URI".
- I did not verify what Drush does without a URI in this pass.
- I could not fetch the systemd nss-myhostname manual (HTTP 403 and 418), so I did not verify system resolver behaviour for `*.localhost` on Linux, macOS or Windows.

## Adoption: drupal.org documentation, Drupal CMS, events, Slack and hosting companies

### Takeaway
DDEV became Drupal's recommended local tool through a six-year community process: a DrupalCon Nashville 2018 BoF, an ideas issue opened in April 2018, and approval in June 2024. drupal.org now says "DDEV is the recommended local development tool for Drupal" and asks anyone who wants a different tool listed to open an issue in the Official Docs queue. The Drupal Association's own Drupal CMS Launcher is the only non-Docker path drupal.org promotes, and it targets evaluators and site builders, not developers. Hosting companies either point to DDEV (Acquia quickstart, Upsun, Lagoon) or decline to support any local tool (Pantheon). DrupalCon (Chicago and Rotterdam in 2026, Orlando and Edinburgh in 2027) is where DDEV and the Launcher were launched and promoted.

### Cited Findings

#### drupal.org local development documentation
- The "Local server setup" guide (a community-editable Drupal Wiki guide at https://www.drupal.org/docs/develop/local-server-setup, also https://www.drupal.org/node/157602, which `core/INSTALL.txt` links): "DDEV is the recommended local development tool for Drupal." It lists DDEV's features ("Easy project setup, Consistent, Docker-based environments, Built-in Composer, HTTPS and mail support, Drush, Mailpit and Xdebug integration") and says "While DDEV is recommended, Drupal is supported by other Docker-based tools." Child pages: Docker-based development environments for macOS, Linux, and Windows; Windows development environment; Installing Drupal on Windows for local usage; Installing Drupal with DDEV in WSL2 on Windows; Running on a USB Drive with USBWebserver; Using XAMPP; Managing mail on a local server; Enable verbose error logging. The fetch tool listed eojthebrave, hansfn, lolandese and clemens.tolboom as guide maintainers. [Local server setup](https://www.drupal.org/docs/develop/local-server-setup) (accessed 2026-10-07; no last-updated date was returned)
- The official, curated "Local Development Guide": "The Drupal community recommends using DDEV, a free, cross-platform local development solution." and "To suggest a different local development solution, please create an issue in the Official Docs issue queue." [Local Development Guide](https://www.drupal.org/docs/official_docs/local-development-guide) (accessed 2026-10-07)
- The guide's source lives in git as `source/en/guide_local_development.asciidoc` in https://git.drupalcode.org/project/official_docs (default branch 9.0.x). It was created on 2019-04-11 ("Creating Local Development Guide.", commit 4c5b48ee), and that first version already presented DDEV ("DDEV is a Docker-based tool that provides a preconfigured local development environment for Drupal"). It was last changed on 2023-03-21 ("Issue #3348987 by eojthebrave, pameeela: Clarify setup requirements around PHP v...", [official_docs issue 3348987](https://git.drupalcode.org/project/official_docs/-/work_items/3348987)). The git text still uses older wording and a `drupal10` project type. [official_docs commits for the file](https://git.drupalcode.org/project/official_docs/-/commits/9.0.x/source/en/guide_local_development.asciidoc) (accessed 2026-10-07)
- "The Drupal community selected DDEV as the recommended environment for local site building as well as code development in June 2024." The same page offers "You can also try Drupal CMS directly on your desktop, with no additional software required." [Install Drupal with DDEV for local site building and development](https://www.drupal.org/docs/getting-started/installing-drupal/install-drupal-with-ddev-for-local-site-building-and-development) (last updated 2026-07-13)

#### How DDEV became the recommendation
- Ideas issue #2965681, "Document using DDEV as the recommended Drupal local development environment", was created on 2018-04-24 and closed on 2024-06-28 with the label approvedPlan. It started from a DrupalCon Nashville 2018 BoF, "An official Drupal local develop environment?", "attended by over 30 people including representatives of the most popular local development environment projects for Drupal, Docksal, DDEV, Lando, and DrupalVM". Its proposed resolution: "Recommend DDEV as the solution to setup a local environment", "update the documentation to remove all mentions of outdated methods such as xampp and local setup", update the evaluator guide, the download page and the Drupal Quick Start command. Outcome: "The proposal has been approved. DDEV is now the officially recommended solution." [project/ideas #2965681](https://git.drupalcode.org/project/ideas/-/work_items/2965681) (closed 2024-06-28)
- Community advocacy before the decision: Kevin Quillen (Velir) wrote "DDEV being considered as the "official" Drupal development environment" on 2023-10-29, as summarised by [The Drop Times](https://www.thedroptimes.com/35228/ddev-gains-ground-preferred-local-development-environment-drupal-community) (accessed 2026-10-07).
- DDEV's own account in May 2024: "Many of you know that DDEV was chosen as Drupal's recommended local development solution in the last year." and "The annual Drupal Developer Survey was released in a Birds-of-a-feature (BoF) session" at DrupalCon Portland 2024. [DrupalCon Portland 2024 Wrapup](https://ddev.com/blog/drupalcon-portland-wrapup/) (2024-05-14)
- DDEV is funded by sponsorship. Its 2023 review reports DDEV Foundation income of USD 47,729 and says "Randy receives no income from the DDEV Foundation; his salary is paid by Platform.sh". [DDEV Project 2023 Review](https://ddev.com/blog/2023-review/) (2024-02-29). The DDEV site showed 84.5% of a USD 12,000 monthly sponsorship goal on 2026-10-07. [ddev.com](https://ddev.com/usage-stats/) (accessed 2026-10-07)

#### Drupal CMS and the Launcher
- Drupal CMS 1.0.0 shipped on 2025-01-14 and 2.0.0 on 2026-01-27; 2.2.2 is current (2026-09-30). [drupal/cms on Packagist](https://packagist.org/packages/drupal/cms) (accessed 2026-10-07)
- The desktop app was presented in the Driesnote at DrupalCon Atlanta 2025 by Gábor Hojtsy, Drupal CMS Contribution Coordinator, for macOS and Windows; it "complements the existing trial experience that lets users explore Drupal CMS without installation". [The Drop Times: Drupal CMS Desktop App Announced at DrupalCon Atlanta 2025](https://www.thedroptimes.com/47573/drupal-cms-desktop-app-announced-at-drupalcon-atlanta-2025) (2025-03-26)
- The Launcher repository was created on 2025-02-04, has 46 stars, and its newest release is v1.0.9 (2026-03-23). Release assets have been downloaded 21,399 times in total, 9,927 of them for v1.0.9. The last push was on 2026-04-30. [drupal/cms-launcher](https://github.com/drupal/cms-launcher) (GitHub API, accessed 2026-10-07)
- The README: "This launcher is designed to help you test out and build a site with Drupal CMS. It's ideal for site builders, themers, and evaluators"; "It is not intended for contributing to or developing Drupal CMS itself"; "This launcher is maintained by the Drupal Association and community contributors". Its alternatives list says "DDEV is Drupal's Docker-based local development platform of choice", names Lando and Docksal, and adds "If you'd rather avoid Docker, Laravel Herd is a fine choice", plus MAMP, XAMPP and Laravel Valet. [README](https://github.com/drupal/cms-launcher/blob/9d81aebf729/README.md) (2026-01-19)
- The download page leads with the Launcher, then Composer with `quick-start`, then the DDEV guide. [Download Drupal](https://new.drupal.org/download) (accessed 2026-10-07)

#### DrupalCon, camps and Slack
- DrupalCon Chicago 2026 ran from March 23 to 26, 2026 at the Hilton Chicago; its page shows "1300+ Attendees". [events.drupal.org/chicago2026](https://events.drupal.org/chicago2026) (accessed 2026-10-07)
- DrupalCon Rotterdam 2026 ran from September 28 to October 1, 2026; its tracks include "Development, AI & Agentic Architecture". [events.drupal.org/rotterdam2026](https://events.drupal.org/rotterdam2026) (accessed 2026-10-07)
- Upcoming: DrupalCon Orlando, 22 to 25 March 2027, and DrupalCon Edinburgh, 25 to 28 October 2027. [events.drupal.org](https://events.drupal.org/) (accessed 2026-10-07)
- Drupal Slack: "Visit https://www.drupal.org/join-slack to join." The workspace has "over 800 channels"; the page lists #support and #contribute among others. [Slack](https://www.drupal.org/slack) (last updated 2025-12-30)
- DDEV's support page lists Discord and the GitHub issue queue; it does not mention Drupal Slack. [DDEV support](https://docs.ddev.com/en/stable/users/support/) (accessed 2026-10-07)

#### Hosting companies
- Pantheon: "Pantheon does not offer support for local development solutions or troubleshooting." It lists Lando and MAMP, WAMP or XAMPP as known options and says "Pantheon's Localdev has been deprecated." [Pantheon local development](https://docs.pantheon.io/guides/local-development) (last reviewed 2022-03-10)
- Acquia's developer portal offers a quickstart built on "DDEV plus acli" and a guide on "where Lando, plain acli, and Cloud IDE fit". [Acquia: Set up local development](https://dev.acquia.com/cloud-platform/local-dev/) (accessed 2026-10-07).
- Upsun (formerly Platform.sh): "DDEV is an open-source tool for local development environments." and "Please see the Upsun Integration docs on DDEV Docs." [Upsun: DDEV](https://developer.upsun.com/docs/development/local/ddev) (accessed 2026-10-07). DDEV added a ddev-upsun add-on for "fixed and flex projects" in 2025. [DDEV 2025 Year in Review](https://ddev.com/blog/2025-review/) (2026-01-15)
- amazee.io Lagoon documents three options for Drupal: Pygmy, Lando and DDEV. DDEV suits those who prefer "ease of use and a large community over production similarity". [Lagoon local development environments](https://docs.lagoon.sh/using-lagoon-the-basics/local-development-environments/) (accessed 2026-10-07)
- DDEV's bundled `ddev pull` integrations show where DDEV users host: pantheon 51.7% (9,053), acquia 20.9% (3,668), platform 15.2% (2,663), upsun 11.3% (1,975), lagoon 0.9% (157). The page notes that many other pull integrations are custom or add-on providers and are not counted. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)

### Inferences
- The decision on "recommended" status is made through the Drupal community process (ideas queue plus documentation owners), not the Drupal Association alone. DDEV needed six years, an established user base and a BoF with competing tools present. freeunit4drupal should aim first for a listing among alternatives, not for the recommendation.
- The concrete entry points are: an issue in the Official Docs queue (the guide asks for exactly this), a child page under the community "Local server setup" guide (a "Drupal Wiki guide" with listed maintainers; the exact edit rights were not verified), and the Drupal CMS Launcher alternatives list.
- The Launcher shows that the Drupal Association wants a non-Docker path for site builders but has not funded one for developers. Its last push was in April 2026, so the niche of "native, no Docker, for developers" is open. Its alternatives list already names a non-Docker tool (Laravel Herd), so a native Drupal-specific tool has a natural place there.
- DrupalCon BoFs and the Driesnote have launched local tools before (DDEV's survey BoF, the Launcher in a Driesnote). Orlando in March 2027 is the next North American slot, and Edinburgh in October 2027 the next European one.
- Hosting companies are neutral or DDEV-aligned. Pantheon's own page names Lando, yet Pantheon is the most common `ddev pull` target, so host documentation lags actual use. Pulling a database and files from the host is a core DDEV workflow; a new tool would need its own pull integration to compete for agency use, which is not MVP work.

#### Adoption implications
| ID | Channel or decision maker | What it takes to get in (process, owner) | How DDEV got there | Sources |
|---|---|---|---|---|
| ADOPT-01 | Official Local Development Guide | An issue in the Official Docs queue (https://www.drupal.org/project/issues/official_docs); the guide itself invites it | Named in the guide since its first commit (4c5b48ee, 2019-04-11); formal approval in June 2024 | Local Development Guide; official_docs git history |
| ADOPT-02 | Community "Local server setup" guide | Add or edit a child page of the wiki guide; edit rights not verified | Top of the guide as the recommendation | Local server setup page |
| ADOPT-03 | Ideas queue decision | A proposal with criteria, community discussion, approval | [Issue 2965681](https://git.drupalcode.org/project/ideas/-/work_items/2965681), 2018 to 2024 | [project/ideas issue 2965681](https://git.drupalcode.org/project/ideas/-/work_items/2965681) |
| ADOPT-04 | Drupal CMS Launcher alternatives list and download page | A pull request to the Launcher README; contact with its maintainers at the Drupal Association | Listed as "platform of choice" | Launcher README; download page |
| ADOPT-05 | DrupalCon sessions and BoFs | Call for sessions per event; BoFs at the event | BoF at Nashville 2018; survey BoF at Portland 2024 | [ideas issue 2965681](https://git.drupalcode.org/project/ideas/-/work_items/2965681); DDEV blog; events.drupal.org |
| ADOPT-06 | Contrib and core CI parity | DrupalCI image maintainers and gitlab_templates maintainers | Not applicable (CI uses Apache images) | drupalci-environments; gitlab_templates |
| ADOPT-07 | Hosting company integrations | Partnerships or add-ons per host | ddev-upsun add-on; Acquia quickstart uses DDEV | Upsun docs; DDEV 2025 review; Acquia portal |

### Gaps
- The guide source links the Official Docs issue queue as https://www.drupal.org/project/issues/official_docs (`guide_local_development.asciidoc` line 127, branch 9.0.x). I did not open the queue itself.
- A DrupalCon Barcelona 2024 session, "Local development environments for Drupal with DDEV", appears in search results (https://events.drupal.org/barcelona2024/session/local-development-environments-drupal-ddev), but I could not open it.
- Search results say Acquia Dev Desktop reached end of life on 2021-06-30, but the Acquia blog URL returned HTTP 404, so this is unconfirmed.
- The current guide maintainers of "Local server setup" come from the fetch tool and were not checked by hand. The page's last-updated date was not returned.
- I could not confirm a #ddev or local-development channel in Drupal Slack from a primary source.
- I found no public statements from large Drupal agencies about their standard local tool, other than Velir via Kevin Quillen's post.
- The Drupal Association's budget or roadmap for the Launcher after April 2026 is unknown.

## Adoption: local tool market share, surveys and Windows

### Takeaway
DDEV dominates Drupal local development: 60% of Drupal Developer Survey respondents used it in 2024 (42% in 2023), and 93% of its users recommend it (2025). DDEV's telemetry counts about 30,700 monthly users in September 2026. Among about 24,600 users seen in a recent 7-day window, 53.2% run macOS, 21.5% Linux and about 25% Windows (19.2% WSL2, 3.7% traditional Windows, 2.2% WSL2 mirrored). All these counts are lower bounds. Among all developers, Windows is the most used OS (49.5% professional use in Stack Overflow 2025), but Drupal-focused tooling skews to macOS. FreeUnit does not run on Windows, so freeunit4drupal's Windows story must be WSL2.

### Cited Findings

#### Drupal community surveys
- The Drupal Developer Survey is run by Ironstar. It was "originally launched in 2018 by Jeff Geerling and Chris Urban and now led by Mike Richardson at Ironstar". The 2025 edition "captured responses from 753 developers across 58 countries, a notable increase from 648 responses in 2024." [The Drop Times: Drupal Developer Survey 2025 Results are Out!](https://www.thedroptimes.com/51215/drupal-developer-survey-2025-results-are-outx) (2025-08-27)
- 2025 result: "DDEV has solidified its position as the dominant local development environment, with 93 percent of users recommending it. While many developers still have experience managing their own LAMP or WAMP stacks, most no longer endorse that approach. Homebrew came in second with a 72 percent recommendation rate, while Lando trailed at 51 percent." [The Drop Times](https://www.thedroptimes.com/51215/drupal-developer-survey-2025-results-are-outx) (2025-08-27)
- 2024 result: "60% of survey respondents reported using DDEV, compared to 42% in 2023." [DDEV: DrupalCon Portland 2024 Wrapup](https://ddev.com/blog/drupalcon-portland-wrapup/) (2024-05-14)
- The Ironstar results pages (https://ironstar.io/devsurvey25, devsurvey24, devsurvey23) returned HTTP 404 on 2026-10-07, so the full usage tables could not be read.
- Earlier history: the 2020 Drupal Local Development Survey had about 600 responses (down from about 1,100 in 2019); "Lando and Docksal have accelerated their growth, DDEV has jumped up four spots". [Jeff Geerling: 2020 Drupal Local Development Survey Results](https://www.jeffgeerling.com/blog/2020/2020-drupal-local-development-survey-results/) (2020-05-08)

#### DDEV's published statistics
- Unique monthly users: 24,706 (November 2025), 26,445 (February 2026), 28,541 (May 2026), 29,874 (August 2026), 30,682 (September 2026). [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)
- Environments (users seen in about the last 7 days, about 24,600 in total): macOS 53.2% (13,072), Linux 21.5% (5,292), WSL2 19.2% (4,717), traditional Windows 3.7% (915), WSL2 mirrored networking 2.2% (542), Codespaces 0.1%. macOS machines are 94.7% Apple Silicon. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)
- Docker providers on macOS: Docker Desktop 55.6%, OrbStack 32.7%, Colima 8.3%, Rancher Desktop 2.1%. On WSL2: docker-ce inside WSL2 64.4%, Docker Desktop 35.1%. On Linux: native Docker 95.5%. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)
- Project types (a count of configured projects, about 30,900 in total): drupal11 18.6% (5,764), php 16.1% (4,989), drupal10 13.4% (4,134), wordpress 12.0% (3,699), laravel 10.5% (3,237), typo3 7.5% (2,325), craftcms 4.5% (1,387), then drupal9 (1,097), drupal7 (392), drupal8 (127), drupal12 (63). All Drupal project types together: 11,617 projects, 37.6%. PHP version settings are counted the same way (about 35,600 in total). PHP versions in DDEV projects: 8.3 34.5%, 8.4 32.0%, 8.2 13.2%, 8.5 7.4%, 8.1 6.1%, 7.4 3.9%. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07)
- Caveat on the page itself: "Amplitude changed its terms for free projects, and DDEV's usage tracking now exceeds the free event quota partway through most months. The Amplitude-based stats below are significantly incomplete as a result." Usage reporting is opt-in. [DDEV usage statistics](https://ddev.com/usage-stats/) (accessed 2026-10-07); in early 2024 DDEV wrote "we don't know what percentage of users opt in" and counted "about 10,000 weekly users". [DDEV Project 2023 Review](https://ddev.com/blog/2023-review/) (2024-02-29)
- github.com/ddev/ddev had 3,906 stars on 2026-10-07 (GitHub API); the 2025 review cited "3,400+ GitHub stars". [DDEV 2025 Year in Review](https://ddev.com/blog/2025-review/) (2026-01-15)

#### OS share among developers in general
- Stack Overflow Developer Survey 2024, "What is the primary operating system in which you work?": Windows 59.2% personal and 47.6% professional use; macOS 31.8% and 31.8%; Ubuntu 27.7% and 27.7%; WSL 17.1% and 16.8%; Debian 9.8% and 9.1%. [Stack Overflow 2024 survey, technology](https://survey.stackoverflow.co/2024/technology) (parsed from the page HTML, 2026-10-07)
- Stack Overflow Developer Survey 2025, same question: Windows 56.7% personal and 49.5% professional; macOS 32.7% and 32.9%; Ubuntu 27.8% and 27.7%; Linux outside WSL 17.6% and 16.7%; WSL 15.9% and 16.8%; Debian 11.4% and 10.4%. Respondents could pick several systems. [Stack Overflow 2025 survey, technology](https://survey.stackoverflow.co/2025/technology) (parsed from the page HTML, 2026-10-07)
- PHP use: 18.2% of all respondents and 18.7% of professional developers in 2024 (parsed from the page HTML); 18.9% of all respondents and 19.1% of professional developers in 2025 (from the fetch tool, not parsed). [Stack Overflow 2024](https://survey.stackoverflow.co/2024/technology); [Stack Overflow 2025](https://survey.stackoverflow.co/2025/technology) (accessed 2026-10-07)
- JetBrains State of PHP 2025 had 1,720 respondents with PHP as their main language; 89% use PHP 8.x; Laravel 64%, WordPress 25%, Symfony 23%. The post gives no OS or local environment numbers. [The State of PHP 2025](https://blog.jetbrains.com/phpstorm/2025/10/state-of-php-2025/) (2025-10-15)

#### Windows pain reported by Drupal developers
- DDEV FAQ: "WSL2 has intolerably slow access to the Traditional Windows NTFS/CIFS filesystem, but the WSL2 filesystem is blazingly fast. It's really important to move your project to the WSL2 native filesystem." [faq.md](https://github.com/ddev/ddev/blob/3af9704770e/docs/content/users/usage/faq.md) line 309 (2026-08-31)
- DDEV troubleshooting: "Firefox on Windows does not use the Windows certificate store. You must import the mkcert CA manually". [troubleshooting.md](https://github.com/ddev/ddev/blob/18ecfec928e/docs/content/users/usage/troubleshooting.md) line 342 (2026-09-23)
- DDEV's 2025 work list includes a "New Windows GUI Installer handling Traditional Windows, WSL2/Docker CE, and Docker/Rancher Desktop", "ARM64 Windows installer support", and docs fixes for PhpStorm inside WSL2 and WSL2 mirrored networking. [DDEV 2025 Year in Review](https://ddev.com/blog/2025-review/) (2026-01-15)
- drupal.org forum, 2026-02-14: a user on Ubuntu in WSL2 wrote "I did not found any instructions to install Drupal/CMS 2.0 without DDEV", found the documentation "fragmented with circular links", and reported that DDEV's installation failed when it tried to write the Windows hosts file. A reply: "DDev is not required, but it will make your life much, much easier." [No instructions to install CMS 2.0 without DDEV](https://drupal.org/forum/support/installing-drupal/2026-02-14/no-instructions-to-install-cms-20-without-ddev) (2026-02-14, replies to 2026-04-27)
- The Launcher README spends its troubleshooting section on Windows: "On Windows, the built-in firewall may block the launcher because it needs to start a local web server." [cms-launcher README](https://github.com/drupal/cms-launcher/blob/9d81aebf729/README.md) (2026-01-19)
- drupal.org keeps separate Windows guides under "Local server setup": "Windows development environment", "Installing Drupal on Windows for local usage" and "Installing Drupal with DDEV in WSL2 on Windows". [Local server setup](https://www.drupal.org/docs/develop/local-server-setup) (accessed 2026-10-07)

#### Docker Desktop licensing
- Docker's plans FAQ limits use of Docker Desktop without a paid subscription to non-commercial open source projects and to commercial undertakings with fewer than 250 employees and less than USD 10,000,000 in annual revenue; government entities need a subscription in all cases. [Docker plans FAQ](https://www.docker.com/pricing/faq/) (accessed 2026-10-07; paraphrased, because the fetched wording dropped the source's "(i)" and "(ii)" markers)

#### FreeUnit platform support
- "Unit compiles and runs on various Unix-like operating systems, including: FreeBSD 10 or later, Linux 2.6 or later, macOS 10.6 or later, Solaris 11". Windows is not listed. [source/installation.rst](https://github.com/freeunitorg/docs/blob/c5a372568cd/source/installation.rst) lines 55 to 62 (docs HEAD c5a372568cd, 2026-10-04)
- "As of April 2026, FreeUnit is distributed via Docker images, source builds, and pre-built `unitctl` binaries from GitHub Releases." Native packages are "coming soon". The old packages.nginx.org repositories "belong to the archived NGINX Unit project and will not receive further updates". [source/installation.rst](https://github.com/freeunitorg/docs/blob/c5a372568cd/source/installation.rst) lines 12 to 21 (2026-10-04)
- In static-php-cli's released line, the embed SAPI build is "not available on Windows". [BuildPHPCommand.php](https://github.com/crazywhalecc/static-php-cli/blob/956fa13e7c3/src/SPC/command/BuildPHPCommand.php) line 34 (branch main at 956fa13e7c3, 2026-08-22). I did not check the unreleased v3 branch.
- FreeUnit's GitHub repository was created on 2026-04-02 and has 57 stars; its newest release is 1.37.0 (2026-10-04). The archived nginx/unit repository has 5,538 stars. [freeunitorg/freeunit](https://github.com/freeunitorg/freeunit), [nginx/unit](https://github.com/nginx/unit) (GitHub API, accessed 2026-10-07)

### Inferences
- About a quarter of DDEV users run Windows (WSL2 19.2% + traditional 3.7% + mirrored 2.2% = 25.1%), and almost all of them already work inside WSL2. Requiring WSL2 is therefore normal for this audience, not a new burden. Native Windows support is not possible with FreeUnit and is not needed for the MVP.
- macOS is the largest group (53.2%), and every one of them carries a Docker cost today: Docker Desktop licensing for larger companies, OrbStack (32.7% of macOS DDEV users) as a separate product, or Colima. A native macOS tool that avoids Docker, file sync and Mutagen is the clearest value proposition.
- The Windows share among all developers (about half) is much higher than among DDEV users (a quarter). Either Windows Drupal developers are under-represented in DDEV telemetry, or Drupal developers use macOS more than developers in general. The data cannot separate these.
- Every number here is self-selected (surveys) or opt-in and incomplete (DDEV telemetry). Treat them as directional. The strongest signal is the agreement between the survey (DDEV 60% use, 93% recommend) and the documentation (DDEV recommended).

#### Adoption implications
| ID | Finding | Implication for freeunit4drupal | Strength of evidence (strong, medium, weak) | Sources |
|---|---|---|---|---|
| MKT-01 | DDEV is used by most Drupal developers and recommended by 93% of its users | Position as a complement for "no Docker" cases, not a replacement; offer import of `.ddev/config.yaml` settings | Medium (self-selected survey) | Drop Times 2025; DDEV blog 2024 |
| MKT-02 | 53.2% of DDEV users are on macOS, 94.7% of those on Apple Silicon | Ship macOS arm64 first, with Linux as the build and CI platform | Medium (opt-in telemetry) | DDEV usage stats |
| MKT-03 | About 25% of DDEV users are on Windows, mostly WSL2 | Support WSL2 in the MVP; document project placement in the WSL2 file system | Medium | DDEV usage stats; DDEV FAQ |
| MKT-04 | FreeUnit does not run on Windows; static-php-cli's released embed build excludes Windows | No native Windows plan; WSL2 only | Strong for FreeUnit (primary docs) | installation.rst; BuildPHPCommand.php |
| MKT-05 | Docker Desktop needs a paid plan for companies with 250 or more employees or USD 10 million or more in annual revenue, and always for government entities | A Docker-free tool removes a licensing question for agencies | Strong (vendor terms) | Docker FAQ |
| MKT-06 | Drupal 11 and 10 dominate DDEV Drupal projects; Drupal 12 is at 63 projects before release | Drupal 11 on PHP 8.4 is the main MVP target; Drupal 12 on PHP 8.5 must work by December 2026 | Medium | DDEV usage stats; release schedule |
| MKT-07 | Windows users report Docker, hosts file and documentation friction | A WSL2-native guide without Docker can fill a documented gap | Weak (one forum thread plus vendor docs) | drupal.org forum; DDEV docs |

### Gaps
- The full 2023, 2024 and 2025 Ironstar survey tables (usage shares for Lando, Docksal, native stacks, and respondents' OS) were not reachable (HTTP 404).
- I found no Drupal-specific survey with OS shares, and no JetBrains PHP OS breakdown for 2024 or 2025.
- I found no Reddit threads with usable quotes through search; the Windows pain evidence comes from drupal.org and DDEV sources.
- The Drupal Business Survey 2025 targets agencies; search results say it ran from 19 August to 23 September 2025 ([The Drop Times, node 51052](https://www.thedroptimes.com/node/51052)), but I did not open that page or the questionnaire, so I do not know whether it asks about local tools.

## Existing use of NGINX Unit or FreeUnit for Drupal

### Takeaway
Public Drupal-on-Unit material is thin. The archived NGINX Unit howto covers only the request path and a `.htaccess` translation. FreeUnit's howto has grown since September 2026 and now covers cron, terminate-phase work, idle and request timeouts, OPcache and route-order pitfalls, but it still says nothing about upload size, forwarded headers, TLS, trusted hosts or private files. The most complete Drupal configuration for FreeUnit is the DrupalCI `php-8.5-ubuntu-unit` image on the `dev` branch. Drupal's own documentation and core do not mention NGINX Unit or FreeUnit at all.

### Cited Findings

#### NGINX Unit (archived)
- "As of October 2025, NGINX Unit is archived and unmaintained." The Drupal howto steps are: install Unit with a PHP module, install Drupal's prerequisites and core files, run `chown -R unit:unit /path/to/app/`, then apply a configuration in which "The default .htaccess scheme in a Drupal installation roughly translates into the following". [Drupal on NGINX Unit](https://unit.nginx.org/howto/drupal/) (accessed 2026-10-07)
- The nginx/unit GitHub repository is archived; its last push was on 2025-10-08. [nginx/unit](https://github.com/nginx/unit) (GitHub API, accessed 2026-10-07)
- A search of nginx/unit issues for "drupal" returns one result: [nginx/unit issue 1301](https://github.com/nginx/unit/issues/1301), "NGINX UNIT "PHP" application is not supported on RHEL 9" (2024-06-05, closed). [GitHub issue search](https://github.com/nginx/unit/issues?q=drupal) (accessed 2026-10-07). The nginx/unit-docs repository has a closed pull request, "Drupal's update.php needs workaround" (#48, 2022-11-24). [nginx/unit-docs#48](https://github.com/nginx/unit-docs/pull/48) (accessed 2026-10-07)
- A web search for Drupal on NGINX Unit tutorials, Docker images or benchmarks returned only the official howto and preview copies of it on frontdoor-test-docs.nginx.com. (WebSearch, 2026-10-07)

#### FreeUnit's Drupal howto, checked against the briefing claim
- The current file is [source/howto/drupal.rst](https://github.com/freeunitorg/docs/blob/8326bb78610/source/howto/drupal.rst), 440 lines, last changed on 2026-10-04 (commit 8326bb78610, "docs(drupal): run /index.php and its PATH_INFO forms as PHP"). Earlier commits: 31279266bc0 (2026-09-27, "Cache-Control on the share, opcache, and the 1.37.0 fixes") and 02ab81516dd (2026-09-13, "docs: cron and background work on Drupal, and the two missing limits options").
- Request path: one listener on `*:80`; a first route that returns 404 for a deny list of globs (`!*/.well-known/*`, `/vendor/*`, the Umami default content, `*.engine`, `*.inc`, `*.install`, `*.make`, `*.module`, `*.po`, `*.profile`, `*.sh`, `*.theme`, `*.tpl`, `*.twig`, `*.xtmpl`, `*.yml`, `*/.*`, CVS names, `*/composer.json`, `*/composer.lock`, `*/web.config`, `*sql`, backup suffixes); a `direct` PHP target for `/index.php`, `/update.php`, `/core/install.php`, `/core/rebuild.php`, `/core/authorize.php`, `/core/core.api.php`, `/core/globals.api.php`, `/core/modules/statistics/statistics.php` and the test front controller; a route that returns 404 for any other `*.php`; and a final `share` with `Cache-Control: max-age=31536000` that falls back to `index.php` (lines 30 to 125).
- Warnings in the howto: route order matters, and a misordered "static files first" share returns `/sites/default/settings.php` "verbatim with your database password in the body" (lines 148 to 163); on case-insensitive file systems the deny list "misses /core/composer.JSON, /WEB.CONFIG, an uppercase .YML and the rest" (lines 181 to 192); copies of the upstream configuration "still carry the original claim that the rule denies "any PHP scripts"" (freeunit#323, line 203).
- Cron and timeouts are now covered: the "Cron and background work" section explains the terminate phase, the FreeUnit 1.37.0 fix, `idle_timeout` (default 15 seconds), `limits.timeout` ("this option defaults to 0, which starts no timer at all"), automated_cron, running `drush cron` from a systemd timer or system cron, OPcache surviving process exits, and `pcntl_alarm()` to bound jobs (lines 236 to 440).
- Not covered: there is no mention of request body or upload size (`max_body_size` has zero hits; the only "upload" hit is the include `howto_upload_config.rst`, which uploads the Unit configuration), no forwarded headers (`forwarded`, `X-Forwarded` and `client_ip` have zero hits), no TLS (`tls` and `certificate` have zero hits; the listener is `*:80` and the text says "browse to http://localhost"), and nothing on `trusted_host_patterns`, private files, Xdebug or Composer beyond installation (keyword counts on the file at commit 8326bb78610).
- So the briefing claim ("covers the request path and a .htaccess translation but says nothing about cron, uploads, timeouts, forwarded headers or TLS") is out of date for cron and timeouts, and still true for uploads, forwarded headers and TLS.
- Two `direct` paths in the howto do not exist in Drupal 12: `core/authorize.php` is absent at C12 (present at C11 and C10), and `core/modules/statistics/statistics.php` is absent at C12 and C11 (present at C10) (git object checks at each commit).
- FreeUnit's generic documentation covers the missing topics: `max_body_size` "Maximum number of bytes in the body of a client's request. If the body size exceeds this value, Unit returns a 413 "Payload Too Large" response" with default 8388608 (8 MB), plus `body_read_timeout` and `idle_timeout` ([configuration/index.rst](https://github.com/freeunitorg/docs/blob/0c64361efa4/source/configuration/index.rst) lines 5775 to 5857); the listener `forwarded` object with `client_ip` and `protocol` (lines 465 to 634); and certificate bundles under `/certificates` with a certbot howto ([certificates.rst](https://github.com/freeunitorg/docs/blob/bf6f370269c/source/certificates.rst)) (commits of 2026-09-27 and 2026-10-04).

#### Drupal's Apache rules that a FreeUnit configuration must replace
- `core/assets/scaffold/files/htaccess` (C12): FilesMatch protection for source extensions, dot files and VCS names (line 6); `Options -Indexes` (line 16); `DirectoryIndex index.php index.html index.htm` (line 19); `AddEncoding gzip svgz` (line 23); `ExpiresDefault "access plus 1 year"` except for PHP (lines 36 to 51); `protossl` from HTTPS and `HTTP_AUTHORIZATION` pass-through (lines 66 to 75); deny hidden directories except `.well-known` (line 90); redirect `install.php` and `rebuild.php` into `core/`; front controller to `index.php`; deny other PHP files except `core/*.php` and two test files, and explicitly `autoload.php`; serve brotli and gzip CSS and JS aggregates.
- Drupal also writes `.htaccess` files into public and private file directories (`SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006`, `php_flag engine off`, `Require all denied`) (`core/lib/Drupal/Component/FileSecurity/FileSecurity.php` lines 68 to 96, C12). FreeUnit does not read `.htaccess`, as the FreeUnit howto states ("Unit does not read .htaccess", line 205).

#### The DrupalCI FreeUnit configuration
- The `dev`-branch DrupalCI image (see the Testing section) translates more of the Apache rules than the howto does: regex deny rules that mirror FilesMatch, denial of `sites/*/private` and `sites/*/temp`, `.svgz` handling, brotli and gzip aggregates, a 1 GiB `max_body_size`, and `fastcgi_finish_request` disabled. [conf/unit/drupal.json](https://gitlab.com/drupal-infrastructure/drupalci/drupalci-environments/-/blob/dev/php/8.5-ubuntu-unit/conf/unit/drupal.json) (commit 4e0adb89, 2026-10-05)

#### FreeUnit issues and pull requests driven by Drupal
- A selection of open issues: [issue 323](https://github.com/freeunitorg/freeunit/issues/323) "static: PHP source stays reachable past the usual guards" (title shortened, 2026-09-12); [issue 546](https://github.com/freeunitorg/freeunit/issues/546) "static: a share cannot refuse a file only when it exists (Apache <FilesMatch> parity)" (2026-10-03); [issue 559](https://github.com/freeunitorg/freeunit/issues/559) "static: extend the built-in MIME table and give it a maintenance policy" (2026-10-04). Closed: [issue 321](https://github.com/freeunitorg/freeunit/issues/321) "router: a PHP worker running the terminate phase is counted idle and never replaced" (opened 2026-09-12, closed 2026-09-18) and [issue 322](https://github.com/freeunitorg/freeunit/issues/322) on Last-Modified time zones (2026-09-12). [freeunitorg/freeunit issues matching "drupal"](https://github.com/freeunitorg/freeunit/issues?q=drupal) (accessed 2026-10-07)
- Open pull requests: [pull request 452](https://github.com/freeunitorg/freeunit/pull/452) "feat(router): add schedules" (2026-09-24; milestone 1.37.1, description says 1.38.0) and [pull request 453](https://github.com/freeunitorg/freeunit/pull/453) "feat(docker): add a Drupal core dev kit image" (2026-09-24, draft). Merged: [pull request 560](https://github.com/freeunitorg/freeunit/pull/560) "http: serve a static .svgz file as gzip-coded image/svg+xml". [freeunitorg/freeunit pull requests matching "drupal"](https://github.com/freeunitorg/freeunit/pulls?q=drupal) (accessed 2026-10-07)

#### Drupal-side traces
- A grep of core and the core CI files at C12 for "nginx unit", "unit.nginx", "freeunit" and "unitd" finds nothing.
- No drupal.org documentation page found in this research lists NGINX Unit or FreeUnit; the web server requirements page names Apache, Nginx, IIS and the PHP built-in server only (last updated 2026-09-13).
- FreeUnit reports its SAPI as "cli-server" (src/nxt_php_sapi.c line 302, FU 872bf041). In core only `.ht.router.php` checks for that name (C12), so no core behaviour changes.

### Inferences
- The FreeUnit howto is a sound base for the request path, but freeunit4drupal should generate its configuration from the DrupalCI `drupal.json` rather than from the howto, because that file already covers private and temp directories, upload size and compressed aggregates. Its rules still need case-insensitive rewrites: none uses `(?i)`, and four are plain wildcards.
- The gaps the MVP must close on top of the howto are: upload size aligned with PHP's `upload_max_filesize` and `post_max_size`; deny rules for private and temp directories; case-insensitive matching on macOS and Windows hosts; an HTTPS listener with local certificates; `forwarded` settings only when a proxy is used; cron through a timer (or FreeUnit `schedules` once merged); and per-version entry points, since two of the howto's PHP paths no longer exist in Drupal 12.
- The DrupalCI image and the open FreeUnit dev kit pull request ([pull request 453](https://github.com/freeunitorg/freeunit/pull/453)) overlap with freeunit4drupal. One shared Drupal configuration for CI, the dev kit and the local tool would reduce drift.

#### Requirement catalogue rows
| ID | Requirement for freeunit4drupal | Drupal mechanism | How DDEV satisfies it today | Priority (MVP or later) | Sources |
|---|---|---|---|---|---|
| UNIT-01 | Generate FreeUnit routes from one maintained template that mirrors Drupal's `.htaccess` (deny source files, dot paths, vendor, private and temp; run only known PHP entry points; front controller fallback) | Drupal `.htaccess`, FileSecurity `.htaccess` files | nginx-fpm or apache-fpm inside the web container with DDEV's own config | MVP | htaccess and FileSecurity.php (C12); DrupalCI drupal.json; FreeUnit howto |
| UNIT-02 | Pick PHP entry points per Drupal major (no `core/authorize.php` for Drupal 12) | Front controllers that exist in each version | Generic nginx front controller rules (not checked here) | MVP | git object checks (C12, C11, C10) |
| UNIT-03 | Set `settings.http.max_body_size` at least as large as PHP's `post_max_size`, and both above Drupal's upload needs | Drupal reads upload limits from PHP ini | Not verified in this research | MVP | configuration/index.rst; DrupalCI drupal.json |
| UNIT-04 | Use case-insensitive deny patterns where the file system is case-insensitive | FilesMatch semantics on Apache | Linux container file system | MVP | FreeUnit howto lines 181 to 192 |
| UNIT-05 | Run cron outside visitor requests (timer now, FreeUnit `schedules` later) and decide on `fastcgi_finish_request` | automated_cron in the terminate phase | ddev-cron add-on | MVP | FreeUnit howto; [pull request 452](https://github.com/freeunitorg/freeunit/pull/452); DrupalCI drupal.json |
| UNIT-06 | Add an HTTPS listener with a local CA and certificate bundles | `isSecure()`, SSESS cookies | mkcert plus Traefik | Later | certificates.rst |
| UNIT-07 | Serve pre-compressed aggregates and correct MIME types (`.mjs`, `.svgz`, `.webp`) | Drupal writes `.gz` and `.br` aggregates | nginx config | Later | htaccess (C12); DrupalCI drupal.json; FreeUnit [issue 559](https://github.com/freeunitorg/freeunit/issues/559) and [pull request 560](https://github.com/freeunitorg/freeunit/pull/560) |
| UNIT-08 | Contribute the missing topics (uploads, forwarded headers, TLS, private files) back to the FreeUnit Drupal howto | Documentation | Not applicable | Later | source/howto/drupal.rst |

### Gaps
- drupal.org's issue search could not be queried with scripted tools, so a drupal.org issue that mentions NGINX Unit could exist without my finding it.
- I did not test the FreeUnit howto or the DrupalCI configuration against a Drupal 12 site.
- I did not compare the DrupalCI `drupal.json` rule by rule with `.htaccess`; the comparison above lists the parts I read.
