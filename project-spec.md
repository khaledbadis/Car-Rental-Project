# Car Rental Management — Project Specification

Status: Phases 1–4 implemented; Phase 5 application delivered with launch gates open; Phases 6–7 implemented (CSV default pending format preference); Phase 8 Dokploy rework planned, implementation pending · 8 October 2026

Sources: [Client brief](client-brief.md), [Client answers](QA.txt), [Resolved decisions](Decisions.txt), [CI/CD and Dokploy requirements](docs/cicd.md), and project owner's technical requirements. The owner's 20 September instruction authorizes fictional documents and migration samples in place of waiting for client materials; placeholders must remain identifiable and replaceable. The 8 October deployment decision replaces mandatory dedicated-VM deployment with GitHub Actions → GHCR → Dokploy, supporting shared or dedicated VPS targets. This document defines scope and implementation gates; it does not authorize implementing every phase at once.

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
| Record completed maintenance and upload evidence | Yes | No | No |
| Read maintenance operations / costs and evidence | Both | Operations only | Both |
| Record expenses/corrections and access management reports/export | Yes | No | Yes |
| Full financial reports and audit access | Yes | Limited operational view | Finance scope |

Use permissions rather than an approval workflow: an authorized person performs sensitive actions under their own account. Managers alone configure vehicle rates. For a fixed-amount agent discount, enforce the same 10% limit against the undiscounted base charge; repeated edits cannot accumulate beyond this limit. Never share manager credentials.

Audit sensitive actions with actor, entity, action, timestamp, before/after values where applicable, and reason: price/discount changes, reservation cancellation, refunds, reversals, deposit application, document deletion, document-expiry override, conflict resolution/reassignment, and permission changes. Audit logs cannot be edited through normal application operations. Do not copy document contents or credentials into logs.

## 4. Functional requirements

### Staff profiles — implemented in Phase 5

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

Dashboard implementation decision (24 September 2026): deliver the operational dashboard in Phase 5, after Phase 4 provides rental and ledger data. The primary view must show the agency situation, not just navigation cards. Use cards for fleet counts, today’s activity, debt and held deposits; an actionable urgency list for overdue returns, pickup delays, booking conflicts and expiring documents; a fleet-state chart; and separately labeled revenue and collections trends over a selected period. Display actual data only, distinguish zero values from unavailable data, respect role scope, and provide table/list alternatives to charts. Do not label deposits as revenue. Detailed profitability and maintenance/expense analytics remain Phase 7.

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

Use a **modular Laravel monolith** with PostgreSQL, Livewire, Flux UI, and Tailwind. Docker remains the development and production runtime. Production uses prebuilt images deployed through owner-managed Dokploy, either on the VPS hosting Dokploy or on a separate VPS managed remotely by Dokploy. A dedicated application VM is optional. Retain the installed compatible package/runtime versions and lockfiles during the deployment rework.

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

- Preserve Docker Compose services: application/PHP-FPM, internal Caddy web server, PostgreSQL 17, queue worker, and scheduler. Worker and scheduler reuse the exact application image. Keep the supported database-backed queue/cache; add Redis only if there is a demonstrated need.
- Dokploy manages public domains and HTTPS through Traefik, forwarding to Caddy over internal HTTP. Caddy forwards PHP requests to FPM. Remove Caddy's public TLS/host port bindings from the deployment configuration. PostgreSQL and FPM have no public ports; only the web service joins the required routing network and also remains connected to the private application network used by the backend services. Verify the final Compose networking generated by Dokploy.
- Keep root `compose.yaml`, `scripts/setup`, and normal local development independent of production changes. Staging and production have separate Dokploy resources, domains, stable Compose/volume identities, databases, storage, credentials and keys. Persistent database and private attachment volumes survive redeployment and application rollback.
- Inject runtime secrets through Dokploy or an equivalent protected secret store, scoped to the services that need them. Keep `APP_DEBUG=false`, HTTPS `APP_URL`, secure cookies and the existing `APP_KEY` across releases. Schema-owner credentials are available only to controlled migration/provisioning operations; ordinary app/worker/scheduler services receive restricted runtime credentials. No secrets enter source, images, release artifacts or logs.
- Retain login rate limiting, server-side role/token authorization, private authorized downloads and immutable audit/financial history. Verify trusted proxy headers, HTTPS links, client IP handling and Livewire uploads through Traefik → Caddy → FPM, restricting forwarded-header trust to the intended proxy topology.
- Queue suitable document/notification work; dashboard correctness and booking checks must not depend on a delayed job. Scheduler supports reminders and housekeeping.
- Back up PostgreSQL at least daily and attachments alongside it, quiescing synchronous/background writers for a consistent pair. Preserve checksums and protected output, encrypt/copy backups off-server, and alert on backup failure or age exceeding 24 hours. Accepted recovery targets remain RPO ≤24 hours and RTO ≤4 hours; launch requires a measured isolated restore of both database and files.
- Verify web/FPM, runtime database access, private storage, worker and scheduler readiness. `/up` alone is insufficient. Monitor external HTTPS, container restarts, disk capacity, application errors, failed jobs and backup freshness without exposing customer documents or credentials.

