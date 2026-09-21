# Phase 0 — Decisions and implementation assumptions

Recorded 20 September 2026. Business decisions come from [Decisions.txt](../Decisions.txt); requirements and phase gates live in [project-spec.md](../project-spec.md). The owner authorizes fictional documents and assumed spreadsheet content. This document completes the planning slice; it does not claim that application functionality has been implemented.

## Confirmed rules

- Agents use standard rates, discount the base charge by at most 10%, cancel with a reason, and create ordinary operational blocks. Managers configure fleet/rates and negotiate nonstandard prices. Finance handles refunds, reversals, and retained cancellation charges.
- Daily quantity rounds up by 24-hour periods, minimum one. Weeks are seven days; months are thirty. Select one pricing basis. Discounts never apply to additional charges.
- Customer ID and licence scans and required fields must exist at handover. Expired customer documents block handover; an expired licence has no Manager exception.
- Known mandatory vehicle-document expiry before return prevents confirmation unless a Manager records a booking-specific reason. Revalidate at handover and when dates/vehicle change.
- Missed pickup is an actionable status, with stronger highlighting after two hours. Staff must explicitly continue, reschedule, or cancel; no automatic release.
- Refund and reversal are distinct ledger events. Receipts use annual REC and REF sequences; deposits remain a separate balance within the same receipt system.
- Contracts are printed for physical signatures. No e-signatures or SMTP dependency in V1.
- Imports cover vehicles, customers, upcoming reservations, active rentals, debt, and deposits still held. Migrate the remaining deposit, not just the original receipt amount.
- Business history is archived. Only Managers can remove customer attachments, with audit history. Upload defaults are 10 MB per file.
- Backups include database and attachments at least daily to separate storage. RPO ≤24 hours; RTO ≤4 hours; prove restore before launch.
- Utilization excludes maintenance/administrative blocks and out-of-service periods, but includes normal preparation and idle time. Revenue is attributed to rental start; collections to payment date.

## Implementation assumptions

These are adjustable engineering defaults, not extra client claims.

| Topic | Initial choice |
|---|---|
| Expiry dates | Valid through local end of expiry date; evaluate exact rental timestamps against the following local midnight |
| Weekly/monthly partial periods | Standard mode requires whole selected periods; otherwise switch to Daily or Manager-negotiated total |
| Money | Exact two-decimal DZD values; half-up rounding; suppress trailing zero decimals in ordinary display |
| Agent fixed discount limit | Compare against 10% of undiscounted base; recheck on every price/date change |
| Ordinary blocks | Agents manage ordinary operational blocks; maintenance administration remains Manager-only initially |
| Return preparation | Two hours from actual return, using the booking's saved buffer; new conflicts require staff action |
| Overdue vehicle | No new confirmation while return timing is unresolved; existing commitments remain visible |
| Retention | No automatic deletion; configurable documented policy and Manager-controlled document removal |
| File types | Documents: PDF, JPEG, PNG; inspection photos: JPEG/PNG initially; explain unsupported formats clearly |
| Account recovery | Manager resets credentials through an audited operation with forced change; initial Manager provisioned via a secured deployment command |
| Infrastructure planning | 2 vCPU / 4 GB RAM starting estimate, expandable persistent disk; verify under representative load |
| Storage | Private persistent files behind a storage abstraction; estimate capacity from actual upload volumes before deployment |
| Queue/cache | Start with supported database-backed drivers; dedicated worker and scheduler processes |
| UI components | Use Flux components available without paid credentials, adding accessible Tailwind/Livewire compositions as needed |
| Cancellation revenue | Standalone cancellation charge attributed to cancellation date and labeled separately |
| Export | CSV initially for later operational reporting; no export dependency on a spreadsheet application |

## Initial screen map and design conventions

| Area | Screens / primary actions | Phase |
|---|---|---|
| Access and shell | Sign in, profile/preferences, staff/roles, settings | 1 |
| Fleet | List/filter, profile, rates, documents, history | 2 |
| Customers | Search/list, individual/company profile, driver, documents, notes | 2 |
| Reservations | Availability search, tentative/confirmed booking, calendar/list, cancellation, reassignment, blocks | 3 |
| Rentals | Booking conversion/walk-in, terms, handover checklist, inspection, extension, return | 4 |
| Finance | Charges, payment/deposit history, balance, refund/reversal actions | 4 |
| Operations | Dashboard, overdue/pickup alerts, print contracts/receipts, imports | 5 |
| Maintenance | Service history, due reminders, costs | 6 |
| Reporting | Expenses, utilization, revenue/collections and vehicle comparisons | 7 |

Use a desktop sidebar and a compact mobile navigation drawer. On phones, replace dense operational tables with cards or prioritized columns; allow contained scrolling only for intrinsically wide content. Keep primary actions visible and dialogs within the viewport. Calendar views need a mobile agenda alternative.

