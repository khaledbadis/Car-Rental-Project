# Car rental management — Phase 7

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
- `src/app/Modules/Catalog/Actions.php`: shared fleet/customer, duplicate detection, archive and private document rules.
- `src/app/Modules/Reservations/Actions.php`: shared availability, confirmation, cancellation, reassignment and operational block rules.
- `src/app/Livewire`: web presentation adapters.
- `src/app/Http/Controllers`: web and versioned API adapters using the same actions.
- `src/config/access.php`: three role permission sets; role + token scope checks on the server.
- `src/lang/{fr,ar,en}`: translated UI, validation and pagination; French default, Arabic RTL.
- `docs/openapi.json`: Foundation, catalog, reservations, rentals and financial ledger API contract.
- `project-spec.md`: requirements, phase checklists and acceptance gates.

API requests accept Sanctum bearer tokens and require both user permissions and token abilities. Token issuance/mobile login is deliberately deferred to the mobile scope; no insecure public token-minting endpoint exists. API feature tests exercise real scoped tokens. Livewire uses session/CSRF protection and directly invokes shared actions rather than making HTTP calls to itself.

Staff edits serialize against the singleton agency row, protect the last active Manager, require a reason, and revoke sessions/tokens after reset, disablement or role changes. Audit rows reject updates/deletes at PostgreSQL level. Database administrators still control the database; separate restricted production runtime/migration credentials are a deployment task.

Preferences persist per user. Themes default to OS preference until chosen. The sidebar collapses on desktop and slides over a dismissible backdrop on phones. The top bar provides breadcrumbs, fleet/customer search, document expiry notifications and a profile shortcut.

## Fleet, customers and documents

Vehicles support categories, daily/weekly/monthly rates, search and archival. Individual and company customers support linked drivers, duplicate warnings, notes and private documents. Updates require reasons and reject stale versions. Archival preserves records, notes and document references. Linked reservations and operational blocks are visible on profiles. Profiles also show rental history, outstanding balances, held deposits and credits.

Managers manage fleet records, archive records and remove documents with an audited reason. Agents can manage customers and upload permitted documents. Finance has read-only catalog access and cannot access customer scans. Authorization is shared between the web interface and `/api/v1`.

Uploads accept PDF/JPEG/PNG with actual content validation, up to the agency limit (maximum 10 MB). Files are stored privately and downloaded through authenticated, authorized routes. Vehicle insurance and inspection expiry appears in the notification menu; dates use the agency timezone. Mobile file inputs offer camera capture where supported by the device.

Optional fictional catalog fixtures:

```sh
docker compose exec app php artisan db:seed --class=CatalogDemoSeeder
```

See [Phase 2 verification](docs/phase-2-verification.md) for checks and remaining device validation.

## Reservations and availability

Open Reservations for tentative inquiries, confirmed bookings, date/status filters and a responsive weekly calendar. Select a customer and exact pickup/return times; confirmation requires an assigned vehicle. Edits and cancellation require reasons. Reassignment uses the same edit form and availability checks. Cancellation releases inventory; the linked ledger preserves advances for explicit settlement.

Availability & blocks searches by interval, category and transmission and explains unavailable vehicles. Search includes the current preparation setting; confirmation snapshots it, and later settings changes do not alter accepted commitments. Exact pickup at the prior buffered end is allowed. Scheduled and indefinite blocks have explicit release actions. Ordinary cleaning/inspection/preparation/temporary blocks are available to agents; maintenance, administrative and emergency blocks require a Manager. Emergency blocks retain bookings and flag conflicts for resolution.

Insurance and technical inspection must have a current scan and known expiry covering planned return. The latest uploaded non-removed document of each type is authoritative. Missing or insufficient coverage requires a reasoned Manager override scoped to that vehicle, interval and set of issues. There is no double-booking override. No-show alerts do not cancel bookings or release commitments automatically.

Schedule writes use transactions, the shared agency lock and vehicle locks in sorted order. PostgreSQL additionally rejects overlapping reservation and rental allocations using a GiST exclusion constraint; no extension is needed. This deliberately permits overlapping emergency operational blocks. Rental conversion transfers the same allocation. Physically overdue vehicles remain unavailable until return; returns replace rental occupancy with the saved preparation interval starting at actual return.