### 5.5 CI/CD architecture and release identity

**Local development → GitHub → GitHub Actions checks/build → GHCR → approved Dokploy deployment → selected VPS → readiness verification.**

| Component | Responsibility |
|---|---|
| Local Docker Compose | Development, local PostgreSQL checks and frontend builds using the existing workflow |
| GitHub / GitHub Actions | Reviewable source, isolated CI, production image builds, release metadata and gated deployment requests |
| GHCR | Versioned application/web images; production pulls the tested image pair without rebuilding source |
| Dokploy | Compose deployment, registry pull authentication, environment configuration, domains/TLS, deployment status and same-host/remote-server operation |
| Owner / operations | Infrastructure/accounts, protected secrets, production approval, migration execution mechanism, monitoring, backups, real restore/import and cutover |

- Use `.github/workflows/ci.yml` for pull requests and relevant pushes. Provision disposable PostgreSQL and isolated storage/credentials; run the existing test suite including concurrency coverage, Pint, frontend compilation and production-image checks. CI must have no path to development/staging/production databases. Untrusted PR jobs receive no registry write or deployment secrets and do not run on a production deployment host.
- Use `master` as the planned publish/release branch, following `docs/cicd.md`. The local `main` branch currently tracks `origin/master`; no branch rename is part of this plan. Apply the chosen branch consistently in workflows, protections, environments and documentation before activation.
- Publish the Dockerfile's `app` and `web` targets only after all required checks succeed for trusted release-branch code. Build the deployable images in CI once, then promote the same artifacts to staging/production. Tag with the full Git commit SHA; optional human-readable tags are aliases. Record both content digests and deploy by digest, because a SHA-named registry tag alone does not prevent replacement.
- Keep app/web digests, source SHA, image platform, matching Compose/configuration and release-operation scripts together as one release identity. Worker/scheduler use the same app digest. Pin deployment configuration to that release rather than reading a moving Git branch during promotion or recovery. Verify the target CPU architecture before choosing the image platform.
- Scope publication permissions to the publishing job (`contents: read`, `packages: write` with `GITHUB_TOKEN`); pin third-party actions to reviewed commits. Private Dokploy pulls use a dedicated package-read credential on the selected target, with required package access. Runtime secrets and migration credentials are separate from registry/deployment credentials.
- Trigger Dokploy only after successful CI/publication and the selected approval policy. Disable independent repository-push auto-deployment that could bypass the checks. Use a version-verified Compose deployment API or controlled manual promotion; an accepted deployment request is not evidence of readiness.
- Prepare automatic staging deployment and a production promotion job with manual approval available. Prefer protected GitHub environments with required reviewers where the repository plan supports them; otherwise require an operator-initiated promotion. Live deployment remains opt-in until owner configuration and rehearsal pass. Serialize releases per target in both automation and target-side operations; do not cancel an in-progress migration for a newer release.

### 5.6 Release procedure, rollback and recovery

Use a brief controlled maintenance window initially. Choose and rehearse a target-side release runner compatible with the installed Dokploy version; do not assume native Compose migration hooks or automatic rollback. The deployment job coordinates the operation through an owner-configured protected connection. Application container entrypoints must not migrate, regenerate keys or seed demo data.

