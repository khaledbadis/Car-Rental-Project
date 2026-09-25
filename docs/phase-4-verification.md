# Phase 4 verification — 25 September 2026

## Delivered

- Walk-in drafts and confirmed-reservation conversion; one vehicle commitment and one financial account preserve inventory and advances.
- Handover eligibility for actual individual/company driver: required identity/licence details and scans, issue/expiry dates, company contact details, unarchived records. Expired identity/licence cannot be overridden, including by Managers. A company driver may be assigned at handover.
- Saved rate and agreement snapshots, numbered immutable versions, daily/weekly/monthly pricing, reasoned discounts/custom prices, extensions and draft cancellation.
- Immutable handover/return mileage, fuel, condition and optional private photo attachments; decreasing return mileage requires a Manager reason.
- Append-only charges, payments/advances, deposits, applications/refunds, cash refunds and linked reversals. Integer centimes, explicit fees, visible debt/credits; return with unpaid debt is allowed.
- Active overdue occupancy blocks availability and flags affected reservations. Return releases the rental allocation and creates the saved preparation period from actual return.
- Shared authorized Livewire/API actions, actor-scoped UUID retry keys, stale-version rejection, audited mutations, private downloads and scoped token enforcement.
- FR/AR/EN screens, RTL and theme support; rental/financial history on catalog profiles, rentals on the weekly calendar.

## Automated evidence

Docker PostgreSQL suite: **76 tests, 438 assertions**, including existing phases and 25 new rental tests. The two rental concurrency tests use independent PHP processes contending on the shared agency lock against the isolated test database.

Coverage includes:

- Full advance → conversion → handover → payment → deposit → damage fee → application/refund → return reconciliation.
- Expired licences/identity, missing driver fields/scans, incomplete company details, late driver assignment and immutable party snapshots.
- Agent 10% discount boundary, cent rounding, Manager-only custom bases, reason requirements, whole-period rules and saved-rate extensions.
- Overdue vehicle availability and booking warnings; conflicting extension rollback; early return preparation; debt after return; reasoned odometer correction.
- Retry replay and changed-payload conflict, duplicate reversals, cash refund/deposit limits and dependent-entry protection.
- Concurrent deposit applications cannot overspend; concurrent identical payment retries produce one entry.
- Database immutability, private photo content/download checks, Finance download denial, read-only token denial of writes, stale versions.
- Reservation customer cannot change after posting advances, preserving financial attribution.
- Livewire screens in all three locales and handover/payment submissions.

A rollback issue discovered by the concurrency suite was fixed: rollback maps rental commitments to temporary blocks before restoring Phase 3 constraints, retaining occupancy without orphan rental references. Runtime rollback still removes Phase 4 rental/financial tables; deployment rollback must restore a reconciled backup, not downgrade live financial data.

Required commands:

```sh
docker compose exec -T app php artisan test --compact
docker compose exec -T app vendor/bin/pint --test
docker compose exec -T app php artisan view:cache
docker compose run --rm node npm run build
```

## Browser evidence

Using only fictional local fixtures, created rental #1 for RENTALDEMO01/Fictional Rental Driver with blank optional fields, handed over the vehicle, posted an 8,000 DZD test payment, and returned it. Verified status Returned, two condition records and zero outstanding balance. No real money changed hands. The demonstration record remains visible.

Checked the rental detail screen at 360, 768, 1024 and 1440px widths without horizontal overflow after layout transitions settled. Visually checked English dark, Arabic RTL light and French light. Restored the demo account’s original English/System preferences and browser viewport. Native phone camera capture and physical-device acceptance remain release checks; backend photo validation/access is covered automatically.

## Rules and boundaries

Money is stored as integer centimes; percentage discounts use half-up rounding. Daily units are 24 hours rounded up; weekly/monthly units are 7/30 days and must be whole periods unless a Manager supplies a reasoned custom base. Extra fees are separate from the discounted base and never automatically inferred at return.

Identity issue/expiry dates are currently required for each supported identity type. Vehicle insurance/inspection are checked through planned return at conversion, handover and extension; each insufficient coverage requires a fresh reasoned Manager override. There is no driver-expiry or double-booking override.

The allocation exclusion constraint covers finite reservation/rental intervals; shared transaction/vehicle locks and dynamic active-overdue checks also protect physical availability. Emergency/preparation blocks may overlap future reservations and flag them for staff resolution.

Ledger rows, inspection records, photo metadata and agreement versions reject updates/deletes at PostgreSQL level. Corrections use linked reversals; base price adjustments use the extension/cancellation workflow. Refunds do not erase charges. Deposit application reduces held funds and debt without pretending to be new cash collection. All financial actions require a reason. Financial account customer attribution is locked once entries exist.

API 1.3 requires a UUID idempotency_key in each mutation body (multipart for photos) and the current version for lifecycle changes. Successful retries return the previously created record; rental retries may show its current state if later operations have occurred. Reusing the key for another operation or changed normalized input returns 409. Photo retries include content hashes.

Numbered agreement data snapshots are ready for Phase 5 printable templates. Legal wording, printable contracts, numbered receipts, operational dashboard charts/cards, staff profile expansion, migration rehearsal and production hosting remain Phase 5. Maintenance and expense/profitability reporting remain later phases.
