# Car Rental Management — Project Specification

Status: Phase 3 implemented · 24 September 2026

Sources: [Client brief](client-brief.md), [Client answers](QA.txt), [Resolved decisions](Decisions.txt), and project owner's technical requirements. The owner's 20 September instruction authorizes fictional documents and migration samples in place of waiting for client materials; placeholders must remain identifiable and replaceable. This document defines scope and implementation gates; it does not authorize implementing every phase at once.

## 1. Outcome and scope

Replace paper folders, disconnected spreadsheets, and operational WhatsApp messages with one reliable internal platform. A manager should understand fleet conditions and urgent actions within approximately 30 seconds of opening the dashboard.

Initial scale: one agency, 40–50 vehicles, 6–8 employees, normally 3–5 concurrent users. Secure Internet access is required, including access outside the office. V1 requires connectivity; temporary outages use paper followed by manual entry.

The first usable release must complete: **Vehicle → Customer → Reservation → Rental → Handover → Payment → Return**, with date-based availability, maintenance blocking, roles, audit history, documents, contracts, receipts, and operational dashboard. Development milestones before that release are not independently sufficient for agency cutover.

Later releases add detailed maintenance management, expenses, and broader reporting. A future phone app must reuse the backend; building that app is outside this project’s initial scope.

Excluded initially: customer accounts, public booking, native mobile app, offline synchronization, GPS, payment gateways, government integrations, accounting/ERP, payroll, category capacity allocation without assigned cars, automatic fee engines, and permanent Excel synchronization.

## 2. Product-wide requirements

- French is the default UI language; French, Arabic, and English are supported from the foundation onward. Arabic uses RTL layout. All user-facing labels, validation, notifications, and operational status text use translation keys. User-entered notes are not automatically translated.
- Light and dark themes, with an initial system preference and a persisted user choice.
- Responsive layouts across phone, tablet, laptop, and desktop. Forms, calendars, tables, dialogs, document previews, and inspection uploads must remain usable at narrow widths. No essential action may depend on hover.
- DZD throughout V1; display amounts such as `8 000 DA` using appropriate locale formatting. Store money with exact precision, never floating point. Distances use kilometres; fuel inspections use percentage, with litres available for fuel-related records where needed.
- Store timestamps consistently in UTC and display/interpret agency operations in `Africa/Algiers`. Use date-only fields for document expiry dates. Validate exact pickup and return times.
- Printable contracts use a replaceable French/Arabic template, initially populated with clearly labeled fictional agency details and sample content. Support PDF/print followed by physical customer/driver and agency-representative signatures; no electronic signatures. Receipts require no customer signature. UI locale does not automatically translate contract content.
- Keyboard-accessible controls, visible focus, sufficient contrast, labeled inputs, and readable errors in both themes and all locales.

## 3. Roles and control

Permissions are enforced server-side in shared application operations and exposed consistently through web and API. UI visibility alone is not authorization.

| Capability | Manager | Rental Agent | Cashier / Finance |
|---|---|---|---|
| Manage staff and settings | Yes | No | No |
| Manage core fleet data, rates, and archive vehicles | Yes | No | No |
| Create ordinary cleaning/inspection/preparation/temporary blocks | Yes | Yes | No |
| Customers, reservations, rental operations | Yes | Yes | Read details needed for finance |
| Collect normal rental payments | Yes | Yes | Yes |
| Receive deposits and print receipts | Yes | Yes | Yes |
| Refunds, financial reversals, deposit refunds/application | Yes | No by default | Yes |
| Negotiated price overrides / discounts | Yes | Standard rates and base discount ≤10% only | No |
| Cancel reservations | Yes, reason required | Yes, reason required; financial settlement handled by Manager/Finance | No |
| Override expired mandatory vehicle documents | Yes, reason required | No | No |
| Full financial reports and audit access | Yes | Limited operational view | Finance scope |

Use permissions rather than an approval workflow: an authorized person performs sensitive actions under their own account. Managers alone configure vehicle rates. For a fixed-amount agent discount, enforce the same 10% limit against the undiscounted base charge; repeated edits cannot accumulate beyond this limit. Never share manager credentials.

