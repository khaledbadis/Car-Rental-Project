# Phase 5 — application delivery and deployment preparation

Status: application features implemented; VM provisioning, real-data cutover and staff launch acceptance remain open. The owner explicitly requested deployment preparation now and VM provisioning later.

## Delivered application slice

- Operational dashboard replaces the quick-access landing page. Live fleet counts distinguish rented, maintenance, other blocks, current allocations and available vehicles; future reservations alone do not mark a car rented. Urgent links cover affected bookings/drafts, overdue returns, late pickups and missing/expiring vehicle documents.
- Date-filtered pending pickups/returns, fleet composition chart, separate daily revenue/collections chart and accessible data table. Current status refreshes every 60 seconds. Manager/Finance see monetary summaries/trends; Agents see operational information and continue accessing individual rental ledgers.
- Revenue uses rental start dates; standalone cancellation charges use their effective date. Drafts and imported opening balances are excluded. Collections use actual payment/refund/reversal effective dates. Held or applied deposits are not new cash collection. Debt, held deposits and customer credits are separately labeled.
- Preferences supports full name, email, phone, address and private profile photo upload/replacement/removal. JPEG/PNG up to 2 MB and 4096×4096px. Login continues using email.
- Permanent usernames are generated as staff-{database ID}, distinct from editable names and emails. Deterministic backfill has no collisions. API/staff actions reject username edits; a PostgreSQL trigger also prevents changes. New audit events snapshot username. Older events remain unchanged and display their stable user-ID-associated username.
- Receipt numbers are allocated atomically with ledger writes: REC-YYYY-000001 and REF-YYYY-000001, based on issuance year in Africa/Algiers. Deposits, applications and corrections have explicit types. Reversal documents link the corrected entry/receipt and are distinguished from refunds. Reprints retain their number and frozen data.
- Bilingual French/Arabic print pages use saved agreement versions, physical signature areas and a conspicuous sample marker. New agreement snapshots freeze template content. Existing Phase 4 versions receive a frozen legacy-template default without rewriting their historical snapshot. Replace config/contract.php for future agreements; old versions retain their content.
- Browser print supports paper or the browser’s Save as PDF option. This is not a server-side PDF download API. Contract data and receipts also have authorized JSON endpoints for future mobile clients. Finance can print receipts but cannot access contracts containing driver identity details.

## Verification

Final formatting verification on 27 September 2026: Pint passed across all 122 PHP files in a temporary Docker container. The final changes were validation translations and whitespace formatting; application services were left stopped.

The PostgreSQL suite covers prior phases plus profile/receipt/dashboard behavior, token scope, private avatars, frozen templates, negative collection display, invalid date filters and migration reconciliation. Existing independent-process ledger races also verify unique receipt issuance. Final full-suite result: **87 tests passed, 504 assertions**. Pint checks and Blade view compilation passed; Vite production assets built successfully.

Browser checks use the existing demo Manager and fictional rental #1. Verified profile name edit/restore while staff-1 remains unchanged; contract CON-1-V2 and receipt REC-2026-000001 show saved data, readable Arabic and sample markings. Checked the dashboard at 360, 768, 1024 and 1440px without horizontal overflow. Visually verified French/System dark, Arabic RTL light and English dark. Restored the original French/System demo preferences and name.

No staff acceptance session, physical printer check or production restore drill has been performed. Those remain release gates; browser previews do not substitute for physical print acceptance.

## Fictional migration rehearsal

Run locally:

```sh
docker compose exec -T app php artisan app:rehearse-cutover
```

Input: database/fixtures/cutover-sample.json. Reconciles two vehicles, two customers, one active rental, one upcoming reservation, outstanding 4500.00 DZD and remaining held deposits 25000.00 DZD. Checks imported active occupancy and rolls back every inserted record. Opening balances are separate from new revenue/cash. Sequence IDs may be consumed by PostgreSQL even though records roll back.

This is a fixed, rollback-only rehearsal using synthetic legacy data, not a production spreadsheet importer. Real field mapping, source-ID duplicate handling, document migration and final reconciliation require a later staging run with the actual export. Already-active imports represent historical handover, not a new eligibility approval. Missing scans must be reviewed before the next new handover.

Existing local ledger entries predating receipts can be backfilled once with:

```sh
docker compose exec -T app php artisan app:backfill-receipts
```

Backfill preserves existing numbers; missing receipts use the issuance year and current party/agency details available at backfill time. Normal new entries snapshot at posting. Opening import entries must not be treated as new cash receipts.

## Deployment preparation

See ../deploy/README.md, ../deploy/compose.yaml and ../docker/Dockerfile.production. Prepared production FPM/Caddy images, runtime-only application credentials, separate database-owner credentials, worker/scheduler, HTTPS entry point, persistent attachments/database and backup procedure. Runtime cannot update/delete immutable records.

Production backup is a paired dump/attachment snapshot with writers stopped. Restore procedures explicitly target an isolated environment and preserve the application encryption key. VM/domain, backup destination, alert routing, restore timings and staff cutover are not configured or claimed complete.

## Remaining launch gates

- Provision isolated staging and production VM/domain, secrets, runtime grants and monitoring.
- Stage actual imports and reconcile source totals, especially opening debt and exact remaining deposits.
- Approve replacement agency/legal content and inspect physical/PDF print output.
- Perform a measured restore of database and attachments (RPO≤24h, RTO≤4h).
- Staff acceptance across roles/devices; agree legacy freeze and approve cutover.

## Build evidence

Production FPM/application and Caddy/web targets built locally. Fixed initial build ordering so Laravel cache directories exist before Composer package discovery. The application image compiled Blade views in a temporary container with network disabled. Caddy configuration validated in another isolated container. Compose configuration and backup/entrypoint shell syntax checks passed. No deployment services, public ports, VM or DNS were provisioned.
