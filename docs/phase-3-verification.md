# Phase 3 verification — 24 September 2026

Implemented reservations, availability and operational blocks through shared application actions, Livewire screens and `/api/v1` adapters. Added responsive list/weekly calendar views and linked reservation/block history on customer and vehicle profiles. Expanded staff profile editing is recorded in the specification for Phase 5; it is not implemented in this phase.

## Evidence

- **46 PostgreSQL tests, 256 assertions passed**, including all previous phase checks. Reservation tests cover tentative inquiries without inventory, required assigned vehicles, overlap rejection, exact buffered boundaries, retained preparation settings, stale edits, cancellation/release, no-show alerts, scoped document overrides, role/token restrictions, reassignment rollback, scheduled/indefinite/emergency blocks, archive history and calendar preparation spanning midnight.
- A concurrency test runs two independent PHP processes against `rental_test`, holds them behind a transaction lock, and then releases both. Exactly one confirmation succeeds; the other returns an overlap conflict. Only one reservation allocation persists.
- A separate test bypasses application actions and proves PostgreSQL rejects an overlapping reservation allocation with SQLSTATE `23P01`. The GiST exclusion constraint uses a one-point vehicle ID range plus a half-open timestamp range; no PostgreSQL extension is required. Emergency operational blocks intentionally remain outside this exclusion.
- Laravel Pint passed, Blade view compilation passed, frontend production build passed and `git diff --check` passed. OpenAPI JSON parses and documents new inputs, filters, permissions, responses and conflict codes.
- Browser: created a fictional tentative inquiry without an assigned car; attempted confirmation and observed the mandatory-document conflict; supplied a fictional Manager exception and verified the confirmed booking and saved 120-minute preparation buffer. Availability explained both document issues and the existing reservation interval including preparation.
- Browser: inspected English/dark reservation list/calendar and narrow availability layout, Arabic/light calendar and tablet creation dialog, and French/light reservation list. Representative widths: 360, 768, 1024 and 1440 CSS pixels. No horizontal page overflow observed in the inspected settled mobile and desktop layouts. Restored the demo Manager to English/system and cleared the viewport override.

## Rules and implementation decisions

- Web forms interpret times in Africa/Algiers. API inputs require explicit offset and seconds. Persist timestamps in UTC. Date-only mandatory-document expiry is valid through the expiry date in agency time.
- A missing insurance/inspection document or unknown expiry is treated as unverified coverage. The latest non-removed upload per type is authoritative. A reasoned Manager override is scoped to the vehicle, exact booking interval and current issues. Reassignment or period changes trigger a new eligibility check; overlap cannot be overridden.
- Schedule mutations use the agency lock followed by vehicle locks in sorted ID order, coordinating with catalog changes and document removal. Existing small-agency scale favors straightforward serialized writes. Vehicle allocation, reservation update and audit events commit atomically.
- Ordinary blocks reject overlaps. Managers can create emergency blocks that preserve affected bookings and flag them on detail, list and calendar screens. Agents can create/release ordinary cleaning, inspection, preparation and temporary blocks; maintenance, administrative and emergency actions require a Manager.
- Cancellation preserves the booking and commitment history and releases inventory. Block release preserves its original dates, reason and release timestamp. Financial settlement is deliberately separate.
- No-show status is computed at read time; it never automatically cancels or releases a commitment. Staff must reschedule or cancel explicitly. Calendar filters include preparation time crossing into the selected period.

## Remaining phase boundaries

Reservation conversion, walk-in rentals, handover, active overdue occupancy, return preparation and finances remain Phase 4. That work must extend the same commitment representation and exclusion invariant, transferring reservation occupancy atomically rather than creating a competing allocation. `converted` is a reserved status, not a currently exposed operation.

The fictional browser booking is marked as a demo in its notes and override reason. No real customer documents were added and no production infrastructure changed. Physical-phone camera acceptance remains the prior Phase 2 device check. Staging performance, exhaustive accessibility/device acceptance and production backup/restore remain launch work.

Planned Preferences work in Phase 5 includes editable full name, email, phone, address and profile picture, plus an immutable unique username separate from full name. The plan requires a migration for existing staff and durable audit attribution independent of mutable profile fields.

## Follow-up: blank form values — 24 September 2026

The reported vehicle creation/edit failures were caused by optional numeric/date inputs arriving from Livewire as empty strings. HTTP string-cleaning middleware does not normalize values restored from Livewire component state. Earlier tests omitted these fields or supplied null, so they missed the real form payload. Logs also showed the same issue with optional customer dates.

Added `App\Support\Input` normalization before shared Foundation, Catalog and Reservations action validation. Blank ordinary strings become null; nonblank strings are trimmed. Zero values, booleans and uploaded files retain their types. Password contents are not trimmed, and an empty optional password preserves the existing hash on staff edits.

Added Livewire/PostgreSQL regressions for vehicle creation and clearing existing rates/dates, individual/company customer fields, cleared upload expiry, staff creation and password-free edits, and invalid-value/API parity checks. Full suite: **51 tests, 291 assertions passed**. Pint and Blade compilation passed. No migration or development-data reset is required.