1. Validate the release/configuration pair, registry access, secrets, storage capacity, target architecture and schema compatibility; retain the prior successful release identity and verify its images/configuration remain retrievable before migration.
2. Acquire the target deployment lock, pull the exact image digests, pause public writes with a gate that survives container replacement, drain/stop workers and stop the scheduler. Capture and verify a paired database/attachment backup before migrating an existing environment.
3. Run migrations exactly once from the candidate application image with temporary schema-owner credentials. Reconcile runtime grants/revokes after migrations and verify privileges. Separate first-install role creation from repeatable grant updates; ordinary services never inherit owner credentials.
4. Apply the matching Compose/configuration through Dokploy, start the matched app/web/worker/scheduler release, and initialize needed runtime caches/package discovery after secret injection. Respect persistent storage identities; deployment must not delete or recreate business volumes.
5. Wait for the corresponding Dokploy deployment outcome and verify HTTPS, database/storage readiness, login, assets, authorized downloads, queue execution and scheduler operation. Restore traffic only after success; record release identity, migration outcome, backup reference and verification result.

First installation has no existing business snapshot: initialize the isolated database/volumes, provision roles, migrate/reconcile grants, provision the first Manager privately, and validate before opening traffic. Preserve the key and volumes on subsequent releases. Never seed demos in production.

If backup, migration or readiness fails, stop promotion, report the actual stage and preserve a safe maintenance/service state. Adapt the existing backup script's unconditional resume behavior so it cannot reopen writes after a failed release. Retain the previous app/web digest pair and matching configuration for a tested **schema-compatible application rollback**. Rolling images back does not reverse migrations; never automatically run `migrate:rollback` or overwrite newer financial records with an old backup. Prefer backward-compatible migrations and forward fixes. Data recovery uses an isolated paired restore, post-backup transaction reconciliation and owner-approved traffic cutover.