API dates require explicit offsets and seconds (`2027-01-10T10:00:00+01:00`); web forms use agency-local time. Conflict responses use HTTP 409 with stable `code`, localized `message` and optional details. See [OpenAPI](docs/openapi.json) and [Phase 3 verification](docs/phase-3-verification.md).

Preferences now supports editable full name, contact details and private profile images. The permanent staff-ID-based username cannot change; email remains the login identifier. Historical audit attribution is preserved.

## Rental operations and finances

Open Rentals to create a walk-in draft, or convert a confirmed booking from its reservation page. Complete the actual driver’s identity/licence details and private scans before handover. Expired driver documents cannot be overridden. Company drivers can be assigned at handover. Record mileage, fuel and condition at both handover and return; attach optional private JPEG/PNG photos.

The agreement keeps rates and party details in numbered snapshots. Daily pricing rounds up in 24-hour units; weekly/monthly use whole 7/30-day periods unless a Manager agrees a custom base. Discounts and custom pricing need reasons; Agents may discount the base by up to 10%. Extensions recheck availability and preserve earlier versions. Return does not automatically charge fees or reduce the agreed price.

Each reservation/rental has an append-only ledger: charges, advances/payments, security deposits, deposit application/refund, payment refunds and linked reversals. Deposits are held separately. Returns may leave visible debt. Manager/Finance perform refunds, deposit settlement and corrections. Repeated requests with the same idempotency key post once; negative deposit balances and duplicate reversals are rejected.

Optional, clearly fictional local fixtures (requires the existing demo accounts):

```sh
docker compose exec app php artisan db:seed --class=RentalDemoSeeder
```

This adds RENTALDEMO01 and Fictional Rental Driver with synthetic placeholder documents. It does not replace existing records. See [Phase 4 verification](docs/phase-4-verification.md) and [OpenAPI](docs/openapi.json). Phase 5 adds a live operational dashboard, expanded profiles and bilingual printable contracts/numbered receipts. See [Phase 5 verification](docs/phase-5-verification.md) for behavior and remaining launch gates.

## Deployment boundary

This Compose configuration is a local development environment with a PHP development server and local database credentials. It is not a production deployment. Production FPM/Caddy images, Compose configuration, restricted-role setup and backup/restore procedure are prepared in [deploy](deploy/README.md). VM provisioning is explicitly deferred. Real-data import, measured restore, monitoring setup and staff cutover remain open. No external infrastructure has been changed.

Official references used for setup: [Laravel releases](https://laravel.com/framework/docs/releases), [Livewire installation](https://livewire.laravel.com/docs/4.x/installation), [Flux installation](https://fluxui.dev/docs/installation).

The homepage now summarizes agency operations, with date filters and separate revenue/collection trends for Manager/Finance. Receipt links appear in the rental/reservation ledger; agreement print links appear on each rental version. Browser Print / Save as PDF handles document output. Sample agency text lives in src/config/contract.php and remains clearly marked until replaced.

## Maintenance and next phases

The Maintenance screen provides completed service history, date/mileage reminders, private evidence and explicit release of linked maintenance blocks. Managers record services; agents see operational history; Manager/Finance can read costs and attachments. Reminder thresholds are editable in Settings. Vehicle profiles link to filtered history. See [Phase 6 verification](docs/phase-6-verification.md).

Expenses and management reports are implemented in Phase 7. CI/CD foundation follows in Phase 8; the owner handles VM provisioning, secrets, activation and actual deployment. See the [owner deployment guide](docs/deployment-guide.md) and [project specification](project-spec.md). Existing launch gates remain open.

## Expenses and reports

Manager/Finance can use Expenses for additional vehicle costs, private evidence and reasoned corrections. Maintenance service costs appear automatically once; do not enter them again. Management reports share revenue/collection rules with the dashboard, show utilization and vehicle contribution, and export CSV using applied date/vehicle filters. Unknown service-entry dates or inconsistent occupancy history produce N/A utilization. See [Phase 7 verification](docs/phase-7-verification.md) for definitions, limitations and export defaults.
