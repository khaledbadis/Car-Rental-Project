# Phase 7 — Expenses and management reporting

Implemented 4 October 2026. Production launch gates remain open. CI/CD foundation is still the next phase and has not been activated.

## Expenses

Manager/Finance can record vehicle expenses, upload private PDF/JPEG/PNG evidence and correct mistakes with a reasoned reversal. Agents cannot access expenses, reports or exports. API tokens must carry finance.manage as well as the user role permission. Amounts use exact integer centimes; inputs are decimal DZD strings. Expense entries cannot be updated or deleted at the PostgreSQL level. Reversals preserve original vehicle, category and incurred date, with negative amounts and a unique reference. They are corrections, not cash refunds. UUID retries serialize under the existing agency lock; changed actor/payload is rejected.

Each completed maintenance service creates one expense inside the maintenance transaction. Existing services are backfilled by migration, with a unique maintenance_record_id constraint. Reports sum expenses only, never expense plus maintenance cost. Linked services/evidence remain accessible from expense history. Reversing a linked expense does not rewrite the service history; the reporting cost is corrected. Separate, additional invoices can be entered manually; the form warns against re-entering automatically linked service costs. Expenses for archived vehicles are permitted for late invoices and historical entry.

## Reports

Shared Recognition is used by the dashboard and reports to prevent differing totals. Revenue uses agreed rental start dates; standalone reservation cancellation charges use original charge effective dates and are disclosed separately. Reversals restate the original revenue attribution. Drafts and imported opening debt do not generate revenue. Collections use payment/refund/reversal effective dates; deposits received, applied or refunded are not rental collections. Unallocated reservation amounts remain separately visible and included in overall totals. Vehicle filtering excludes unallocated amounts.

Reports include date/vehicle filters (maximum 366 inclusive days), revenue, net collections, expenses, contribution, rental counts, five most rented vehicles, expense category bars, per-vehicle performance and accessible daily totals. Counts use active/returned rentals by agreed start date. Contribution means revenue less recorded expenses, not accounting profit. All money responses are centimes.

Utilization uses actual handover through actual return (or now for active rentals), intersected with the selected period. Eligible hours end at now and exclude time before service entry/after archive and the union of maintenance/administrative blocks. Released blocks end at the earlier of scheduled end or release; cancelled-before-start blocks contribute zero. Normal cleaning/preparation remain eligible. Overlapping exclusions are merged. Overlapping rentals, rented/excluded overlap and rental time outside service dates flag inconsistent history and suppress the percentage. Missing service-entry dates, missing handover times relevant to the period and zero eligible hours also yield N/A, with explicit reasons. Historical service-entry dates should be completed before using fleet utilization comparisons.

## Export decision

CSV is the initial implementation default after asking the owner about formats; no explicit format answer had arrived at implementation time. It can be replaced/extended if the owner chooses XLSX. The per-vehicle CSV uses UTF-8 BOM, comma delimiters, stable English column names, decimal DZD money, explicit utilization status and blank unavailable numeric values. An unallocated row reconciles money totals. Text cells are protected against spreadsheet formula interpretation. CSV uses the last applied report filters and the same server-side authorization/calculation as the screen. This is not a formatted XLSX workbook or full expense-journal export.

## Verification

Full Docker/PostgreSQL suite passed: **101 tests, 633 assertions** before the final additional cancellation test. Final focused reporting run: **10 tests passed, 74 assertions**, including the added cancellation/unallocated/refund case. Existing rental/deposit/booking concurrency tests passed in the full suite. New cases cover expense idempotency and immutable storage, one maintenance expense on retry and migration backfill, corrections, overlap union, archive and current-time clipping, missing history, dashboard reconciliation, financial permissions, private evidence, CSV formula safety and Livewire/API/locale rendering.

Pint passed across 145 PHP files; Vite build and Blade compilation passed. The local development migration was applied. OpenAPI documents expense/report routes. Browser verification covered English light desktop/phone, Arabic RTL dark tablet and French dark laptop at 360/768/1024/1440 widths without persistent horizontal overflow. September demo report showed 8000.00 DZD revenue, 8000.00 DZD collections, one rental, and an honest N/A utilization for the vehicle without service-entry date. Original English/System demo preferences were restored. No expense or rental test records were added to the local operational database during browser checks.

## Remaining boundaries

The report implementation reads the agency's records in memory, consistent with the current small fleet; staging performance against actual history still requires measurement. Reports recalculate with later corrections; they are not frozen accounting statements. Physical-device upload and opening CSV in the owner's spreadsheet application remain acceptance checks. Real-data migration reconciliation, staff acceptance, VM setup, measured restore and agency cutover remain open. Phase 8 prepares deployment files and guidance only; the owner operates deployment.