French is the initial locale. Persist language and theme per user. Apply Arabic RTL using logical spacing/direction utilities; isolate registration numbers, phone numbers, identifiers, and monetary values when necessary to avoid mixed-direction confusion. Use semantic color tokens, readable focus states, and text/icon status labels so color is never the only signal. Validate at 360/768/1024/1440 px and by continuous resizing. Translate empty states, errors, confirmations, and accessibility labels as well as navigation.

## Fictional document plan

Build these when the document phase is reached, without waiting for real materials:

- Agency identity: `Agence Démo Location`, fictional address/contact identifiers, generic text logo. Store these in editable agency settings.
- Bilingual contract: editable French/Arabic template with agency, renter/company, actual driver, vehicle, dates, rate/basis, charges, deposit, mileage/fuel/condition references, and physical signature areas. Use short sample terms marked as demonstration content, not purported client-approved legal wording.
- Receipt: transaction identity/date, agency, customer, reservation/rental reference, amount/method, transaction type, actor, and remaining balance as applicable. Refund/reversal output identifies the related original transaction.
- Contract number assumption: annual `CTR-YYYY-000001`, replaceable if the owner supplies another convention. Preserve issued numbers and template versions when later changing templates.
- Persist document snapshots so replacing sample wording does not silently rewrite already-issued documents. Demo outputs must visibly say SAMPLE / EXEMPLE / نموذج; demo fixtures must not populate real production records automatically.

## Assumed migration inputs

Accept cleaned tabular data through a mapping layer. Implement synthetic fixtures with the importer phase; there is no need to construct or wait for real Excel files now. Identifiers below are legacy references, not database primary keys.

| Dataset | Assumed columns |
|---|---|
| Vehicles | legacy_vehicle_id, registration, make, model, year, category, fuel_type, transmission, mileage_km, daily_rate, weekly_rate, monthly_rate, entered_service_at |
| Customers | legacy_customer_id, type, full_name/company_name, birth_date, phone, address, contact_person, tax_id, identity_type, identity_number, identity_issue_date, identity_expiry_date, licence_number, licence_issue_date, licence_expiry_date, licence_country |
| Drivers | legacy_driver_id, legacy_customer_id/company link, personal/document fields matching individual customers |
| Reservations | legacy_reservation_id, legacy_customer_id, legacy_driver_id, legacy_vehicle_id, pickup_at, return_at, status, pricing_basis, quantity, rate, discount, agreed_total |
| Active rentals | legacy_rental_id, legacy_reservation_id if any, customer/driver/vehicle references, started_at, expected_return_at, starting_mileage, starting_fuel, condition_notes, agreed_base, charges_total, paid_to_date |
| Opening debts | legacy_balance_id, customer reference, rental reference if available, amount_due, effective_at, note |
| Held deposits | legacy_deposit_id, customer/rental reference, remaining_held_amount, effective_at, original_amount if known |
| Attachments | owner_type, legacy_owner_id, document_type, file_reference, issue_date, expiry_date |

Dates use ISO format; money uses decimal values without formatted currency text. Import timestamps are explicitly interpreted in Africa/Algiers unless an offset is supplied. Normalize identifiers and flag collisions; never merge on phone alone. Missing scans are listed for staff completion; importer must not fabricate real identity evidence. Legacy active rentals with incomplete historical handover data are marked as imported exceptions, not fabricated successful handovers. New handovers still enforce all eligibility rules.

For financial migration, a debt linked to an imported active rental is reconciliation input for that rental, not a second receivable. Opening deposits are explicit opening held balances, not fictional new cash receipts. If original transaction history is unavailable, preserve the opening entry and source provenance without inventing payment methods, receipt numbers, or original staff actors.

Synthetic reconciliation scenario:

- Two vehicles, two customers, one active rental and one non-overlapping future reservation.
- Active rental charges 32,000 DA; previously paid 20,000 DA; linked debt 12,000 DA. Import yields 12,000 DA outstanding once.
- Original deposit 30,000 DA with 5,000 DA previously applied; remaining held 25,000 DA. Import opening deposit balance is 25,000 DA, with no new cash collection.
- Include separate invalid rows for duplicate registration, unknown customer, overlapping reservation, and negative held balance. Report these clearly without partial financial corruption.
- Repeating the accepted batch does not create duplicates. Dry-run makes no business-data writes.

## Phase boundary

Phase 0 planning is complete using the owner's authorized assumptions. Phase 1 is the next implementation slice: Docker/Laravel foundation, access control, shared action/API conventions, audit foundation, and responsive multilingual/theme shell. Real infrastructure credentials, agency legal text, and Excel exports are not prerequisites for that slice.