Audit sensitive actions with actor, entity, action, timestamp, before/after values where applicable, and reason: price/discount changes, reservation cancellation, refunds, reversals, deposit application, document deletion, document-expiry override, conflict resolution/reassignment, and permission changes. Audit logs cannot be edited through normal application operations. Do not copy document contents or credentials into logs.

## 4. Functional requirements

### Staff profiles — planned for Phase 5

Expand Preferences before staff acceptance/cutover: editable full name, contact phone, email, postal address and profile picture. Keep an immutable, unique username separate from the editable full name. Existing accounts require a deterministic backfill with collision review before the username becomes mandatory; do not derive permanent audit identity from a mutable email or display name. Audit events retain the stable user ID and username attribution, while profile changes must not rewrite historical events. Decide whether the existing email-based login remains or accepts username as well during this implementation; no login change is implied now.

Use shared web/API profile actions, server-side self-edit authorization, validated private image storage and replacement/removal, translated labels, and responsive avatar controls. Audit profile edits without copying contact details or image contents unnecessarily. Username edits must fail through both web and API; changing full name or email must preserve prior audit attribution. Password recovery remains Manager-assisted unless separately changed. Requested 24 September 2026; deliberately deferred from the reservation phase.

### 4.1 Vehicles and documents

- Store registration, make, model, year, category, fuel type, transmission, mileage, default daily rate, optional weekly/monthly rates, and operational notes.
- Store insurance, technical inspection, registration, and supporting documents with type, applicable expiry date, and private scan/photo attachments.
- Keep a vehicle’s rental history, current activity, upcoming commitments, and blocks visible in its profile.
- Archive vehicles rather than deleting referenced history. Availability is derived, never controlled by a single available/unavailable boolean.
- Warn for upcoming insurance/inspection expiry at configurable thresholds initially 30, 15, and 7 days. Expired mandatory documents prevent handover unless the manager records an override reason.

### 4.2 Customers

- Support individuals and basic company customer records, including the actual driver's details when the renter is a company. Detailed company billing workflows are deferred.
- Individual/actual-driver fields required before handover: full name, date of birth, phone, address, identity-document type and number, identity issue/expiry dates when applicable, licence number, issue date, expiry date, issuing country, and uploaded ID and licence scans/photos. Allow incomplete profiles during inquiry; enforce completeness at handover.
- An expired licence blocks handover with no Manager override. An expired identity document also blocks handover when its type has an expiry date; no customer-document override is provided. Company records require legal name, phone, address, and contact person, with registration/tax identifier optional. Record an actual driver meeting the same individual requirements separately from the company renter.
- Show all linked reservations, rentals, outstanding amounts, and dated incident notes: late returns, unpaid amounts, accident, damage, and other relevant observations.
- Detect likely duplicates using normalized document identifiers and phone/name matches. Warn for shared phone numbers rather than treating every shared number as the same customer. Reuse an existing profile; never silently merge records.

### 4.3 Reservations and availability

- Tentative inquiry may record requested category without a vehicle. Tentative inquiries do not reserve inventory in V1.
- Confirmation requires one specific eligible vehicle and exact start/end timestamps. Requested category remains recorded.
- Reservation lifecycle: tentative → confirmed → converted to rental; tentative/confirmed → cancelled. After pickup time, display “Pickup overdue / awaiting action”; highlight strongly after a configurable two-hour no-show grace. Never release automatically. Staff explicitly continue with late arrival, reschedule with fresh availability checks, or cancel as no-show with a reason. Cancellation releases the remaining commitment; required refunds/retained charges remain a separate Manager/Finance task.
- Search by requested interval and optional category/transmission filters. Show why a vehicle is unavailable.
- Default preparation buffer: two hours after expected return, configurable for future commitments. Persist the applied buffer with each commitment so changing a setting does not silently alter accepted reservations.
- Use half-open blocked intervals `[start, end + preparation buffer)`: a new pickup exactly at the buffered end is permitted. Validate overlap atomically against reservations, rentals, maintenance, and other operational blocks.
- Converting a reservation into a rental transfers its occupancy; the same booking must not conflict with itself or leave a gap where another booking can claim it.
- An active overdue rental remains physically unavailable until checked in and released, regardless of its originally scheduled end. Flag affected future reservations; do not automatically cancel them. Proposed V1 policy: block new confirmations on an overdue vehicle until return or an explicit extension/resolution establishes a reliable schedule.
- Extensions and reassignments run the same conflict checks. Reject a conflicting extension until staff move/cancel the conflicting commitment or otherwise resolve it. There is no generic override that permits double booking.
- Scheduled maintenance blocks a defined interval. Indefinite maintenance blocks from its start until explicitly released. Emergency blocks may expose existing conflicts; retain bookings, flag them, and require staff resolution.
- Mandatory vehicle documents must cover pickup through planned return, otherwise confirmation is blocked. A Manager can override only with an audited reason scoped to that booking and checked period; recheck on extension, reassignment, and handover. This exception never bypasses customer-document eligibility or vehicle overlap rules. Implementation assumption: a date-only expiry remains valid through the end of that date in Africa/Algiers.
- Dashboard “available now” is distinct from “available for the requested interval.” A future reservation does not automatically mean the car is physically rented now.

