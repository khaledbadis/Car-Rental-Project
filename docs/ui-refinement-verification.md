# Qualicar Algerie UI refinement — 2026-10-05

Implemented native SVG branding/car icons, profile menu with persisted language/theme switching, themed scrollbars, two-column Preferences, full-width Team list and modals, operational notifications with per-user read state, and readable activity changes. API endpoints are documented in openapi.json and use the same application actions.

Delete account disables access and revokes sessions/tokens; it preserves historical references and can be reversed through Edit. Self-disable and last-manager protection remain enforced.

Notifications are current operational alerts, refreshed every 60 seconds. Marking read does not resolve the underlying issue. Unresolved alerts resurface each Africa/Algiers day; resolved alerts disappear. Read-state identity is independent of translated labels. This is in-app notification delivery, not email or push delivery.

Verification:
- Full PostgreSQL suite: 107 tests, 683 assertions passed.
- After final audit presentation corrections: focused suite 5 tests, 48 assertions passed, including populated audit records across FR/AR/EN.
- Pint: 153 files passed; Blade compilation and Vite production build passed.
- Local notification-read migration applied.
- Browser: desktop Team and Add modal, profile dropdown and theme persistence, two-column Preferences, Arabic mobile notifications and activity list. Mobile viewport had no horizontal overflow. Preferences restored to English/system and viewport reset.
- Browser testing found and corrected missing Team copy, grouped translation lookup returning arrays, and dotted audit action translation keys.

Deployment foundation remains the next planned phase. No VM provisioning or deployment activation performed.

## Vehicles list/card design — 2026-10-07

Applied the supplied images/dashboard-list-list.png and dashboard-list-card.png references to Vehicles only. List is the default on each visit; the toolbar switches immediately to cards without losing filters. Rows/cards display vehicle identity, category, mileage, daily rate and active/archive state (not live rental availability). Existing creation, permissions, links, search, filters and pagination are retained. Customers and Team await owner review before adopting this design.

Verification: 107 PostgreSQL tests / 685 assertions passed; Pint 153 files passed; production assets built. Browser checked both desktop views, live search, light/dark themes, and Arabic mobile layout (390px viewport, no horizontal overflow).

## Directory design rollout — 2026-10-08

Extended the approved Vehicles pattern to Customers, Team, Reservations, Rentals, Maintenance history, Expenses, and availability results/blocks. List is the default on each visit; the secondary card layout switches locally without a server request. Shared Blade components provide consistent view controls, record links, badges, focus styling and responsive layouts. Activity history uses separated full-width rows. The reservation calendar, reports/charts, profile screens and existing forms retain their purpose-specific layouts.

Business actions, permissions, filters, pagination, outstanding balances, warning messages, evidence links, account modals, block release and expense reversal controls are retained. No schema or API changes.

Verification:
- Full PostgreSQL suite: 107 tests / 685 assertions passed.
- Final availability/history adjustments: 30 focused tests / 217 assertions passed (ReservationTest, MaintenanceTest, ReportingTest). Maintenance and expenses tests render populated records in FR/AR/EN.
- Pint: 153 files passed. Production build passed. Git whitespace check passed.
- Browser: Customers list/cards, Team cards, reservation list and weekly-calendar switch, Rentals list, Maintenance and Expenses controls/empty states; shared layout checked in light/dark and Arabic at 390px (no horizontal overflow).
- Local maintenance/expense histories are empty; populated histories were verified by the existing automated suite without adding agency records.
