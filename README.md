# Car rental management — Phase 1

Laravel 13 / PHP 8.4, Livewire 4, Flux 2 (free components), Tailwind 4, PostgreSQL 17. Composer and npm lockfiles pin installed packages. All runtimes run in Docker.

## Start locally

From this directory, with Docker Engine and Compose installed:

```sh
./scripts/setup
```

Setup installs dependencies, generates an application key only if missing, migrates PostgreSQL, builds assets, and starts app/worker/scheduler. It preserves existing data and keys. The development server binds only to localhost. PostgreSQL is not published to the host.

Open [http://localhost:8080](http://localhost:8080). To add fictional local demo accounts:

```sh
docker compose exec app php artisan db:seed
```

Accounts: `manager@demo.test`, `agent@demo.test`, `finance@demo.test`. Local-only password for each: `LocalDemo!2026`. Seeding is prohibited outside local/testing and does not reset existing accounts. These are synthetic fixtures, never deployment credentials.

For a clean environment without an existing Manager:

```sh
docker compose exec app php artisan app:create-manager
```

This prompts privately for a password, records provisioning in the audit log, and forces a password change on first login. Further staff and recovery are handled in Team by a Manager. No registration or SMTP-dependent password reset is exposed.

If your UID/GID differs from 1000, export `LOCAL_UID=$(id -u)` and `LOCAL_GID=$(id -g)` for manual Compose commands (setup does this automatically). Change `APP_PORT` and `src/.env` APP_URL together if 8080 is occupied.

## Verification

Create the separate test database once:

```sh
docker compose exec db createdb -U rental rental_test
```

Or run `./scripts/check` to create the test database if missing and run all checks.

Individually:

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
docker compose run --rm node npm run build
```

Tests force `rental_test` at application bootstrap to protect the normal development database from Compose environment overrides. `RefreshDatabase` is destructive to the test database only. All integration tests use PostgreSQL, including the append-only audit trigger.

Stop containers with `docker compose down`. Do not add `-v` unless you intend to erase the local database volume.

## Structure and boundaries

- `src/app/Modules/Foundation/Actions.php`: shared authorization, validation, staff management, settings and audit operations.
- `src/app/Livewire`: web presentation adapters.
- `src/app/Http/Controllers/Api`: versioned API adapters using the same actions.
- `src/config/access.php`: three role permission sets; role + token scope checks on the server.
- `src/lang/{fr,ar,en}`: translated UI, validation and pagination; French default, Arabic RTL.
- `docs/openapi.json`: Phase 1 API contract.
- `project-spec.md`: requirements, phase checklists and acceptance gates.

API requests accept Sanctum bearer tokens and require both user permissions and token abilities. Token issuance/mobile login is deliberately deferred to the mobile scope; no insecure public token-minting endpoint exists. API feature tests exercise real scoped tokens. Livewire uses session/CSRF protection and directly invokes shared actions rather than making HTTP calls to itself.

Staff edits serialize against the singleton agency row, protect the last active Manager, require a reason, and revoke sessions/tokens after reset, disablement or role changes. Audit rows reject updates/deletes at PostgreSQL level. Database administrators still control the database; separate restricted production runtime/migration credentials are a deployment task.

Preferences persist per user. Themes default to OS preference until chosen. Settings are defaults for future phases; there is no rental pricing/availability engine yet. Files have no upload endpoint yet; the initial configurable cap is 10 MB, to be enforced by Phase 2 upload validation and runtime configuration.

## Deployment boundary

This Compose configuration is a local development environment with a PHP development server and local database credentials. It is not a production deployment. Phase 5 will supply the dedicated VM, production web runtime/reverse proxy, HTTPS, secrets, restricted DB credentials, backups, restore test and monitoring. No external infrastructure has been changed.

Official references used for setup: [Laravel releases](https://laravel.com/framework/docs/releases), [Livewire installation](https://livewire.laravel.com/docs/4.x/installation), [Flux installation](https://fluxui.dev/docs/installation).