### 4.4 Rental, handover, and return

- Support reservation conversion and direct walk-in rentals through the same availability and validation rules.
- Suggested rental lifecycle: draft → active at handover → returned/closed. Payment status remains separate, so a closed rental may have an outstanding balance.
- Snapshot customer/vehicle contract details, agreed dates, rate, selected billing basis, discount, mileage allowance/unlimited choice, and financial terms so subsequent profile/rate edits do not rewrite past agreements.
- Handover requires complete customer prerequisites, eligible vehicle, starting mileage, fuel percentage, and condition confirmation/notes. Photos are optional and can be captured/uploaded from a phone browser.
- Return records actual timestamp, ending mileage, fuel, condition confirmation, optional damage notes/photos, and explicit additional charges. Reject ending mileage below starting mileage unless a manager performs an audited correction with a reason.
- Return releases active occupancy into preparation time. Proposed default: calculate the return preparation block from actual return time. Emergency extensions of cleaning/preparation use an operational block and flag affected reservations.
- Support a rental extension with audited dates and price changes and a refreshed contract version where needed.
- Prominently identify overdue rentals immediately after expected return time. Billing grace does not hide operational overdue status.

### 4.5 Pricing, payments, and deposits

- Daily pricing is `max(1, ceil(planned duration / 24 hours))`. Monday 10:00 → Tuesday 10:00 is one day; Tuesday 14:00 is two; a five-hour rental is one. The late-return grace never reduces the originally agreed duration.
- Default late-fee grace is one hour, configurable. Late, fuel, damage, excess mileage, and extra-day charges remain manually entered; return processing must not silently auto-charge or reprice the contract.
- Staff explicitly select Daily, Weekly (7 × 24 hours), or Monthly (30 × 24 hours). Weekly/monthly quantities are whole units, with no mixed-rate optimizer. Additional partial periods use Daily pricing or a Manager-negotiated agreed price. Implementation default: require whole-period duration for standard Weekly/Monthly pricing; a Manager may explicitly agree a custom total for a non-whole duration.
- Apply either fixed or percentage discount to the base rental charge only, never fuel/damage/late/excess-mileage/towing or other additional charges. Agents are limited to 10% of the original calculated base, including fixed-amount equivalents. Larger discounts and negotiated base overrides are Manager-only. Persist the agreed calculation and rounding; prevent negative totals. Implementation default: exact decimal DZD with two fractional digits and half-up rounding at charge calculation.
- Itemize base rental, discount, additional charges, cancellation charges where applicable, and reversals/adjustments.
- Show net total, net payments applied toward charges, outstanding balance, and any credit/refund due. Keep security deposit held separately from rental revenue and rental payments.
- Record each payment with amount, method, effective date, recording timestamp, actor, and optional reference. Support advance payments and transfer their association when a reservation becomes a rental without counting the money twice.
- Track deposits as received → held → applied to charges and/or refunded. Never allow application/refund above the remaining held amount. Applying a deposit reduces deposit liability and settles an existing charge; it does not create a second charge.
- Example: deposit received 30,000 DA; damage charge 5,000 DA; deposit applied 5,000 DA; refund 25,000 DA; held balance zero.
- Distinguish refunds (valid collected money actually returned) from void/reversal (neutralizing an erroneous entry). Preserve the original and linked adjustment with reasons; posted records are never overwritten or hard-deleted. A correction cannot be disguised as a cash refund, and dependent deposit applications/refunds must remain consistent.
- Cancellation requires a reason. Staff explicitly record retained advance as a cancellation charge and refund any refundable remainder; do not silently leave retained money unexplained.
- Allow rental closure with debt. Outstanding amounts stay visible on the customer, rental, and finance views.
- Generate transaction receipts using annual atomic sequences `REC-YYYY-000001` and refunds `REF-YYYY-000001`; never reset monthly or reuse numbers. Deposit receipts use REC and the explicit label “Security deposit received.” Reprints retain identity; reversal documents clearly identify the corrected entry. Payment methods: Cash, Bank transfer, CIB / Edahabia / electronic terminal payment, Cheque, Other; allow an optional reference, including for non-cash payments.
- This is an operational ledger, not full accounting. Revenue and cash collections must be labeled separately in reports.

