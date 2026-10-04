# Phase 6 — Detailed maintenance

Implemented 4 October 2026. Phase 5 agency launch gates remain open; proceeding with maintenance does not certify production readiness.

## Delivered

- Completed service records with vehicle, type (oil, tyres, brakes, inspection, repair, other), service date/mileage, exact DZD cost, notes and optional next date/mileage. No service edit/delete endpoint; history is retained.
- Manager-only creation and private PDF/JPEG/PNG uploads up to the configured agency limit (maximum 10 MB). Manager/Finance can read costs, notes and evidence; agents receive operational fields only. Private downloads use attachment disposition, nosniff and no-store. Role and token abilities both apply.
- Shared application actions for Livewire and API. UUID service retries are serialized under the existing agency lock; changed retry input is rejected. Audit creation and attachments. A higher reading advances the vehicle odometer and version; historical readings never lower it.
- Latest service date then ID per vehicle/type determines the next reminder. A newer service with empty next-due fields clears the previous reminder. Backdated services do not displace newer ones. Date checks use agency calendar dates; mileage uses the latest recorded vehicle reading. Due if either threshold is reached, soon if within configured 30 days/500 km defaults. Archived vehicles have history but no current reminders or new service creation.
- Reminders on the maintenance screen and operational dashboard. Settings exposes date/km warning thresholds. Vehicle profiles link to filtered maintenance history.
- Work records can reference an existing maintenance block for the same vehicle. Recording does not create/release occupancy. Explicit release delegates to the shared versioned scheduling action with a reason and existing permissions; booking conflicts remain governed by the established availability module. Create scheduled, indefinite or emergency blocks in Availability.
- Maintenance costs remain on the service record; no rental ledger charge/payment is created. Phase 7 will link expenses to this identity and count the cost once.

## Verification

Full Docker/PostgreSQL regression suite: **92 tests passed, 563 assertions**, including five new maintenance tests. Coverage includes idempotent replay/mismatch, odometer monotonicity, due/soon threshold boundaries, date-only timezone handling, superseded reminders, dashboard integration, correct/mismatched block associations, retained occupancy, stale release, private uploads, role/token scope, invalid input and Livewire/API parity. Render tests cover FR/AR/EN.

Pint passed across 132 PHP files; frontend production build and Blade compilation passed. Applied the new migration to local development. Tests use rental_test and the existing separate concurrency database, never the local agency database. No production changes or infrastructure provisioning occurred.

Browser checks: English/system-dark desktop (1440px) and phone (360px), Arabic/light RTL (360px and 768px), French/light laptop (1024px), no horizontal document overflow. Examined empty reminders/history and service form layout. Restored the original English/System demo preferences. Populated history, creation and attachments are covered by integration/Livewire tests; no new demo service was inserted into the local database for screenshots. Physical-device upload remains an owner acceptance check.

## Deployment planning

Phase 8 now follows Phase 7 in project-spec.md. docs/deployment-guide.md records owner setup, private connectivity, protected secrets, staged activation, migration/recovery and launch responsibilities. Actual CI/CD workflow/script implementation is scheduled for Phase 8, not claimed complete now. Real-data staging, staff acceptance, paired restore and cutover remain open launch gates.
