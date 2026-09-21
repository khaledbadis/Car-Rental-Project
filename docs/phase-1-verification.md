# Phase 1 verification — 20 September 2026

Implemented the Docker/Laravel foundation, staff authentication and recovery, three roles, shared application actions and `/api/v1` adapters, agency defaults, audit history, and responsive French/Arabic/English shell with persisted appearance.

## Evidence

- PostgreSQL feature suite: **18 tests, 95 assertions passed**. Covers guest/API authentication, disabled accounts, login throttling, web/API role enforcement, restricted Sanctum tokens, staff creation and password redaction, last-manager protection, reset/token revocation, mandatory password change, Livewire authorization, shared settings validation/audit, locale/theme persistence, page rendering and database-level audit immutability.
- PHP formatting applied with Laravel Pint; Blade view cache compilation passed.
- Composer platform requirements pass on the actual PHP 8.4 Docker runtime; Composer and npm dependency resolution reported no known vulnerabilities at installation time.
- Vite/Tailwind production asset compilation passed, using local system fonts without a remote font dependency.
- Browser: logged in as the synthetic demo Manager; checked preferences and staff screens at 360, 768, 1024 and 1440 CSS-pixel widths. Inspected phone navigation and Arabic RTL/dark mode, then English/light mode. No horizontal overflow observed in inspected layouts; no browser console errors recorded. Restored the demo Manager to French/system appearance afterward.
- Fixed the missing staff card translation and localized the save notice in the newly selected language.
- The documented `./scripts/setup` completed end to end and retained the existing application key and demo data.
- Shell scripts pass syntax validation; OpenAPI JSON parses successfully.

The initial test execution inherited the Compose development database setting. Added an explicit test bootstrap override (including clearing DB URL) before any database refresh and restored synthetic local data. Subsequent suite runs use `rental_test`; no real client data was present. The audit trigger function now supports repeatable fresh migrations.

## Scope and practical limits

This is a development foundation, not the agency launch. Fleet/customer records, uploads, reservations, financial transactions, contracts, and operational reports remain in their planned later phases. Role permissions for those future modules are declared but do not imply their workflows exist.

The OpenAPI contract covers the Phase 1 adapters. Mobile token issuance/login is deferred; scoped Sanctum bearer tokens are supported and tested. Web requests use session authentication and CSRF protection.

The local Docker setup uses Laravel's development server, localhost-only binding, and local database credentials. Production VM provisioning, HTTPS/reverse proxy, restricted database runtime credentials, backup/restore acceptance, and deployment automation belong to Phase 5.

Browser checks are representative layout verification, not a claim of exhaustive device, accessibility, or language proofreading coverage. Future screens must follow the same layout/translation checks.