### 4.6 Dashboard and reports

First usable release:

- Total active fleet, rented now, available now, maintenance/other blocks, upcoming pickups and returns, overdue vehicles, document warnings, and outstanding balances within role scope.
- Link urgent items to the vehicle, rental, customer, or reservation needing action. Highlight overdue rentals affecting another booking.
- Date-filtered operational lists and basic rental/payment summaries.

Later reporting:

- Rental revenue, cash collections, rental count, most rented vehicles, utilization, maintenance costs, and vehicle revenue versus expenses.
- Utilization = actual rented hours / eligible hours within the filtered period. Eligible hours exclude scheduled maintenance, breakdown/indefinite maintenance, administrative blocks, and time before entry into service/after archive. Do not exclude idle availability or normal cleaning/preparation. Merge overlapping excluded intervals before subtracting; show N/A for zero eligible hours. Flag inconsistent rented/excluded overlaps for correction rather than silently reporting misleading utilization.
- Attribute base rental revenue and related rental charges to rental start date; report collections separately by payment date. Deposits held are excluded from revenue, and settlement of imported opening debt is not new revenue. Assumption: standalone cancellation charges use cancellation date because no rental started; disclose this separately. Later corrections update the original revenue attribution rather than masquerading as new cash collections.

### 4.7 Maintenance and expenses

- Core release includes scheduled/indefinite blocks and manual release because availability depends on them.
- Later maintenance records include vehicle, service type, date, mileage, cost, notes, attachments, and next due mileage/date.
- Dashboard reminders use the latest recorded mileage; V1 does not have live vehicle telemetry. Make date/mileage warning thresholds configurable.
- Later expenses support vehicle, category, amount, date, description, and evidence. Categories include maintenance, spare parts, cleaning, towing, and insurance.
- A maintenance cost linked to an expense is counted once in reporting.

## 5. Architecture

### 5.1 Application structure

Use a **modular Laravel monolith** with PostgreSQL, Livewire, Flux UI, and Tailwind. Docker is used for both development and deployment on the project owner's infrastructure. A dedicated production VM will be provisioned in the deployment phase. Pin compatible supported package/runtime versions during foundation work; no specific version is assumed here.

Separate presentation from application/domain logic:

```text
Livewire web UI ───────┐
                      ├─> Shared application actions/queries
/api/v1 controllers ──┘       ├─ Authorization and validation
                             ├─ Domain rules and transactions
Future phone app → API       └─ PostgreSQL / private file storage
```

Livewire invokes shared application actions directly; it need not make HTTP requests back to its own server. API controllers invoke those same actions. Neither presentation layer owns pricing, availability, financial, or lifecycle rules. This avoids duplicate business logic while retaining normal Livewire development.

Modules: Identity/Access, Fleet, Customers, Reservations/Availability, Rentals/Inspections, Billing/Deposits, Documents, Maintenance, Expenses, Reporting, Audit/Settings. Keep boundaries in code; microservices and separate backend deployment are unnecessary at this scale.

### 5.2 API and authentication