Implementation references: [Dokploy Compose/storage](https://docs.dokploy.com/docs/core/docker-compose), [domains and routing](https://docs.dokploy.com/docs/core/docker-compose/domains), [remote servers](https://docs.dokploy.com/docs/core/remote-servers), [Compose deployment API](https://docs.dokploy.com/docs/api/reference-compose), [GHCR authentication/digests](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry), and [GitHub deployment environments](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments). Platform-specific API, approval and orchestration behavior must be verified against the owner's installed Dokploy version and repository capabilities during implementation.

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

Implementation completed 20 September 2026. See [Phase 1 verification](docs/phase-1-verification.md), [setup instructions](README.md), and [API contract](docs/openapi.json). Production hosting is owner-operated; launch gates remain tracked in Phase 5 and the CI/CD foundation is scheduled in Phase 8.

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

- [x] Implement reservation conversion and walk-in rentals with handover prerequisites.
- [x] Implement price snapshots, discounts, inspections, optional photos, and extensions.
- [x] Implement charges, advances, payments, deposits, refunds/reversals, and debt visibility.
- [x] Implement returns, preparation release, overdue detection, and affected-booking warnings.
- [x] Add equivalent API operations and retry protection.

Acceptance: expired driver licences block even a Manager; a 10% agent base discount succeeds and a larger one fails; additional charges remain undiscounted; complete a rental with advance payment, handover, deposit, damage charge, deposit application/refund, and return; financial totals reconcile. A rental can close with visible debt. Repeated submission produces one financial transaction. A conflicting extension fails without cancelling the next reservation. Missing required handover details block activation. Overdue vehicles remain unavailable after their scheduled end.

Implementation completed 25 September 2026. See [Phase 4 verification](docs/phase-4-verification.md). Agreement data snapshots/versions are implemented; printable contracts, numbered receipts, the operational dashboard and expanded staff preferences remain Phase 5.

### Phase 5 — First usable release and cutover

- [x] Implement a replaceable bilingual contract template with fictional agency content and physical signature areas; retain a clear sample marker until the owner substitutes final content.
- [x] Implement stable numbered receipts, contract snapshots/versions, and print layouts.
- [x] Replace the quick-access landing page with an operational agency dashboard: role-scoped KPI cards, urgent actions, pickups/returns, overdue/conflicting commitments, document alerts, debt and held-deposit summaries; add fleet-status and time-series revenue/collections charts, date filters, clear empty states and links to underlying records.
- [x] Rehearse fictional migration of vehicles, customers, active rentals, upcoming reservations, opening unpaid balances and remaining held deposits; see Phase 5 verification for rollback-only scope.
- [ ] Validate a real-data staging import with source mapping, duplicate handling, documents and final reconciliation.
- [x] Prepare the original production Docker images/Compose, Caddy HTTPS configuration, restricted database-role setup and paired backup/restore procedure; adapting these for Dokploy remains Phase 8.
- [ ] Configure isolated staging/production Dokploy targets and domains on the selected shared or dedicated VPS, configure monitoring/backups and complete measured restore; owner-operated launch work remains open.
- [x] Expand Preferences with editable full name, phone, email, address and profile picture; introduce immutable unique usernames and preserve audit attribution.
- [ ] Conduct staff acceptance sessions across roles, languages, themes, and screen sizes.
- [ ] Perform approved cutover with reconciliation, training, and rollback/recovery readiness.

Implementation notes: [Phase 5 verification](docs/phase-5-verification.md). Email-based login is retained; immutable usernames use staff-{ID}. Printable French/Arabic templates remain marked as samples. Infrastructure, real imports and staff acceptance are not complete.

Acceptance: staff complete the full everyday workflow without Excel; contracts/receipts print with correct Arabic shaping and no clipped fields; dashboard flags overdue and blocked vehicles; migrated active commitments block availability correctly; opening debt and deposits reconcile to agreed source totals; a backup restores both data and attachments. This is the first agency launch gate.

Owner sequencing decision (4 October 2026): continue Phases 6 and 7 while the explicit Phase 5 launch requirements remain open. This does not certify agency cutover or production readiness.

### Phase 6 — Detailed maintenance

- [x] Add service history, mileage/date reminders, costs, and attachments.
- [x] Link work records to existing maintenance blocks and release operations.

Acceptance: record an oil change and next due mileage/date; reminders reflect recorded mileage; maintenance blocking remains consistent with reservation checks.

Implementation completed 4 October 2026. See [Phase 6 verification](docs/phase-6-verification.md). Managers record completed services; all roles read operational history/reminders, while costs, notes and evidence require finance scope. Latest service date/ID per vehicle/type determines reminders; default warning thresholds are 30 days/500 km and are configurable. Historical readings cannot lower the vehicle odometer. Recording work does not automatically release a block. Maintenance cost is stored once on the service record; Phase 7 must link it without duplicating an expense.

### Phase 7 — Expenses and management reporting

- [x] Add vehicle expenses and link maintenance costs without duplication.
- [x] Implement the agreed utilization denominator and rental-start revenue attribution, alongside payment-date collections.
- [x] Add date/vehicle filters and an initial CSV export with documented format assumptions.
- [ ] Confirm the owner’s export format preference; CSV is implemented as the initial default, with XLSX deferred unless requested.

Acceptance: report totals reconcile to ledger/expense records; security deposits held are excluded from revenue; maintenance is counted once; utilization follows its documented denominator.

Implementation delivered 4 October 2026. See [Phase 7 verification](docs/phase-7-verification.md). Manager/Finance record expenses and reversals and access reports/evidence. Maintenance creates one linked expense transactionally, including a backfill of existing services; no double summation. Reports and dashboard share revenue/collection recognition. Utilization uses elapsed actual rental time, merged maintenance/admin exclusions and explicit N/A reasons. Contribution is not accounting profit. Initial CSV uses stable English column keys and decimal DZD amounts; export format preference remains open and does not authorize an unrequested XLSX implementation.

### Phase 8 — GitHub Actions, GHCR and Dokploy rework

Scheduled after Phase 7 on 4 October; revised to the [Dokploy requirements](docs/cicd.md) on 8 October 2026. Execute the following stages in order. The current deliverable is the rework plan/specification; implementation, registry publication and deployment remain pending. Reuse the existing Docker foundation and preserve application behavior, authorization, immutable history, database-role separation and backup guarantees. Application changes are limited to demonstrated deployment needs such as proxy/readiness configuration.

#### 8.0 — Record the revised scope

- [x] Replace mandatory dedicated-VM deployment with GitHub Actions → GHCR → Dokploy, supporting same-host and remote VPS targets.
- [x] Document release responsibilities, security/storage requirements, ordered rework stages and acceptance gates in this specification.
- [x] Retain the previously prepared [owner deployment guide](docs/deployment-guide.md); its direct-VM connection/main-branch assumptions require revision in 8.5.

#### 8.1 — Establish isolated CI

- [ ] Add `.github/workflows/ci.yml` for pull requests and relevant pushes, using disposable PostgreSQL and isolated credentials/storage.
- [ ] Reuse the checks in `scripts/check`: PostgreSQL tests (including concurrent booking/ledger operations), Pint and Vite compilation; add production `app`/`web` image build/smoke validation.
- [ ] Restrict job permissions, pin external actions, and ensure PRs cannot access deployment secrets or agency data. Configure required checks when owner repository access is available.

Acceptance: a clean CI run passes all required checks; a deliberately failing check prevents release publication/promotion; local setup/check commands remain usable.

#### 8.2 — Publish immutable release artifacts

- [ ] Reuse `docker/Dockerfile.production` targets; publish matched app/web images to configurable GHCR package names after successful trusted `master` checks.
- [ ] Record full SHA tags, content digests, target platform and matching Compose/configuration/scripts as a release artifact. Worker/scheduler use the identical app digest; production performs no source build.
- [ ] Define registry visibility, CI publishing permissions, target pull credentials and retention of previously deployed releases. Verify secret exclusions in `.dockerignore` and image contents.

Acceptance: both images identify the same source revision, run with the target platform/runtime, and can be promoted by digest without rebuilding. Failed/untrusted checks publish no production release.

#### 8.3 — Adapt runtime configuration for Dokploy

- [ ] Rework `deploy/compose.yaml` to require registry image references, preserve all five services/restart policies and add appropriate health checks. Remove production `build:` directives and implicit local/latest release defaults.
- [ ] Change `deploy/Caddyfile` to internal HTTP; remove public Caddy ports and certificate volumes. Configure Dokploy-managed domains/TLS to the internal web port, inspect generated networking, and keep PostgreSQL/FPM private.
- [ ] Give each environment stable project/volume identities and preserve database/attachments with correct ownership. Package fixed scripts/configuration in release artifacts/images or managed mounts; do not depend on a disposable Dokploy source checkout for persistent files.
- [ ] Update `deploy/.env.example` and `deploy/db.env.example` with safe image/environment placeholders and service-scoped secret wiring. Verify owner credentials cannot reach ordinary application services, keys survive releases, and staging/production share no business storage.
- [ ] Verify proxy trust, HTTPS links/cookies, Livewire uploads and runtime cache/package discovery; adjust `src/bootstrap/app.php` or `deploy/entrypoint.sh` only where verification demonstrates a deployment requirement.

Acceptance: a disposable stack boots from the release images without source builds; redeployment preserves files/data and keys; proxy behavior works; previewed/actual networking does not expose database/FPM or conflict with Dokploy's public ports.

#### 8.4 — Implement controlled releases and recovery

- [ ] Add a protected promotion workflow (planned `.github/workflows/deploy.yml`) and target-side release script/orchestration (planned `deploy/release.sh`) implementing section 5.6. Verify the installed Dokploy Compose API/provider and target execution mechanism before choosing commands; bind deployment configuration to the selected release SHA/digests.
- [ ] Support post-CI automatic staging deployment and production approval/manual promotion. Keep activation opt-in, disable competing repository-push auto-deploy, and serialize operations across workflow, manual deployment, backup and migration paths.
- [ ] Adapt `deploy/runtime-role.sql` for separate first-install bootstrap and repeatable post-migration privilege reconciliation, including verification of bootstrap-admin/owner/runtime privileges. Run one migrator with protected owner credentials.
- [ ] Adapt `deploy/backup.sh` for the Dokploy project/environment, persistent storage, shared operation lock and safe failure handling. Preserve consistent database/files pairs, checksums, encrypted off-server copies and backup freshness alerts.
- [ ] Implement deployment-status/readiness verification and failure reporting; document durable write pause, worker/scheduler restart, prior compatible release restoration, forward recovery and isolated data restoration.

Acceptance: rehearsals prove duplicate deployment/migration attempts serialize, failed backups/migrations/readiness prevent reopening writes, owner credentials stay confined, and the exact release/configuration pair can be restored when schema-compatible. A trigger response alone never marks a release successful.

#### 8.5 — Rehearse and complete the owner handoff

- [ ] Run local/disposable checks for CI isolation, image pairing, proxy/HTTPS behavior, persistent storage, permissions, one-off migration/grants, background processes and failure handling. Repeat relevant app tests/builds when implementation changes them.
- [ ] Revise `README.md`, `deploy/README.md` and `docs/deployment-guide.md` with tested GitHub/GHCR/Dokploy setup, branch policy, required variables/secrets, same-host/remote target steps, first installation, per-release operation and recovery. Reconcile historical hosting assumptions in `docs/phase-0-decisions.md` without changing settled business rules.
- [ ] Record what is implemented, locally verified, verified on staging and still pending; registry pulls, Dokploy routing and remote operation require their actual configured targets. Exercise staging deployment and an isolated paired restore once the owner supplies infrastructure.
- [ ] Owner configures repository permissions/protections, GHCR access, Dokploy/version/target connectivity, domains/DNS, persistent capacity, runtime/migration secrets, notification destination, backup retention/storage and production promotion policy.
- [ ] Owner completes measured RPO/RTO verification, real-data reconciliation, sample legal-content replacement, staff acceptance and approved production cutover before activating live automated releases.

Acceptance: the owner can follow the runbook for either supported target; demonstrated failures stop promotion and report recovery state; production readiness is claimed only after actual infrastructure/restore/launch gates pass. Preparing configuration alone does not complete external acceptance.

The deployment architecture in this specification and `docs/cicd.md` supersedes the older dedicated-VM/direct Compose deployment instructions until the runbooks are updated. No VM provisioning, secrets configuration, remote deployment or cutover is authorized by this planning task.

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
- Deployment checks for isolated CI, trusted publication, matched digest/configuration releases, Dokploy proxy routing, durable volumes, restricted runtime grants, serialized migration/backup operations, readiness failures and schema-compatible recovery; measure paired restore on an isolated target before launch.
- Proposed performance target: ordinary dashboard, availability, and record views complete within two seconds on staging under five concurrent representative users, excluding large uploads/print generation. Confirm VM resources and test data before treating this as a deployment acceptance target.

## 9. Resolved decisions and replaceable inputs

The ten business decision groups are resolved by Decisions.txt and incorporated above. No further client materials are required to begin implementation. See [Phase 0 decisions and assumptions](docs/phase-0-decisions.md) for defaults, placeholder schemas, and the initial screen map.

- Contract/legal text, agency identity/logo, and migration data: use fictional fixtures now, clearly labeled; the owner can substitute real materials later without changing application logic.
- Infrastructure: self-hosted Docker through Dokploy on a shared VPS or dedicated VPS, on the Dokploy host or a remotely managed deployment server; HTTPS is Dokploy/Traefik-managed. A dedicated VM is optional. The original 2 vCPU / 4 GB RAM estimate is an application planning baseline, not the capacity requirement for an entire shared Dokploy host; validate available capacity, co-hosted workloads and storage against the performance target. Owner supplies infrastructure/accounts/domains and protected secrets at deployment.
- Email: no SMTP dependency for V1; use audited Manager-assisted account recovery initially. Add email recovery later if required.
- Flux: begin with components available without paid credentials; do not commit to paid-only components unless the owner supplies licensed access.
- Retention: archive business history; Manager-only audited customer-document removal; a documented configurable retention policy, with no automatic regulatory deletion engine. Default upload limit is 10 MB per document/photo, configurable. No scheduled deletion by default.
- Backup targets are accepted, but the actual restore test remains a launch requirement. Synthetic import reconciliation demonstrates tooling, not reconciliation of the agency's real books.

## 10. Definition of done for each phase

A phase is complete when its checklist and acceptance gate pass, relevant web and API behavior is documented, translations and RTL/themes are covered for its screens, authorization/audit requirements are implemented, migrations are repeatable, and no unresolved defect compromises that phase’s core business rules. Record any deferred item explicitly with its destination phase; do not quietly expand or reduce release scope.

## UI refinement — October 2026

- [x] Qualicar Algerie branding and car icon.
- [x] Profile dropdown with identity, language/appearance switchers and logout.
- [x] Theme-aware scrollbars and responsive two-column Preferences.
- [x] Full-width Team list with create/edit modals and audited account deactivation, preserving history.
- [x] Role-scoped notifications for documents, late rentals/pickups, conflicts and maintenance; per-user daily read state and shared API.
- [x] Full-width activity list with translated actions and readable before/after changes.

Deployment foundation remains Phase 8; no infrastructure activation is included in this UI pass.

### Approved directory design rollout — 8 October 2026

- [x] Extend Vehicles list-first/card-secondary design to Customers, Team, Reservations, Rentals, Maintenance history, Expenses and Availability results/blocks.
- [x] Align Activity history with separated full-width rows.
- [x] Preserve existing business actions, access controls, filters, pagination and the reservation calendar.
- [x] Verify shared layouts in light/dark and Arabic mobile; retain French/Arabic/English text support.

See `docs/ui-refinement-verification.md` for validation evidence. This is a UI refinement, not a new implementation phase.