- Build `/api/v1` endpoints alongside each core module, not as a last-minute wrapper after all UI work. Cover availability, customers, reservations, rental lifecycle, inspections/uploads, payments/deposits, and authorized document retrieval.
- Maintain an OpenAPI contract with request/response examples, validation and authorization errors, pagination, filters, and machine-readable status codes. Display localization belongs to the client; identifiers/status codes remain stable.
- Web authentication uses secure session cookies and CSRF protection. Plan Laravel Sanctum-backed revocable tokens for future first-party mobile clients; token permissions supplement user permissions, never bypass them.
- Financial creation and rental lifecycle mutations support idempotency to make retries safe. Reject stale conflicting edits with a clear conflict response.
- Web/API paths share service-level tests, with focused adapter tests proving equivalent authorization and outcomes.

### 5.3 Persistence and consistency

Conceptual entities: users/roles/permissions; vehicles/categories/documents; customers/driver details/documents/notes; reservations; rentals; vehicle commitments/blocks; inspections/photos; charges; payment and deposit entries; receipts; contract versions; maintenance records; expenses; audit events; settings; import batches/opening balances.

- Use foreign keys, required fields, indexes, and appropriate uniqueness constraints; registration identifiers and document identifiers require normalization rules.
- Maintain one transactional occupancy representation shared by confirmed reservations, active/planned rentals, preparation, and maintenance/operational blocks.
- Serialize availability-affecting mutations with a vehicle-row lock, including booking confirmation, conversion, extension, block creation, return, and reassignment. For reassignment, lock both vehicles in a consistent order. Recheck eligibility and overlap inside the transaction.
- Scheduled overlapping allocations must fail even with simultaneous requests from two employees. Database constraints should reinforce interval invariants where compatible with the treatment of emergency blocks and overdue rentals.
- Financial operations use database transactions, lock affected balances, validate remaining refundable/held amounts, and store audit events atomically with the business change.
- Use exact money values, explicit rounding rules, and immutable posted entries. Archive referenced business records rather than cascading away history.
- Private documents are served through authorized routes or short-lived authorized links. Validate file type, size, and upload content; use non-user-controlled storage names. No public identity-document directory.

### 5.4 Runtime and operations

- Docker services: application/PHP runtime, web entry point/reverse proxy, PostgreSQL, queue worker, and scheduler. Use a simple supported queue/cache backend initially; add Redis only if there is a demonstrated need.
- Keep local development, staging, and production configuration separate. Secrets stay outside source control and images. Use persistent storage for database and attachments.
- HTTPS, secure cookies, login rate limiting, least-privilege database credentials, and restricted infrastructure access. Staff accounts are individually attributable and can be disabled.
- Queue suitable document/notification work; dashboard correctness and booking checks must not depend on a delayed job. Scheduler supports reminders and housekeeping.
- Back up PostgreSQL at least daily and attachments alongside it to separate storage. Accepted recovery targets: RPO ≤24 hours and RTO ≤4 hours. Launch requires a successful measured restore of both database and files.
- Capture application errors, job failures, and health checks without exposing customer document contents. Define backup alerts, deployment procedure, migrations, rollback/forward recovery, and operational ownership before launch.

## 6. Phased implementation checklist and acceptance gates

Work one phase at a time. At each gate, demonstrate the implemented slice, run relevant checks, record decisions, and agree that the next phase is ready. Keep later-phase features out of earlier deliverables unless needed for a stated invariant.

### Phase 0 — Resolve rules and prepare foundations

- [x] Incorporate confirmed permissions from Decisions.txt.
- [x] Record billing periods, rounding, discount limits, document eligibility, and no-show rules.
- [x] Define fictional migration fixtures and field mappings in docs/phase-0-decisions.md; real spreadsheets are not a development dependency.
- [x] Record hosting/retention/backup defaults and owner responsibilities in docs/phase-0-decisions.md.
- [x] Document the initial screen map and FR/AR/EN, RTL, light/dark conventions in docs/phase-0-decisions.md.

Acceptance: decisions and explicitly labeled implementation assumptions are recorded in this spec and docs/phase-0-decisions.md. Real contracts/spreadsheets are replaceable inputs, not blockers for development. Phase 0 documentation is complete; implementation status is tracked below.

### Phase 1 — Technical foundation and access

- [x] Create Docker development environment and Laravel/PostgreSQL application.
- [x] Establish module structure, shared actions, `/api/v1` conventions, and OpenAPI documentation.
- [x] Implement staff authentication, three initial roles, policies, audit infrastructure, and settings.
- [x] Build responsive Flux/Tailwind shell, language selection, RTL support, and persisted themes.
- [x] Establish automated checks, migrations, and seed/demo data.

Acceptance: a clean checkout starts via documented Docker steps; users can sign in and receive correct server-enforced permissions; shell and validation render in all three languages and both themes; an unauthorized API request cannot bypass access rules.

Implementation completed 20 September 2026. See [Phase 1 verification](docs/phase-1-verification.md), [setup instructions](README.md), and [API contract](docs/openapi.json). Production hosting remains Phase 5.

### Phase 2 — Fleet and customer records

- [x] Implement vehicles, categories, rates, archive behavior, and vehicle documents.
- [x] Implement customer/driver profiles, duplicate warnings, notes, and document uploads.
- [x] Implement private attachment access, expiration indicators, and corresponding APIs.

Acceptance: staff can create and reuse customer and vehicle records; unauthorized document access fails; expired/soon-expiring vehicle documents are distinguishable; phone capture/upload works; referenced records retain their history after archiving.

Implementation completed 21 September 2026. See [Phase 2 verification](docs/phase-2-verification.md). Sidebar, navigation, search and notification improvements are included. Physical-phone camera capture remains a device acceptance check; browser upload and mobile layouts have been verified.

### Phase 3 — Reservations and reliable availability

- [x] Implement tentative and confirmed reservations, cancellation, and reassignment.
- [x] Implement interval search, preparation buffers, and scheduled/indefinite operational blocks.
- [x] Add concurrency protection, conflict messages, and APIs.
- [x] Provide calendar/list views of upcoming commitments.

Acceptance: known vehicle-document expiry before return blocks confirmation except for a reasoned Manager override; no-show timers never free a reservation; tentative category inquiry consumes no inventory; confirmation without a vehicle fails; overlapping concurrent confirmations yield only one success; pickup exactly at buffered end succeeds; maintenance blocks exclude vehicles; reassignment atomically releases one car and allocates the other.

Implementation completed 24 September 2026. See [Phase 3 verification](docs/phase-3-verification.md). Rental conversion, active overdue occupancy and financial settlement remain Phase 4.

### Phase 4 — Rental operations and financial records

- [ ] Implement reservation conversion and walk-in rentals with handover prerequisites.
- [ ] Implement price snapshots, discounts, inspections, optional photos, and extensions.
- [ ] Implement charges, advances, payments, deposits, refunds/reversals, and debt visibility.
- [ ] Implement returns, preparation release, overdue detection, and affected-booking warnings.
- [ ] Add equivalent API operations and retry protection.

Acceptance: expired driver licences block even a Manager; a 10% agent base discount succeeds and a larger one fails; additional charges remain undiscounted; complete a rental with advance payment, handover, deposit, damage charge, deposit application/refund, and return; financial totals reconcile. A rental can close with visible debt. Repeated submission produces one financial transaction. A conflicting extension fails without cancelling the next reservation. Missing required handover details block activation. Overdue vehicles remain unavailable after their scheduled end.

### Phase 5 — First usable release and cutover

- [ ] Implement a replaceable bilingual contract template with fictional agency content and physical signature areas; retain a clear sample marker until the owner substitutes final content.
- [ ] Implement stable numbered receipts, contract snapshots/versions, and print layouts.
- [ ] Complete operational dashboard, date filters, and basic finance/overdue lists.
- [ ] Rehearse migration of vehicles, customers, active rentals, upcoming reservations, and opening unpaid balances.
- [ ] Provision staging and production VM/Docker deployment, HTTPS, monitoring, backups, and restore procedure.
- [ ] Expand Preferences with editable full name, phone, email, address and profile picture; introduce immutable unique usernames and preserve audit attribution.
- [ ] Conduct staff acceptance sessions across roles, languages, themes, and screen sizes.
- [ ] Perform approved cutover with reconciliation, training, and rollback/recovery readiness.

Acceptance: staff complete the full everyday workflow without Excel; contracts/receipts print with correct Arabic shaping and no clipped fields; dashboard flags overdue and blocked vehicles; migrated active commitments block availability correctly; opening debt and deposits reconcile to agreed source totals; a backup restores both data and attachments. This is the first agency launch gate.

### Phase 6 — Detailed maintenance

- [ ] Add service history, mileage/date reminders, costs, and attachments.
- [ ] Link work records to existing maintenance blocks and release operations.

Acceptance: record an oil change and next due mileage/date; reminders reflect recorded mileage; maintenance blocking remains consistent with reservation checks.

### Phase 7 — Expenses and management reporting

- [ ] Add vehicle expenses and link maintenance costs without duplication.
- [ ] Implement the agreed utilization denominator and rental-start revenue attribution, alongside payment-date collections.
- [ ] Add date filters and appropriate export options after format agreement.

Acceptance: report totals reconcile to ledger/expense records; security deposits held are excluded from revenue; maintenance is counted once; utilization follows its documented denominator.

### Future work — Separate scope

- [ ] Native phone application consuming the established API.
- [ ] Consider category allocation, public booking, integrations, and other additions only through a new scope decision.

## 7. Migration and cutover requirements

- One-time import with preview/dry run, validation errors by row, explicit field mapping, duplicate handling, import batch identity, and safe retry behavior. No silent overwrites.
- Import vehicles/customers first, then references for current rentals and future reservations. Reject or explicitly resolve overlapping commitments before cutover.
- Import opening customer debts separately from new revenue. Import active-rental payments and deposits still held when relevant; otherwise the first return/refund cannot reconcile.
- Historical rentals are optional. Preserve legacy references where available. Do not invent missing historical transactions or audit actors.
- Rehearse at least one dry run and reconcile counts, debt, and remaining held deposits. At the agreed cutover time make legacy Excel read-only. Enter intervening business into the new system before opening or on paper for immediate back-entry with original timestamps. After final reconciliation, the application is the sole system of record.
- Manual paper catch-up records preserve actual business timestamps and separately record who entered them and when.

## 8. Cross-cutting verification

- Unit/domain checks for interval boundaries, buffer behavior, daily pricing/rounding, debt, refunds, and deposit invariants.
- PostgreSQL integration checks for concurrent booking, conversion, extension/reassignment, emergency maintenance, overdue occupancy, and financial retries.
- Authorization checks for sensitive web/API operations and private file access.
- End-to-end checks for the primary rental workflow and exceptions: cancellation with advance refund, partial payment, manager expiry override, damage/deposit settlement, and closing with debt.
- Visual/manual verification at representative widths of 360, 768, 1024, and 1440 CSS pixels, plus continuous resizing. Cover FR/AR/EN and both themes; verify long names and real Arabic content.
- Print verification with representative multi-page contracts and receipts in PDF and paper preview.
- Proposed performance target: ordinary dashboard, availability, and record views complete within two seconds on staging under five concurrent representative users, excluding large uploads/print generation. Confirm VM resources and test data before treating this as a deployment acceptance target.

## 9. Resolved decisions and replaceable inputs

The ten business decision groups are resolved by Decisions.txt and incorporated above. No further client materials are required to begin implementation. See [Phase 0 decisions and assumptions](docs/phase-0-decisions.md) for defaults, placeholder schemas, and the initial screen map.

- Contract/legal text, agency identity/logo, and migration data: use fictional fixtures now, clearly labeled; the owner can substitute real materials later without changing application logic.
- Infrastructure: self-hosted Docker on a future VM; HTTPS required. Start with a planning assumption of 2 vCPU / 4 GB RAM and expandable persistent storage, then validate against the performance target. Domain and host credentials are only needed at deployment.
- Email: no SMTP dependency for V1; use audited Manager-assisted account recovery initially. Add email recovery later if required.
- Flux: begin with components available without paid credentials; do not commit to paid-only components unless the owner supplies licensed access.
- Retention: archive business history; Manager-only audited customer-document removal; a documented configurable retention policy, with no automatic regulatory deletion engine. Default upload limit is 10 MB per document/photo, configurable. No scheduled deletion by default.
- Backup targets are accepted, but the actual restore test remains a launch requirement. Synthetic import reconciliation demonstrates tooling, not reconciliation of the agency's real books.

## 10. Definition of done for each phase

A phase is complete when its checklist and acceptance gate pass, relevant web and API behavior is documented, translations and RTL/themes are covered for its screens, authorization/audit requirements are implemented, migrations are repeatable, and no unresolved defect compromises that phase’s core business rules. Record any deferred item explicitly with its destination phase; do not quietly expand or reduce release scope.
