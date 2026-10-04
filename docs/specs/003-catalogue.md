# Spec 003 · Catalogue seeded from scoping YAML + admin editing

Status: Done (merged in PR #19) · Phase: 1 · Owner: founder

## Goal
The trades, services and scoping questions in `docs/product/scoping/*.yaml` are loaded into the database and can be viewed and edited by admins in `/admin`, so the booking flow (Phase 2) and pro services (spec 008) have a real catalogue to use.

## User stories
- As the founder, I want the catalogue loaded from the YAML files with one command, so a fresh environment has the same starting content.
- As an admin, I want to edit trades, services and questions in the admin panel, so I can fix wording or add a service without a developer.
- As an admin, I want to switch a service off instead of deleting it, so past jobs keep their history.

## Acceptance criteria
**Seeding**
1. Given an empty database, when `php artisan db:seed --class=CatalogueSeeder` runs, then every trade, service and question in the four YAML files exists with its key, name/prompt, description, type, options, required flag, flags (`urgent_if`), `requires_registration`, `emergency_capable`, `safety_advice` and file order (`sort`).
2. Given the YAML is invalid (unknown question type, duplicate key, missing name, `urgent_if` value not in `options`), when the seeder runs, then it stops with a clear message naming the file and key, and writes nothing.
3. Given the catalogue was seeded and an admin edited it, when the seeder runs again, then it only **adds** trades, services and questions whose keys are new, and never changes or removes existing ones (the admin panel is the source of truth after the first seed).
4. The seeder runs as part of `php artisan migrate --seed` in local and staging, and on its own in production (it contains no personal data).

**Admin panel**
5. Given an admin with an editing role, then `/admin` shows **Catalogue → Trades** (name, key, status, number of services, active services) and each trade's **Services**, and each service's **Scoping questions** in order.
6. Given an editing admin, when they edit a trade name/status, a service's name, description, registration requirement, emergency flag, safety advice or active switch, or a question's prompt, type, options, required flag and `urgent_if` values, then the change is saved and shown.
7. Given an editing admin, when they add a service or question, then they must give a **key** (lowercase letters, digits, underscores; unique within its trade/service) which can never be changed afterwards.
8. Given any admin, then trades, services and questions **cannot be deleted** in the panel; services and trades are switched off with **Active** instead. (Questions can be removed from a service only while no job exists — there are none until Phase 2, so in this spec questions can be deleted.)
9. Given a question of type `single_choice` or `multi_choice`, then it must have at least two options; other types (`yes_no`, `number`, `text`) cannot have options; `urgent_if` values must be among the options (or `yes`/`no` for `yes_no`).
10. Questions can be reordered by drag and drop; services within a trade too.
11. Every create/update is written to the audit log (who, what, before/after, when).

**Access**
12. Given an admin role without catalogue permission, then they can view but not edit. *(See open question 2.)*
13. Customers and pros cannot reach any of this (the admin panel already refuses them).

## Screens / UX
Filament admin, desktop first (admins), usable on a tablet.
- **Catalogue → Trades** table; open a trade → its services table (with active toggle, order handle); open a service → form + **Scoping questions** list (order handle, type badge, required badge).
- Forms show the key as read-only after creation, with a hint "Keys never change; they link jobs and pros to this service."
- Empty state: "No trades yet — run the catalogue seeder."

## Rules and edge cases
- Keys are stable identifiers (`trade` key, `service` key unique within trade, question key unique within service) and are never renamed.
- `status: demo | live` on trades is stored and editable; what customers see for `demo` trades is decided in Phase 2.
- Options are stored as an ordered list; editing an option's text later will affect how past answers display — acceptable until Phase 2 (noted for the booking spec).

## Data changes
Update `docs/architecture/data-model.md`:
- `trades`: key (unique), name, status (demo/live), is_active, sort, timestamps.
- `services`: trade_id, key (unique per trade), name, description, requires_registration (nullable: pirb / electrical_registered_person / …), emergency_capable, **safety_advice jsonb** (new — present in the YAML but not yet in the data model), is_active, sort.
- `scoping_questions`: service_id, key (unique per service), prompt, type (enum), options jsonb, required, flags jsonb, sort.
- Enums: `TradeStatus`, `QuestionType`, `RegistrationType`.

## Security and privacy
- No personal data.
- Policies for Trade, Service, ScopingQuestion: view for any admin role; create/update for editing roles; delete denied (questions excepted, AC8).
- Permissions via `spatie/laravel-permission` (`catalogue.view`, `catalogue.edit`), granted to roles by a migration.
- Audit log on every change.

## Out of scope
- Anything customers see (trade tiles, booking flow) — Phase 2.
- Linking pros to services (spec 008) and suburbs (spec 004).
- Importing YAML changes into an already-edited catalogue beyond adding new keys.
- Translations of catalogue text (isiZulu is post-launch).

## Decisions (founder, 2026-10-04)
1. YAML seeds the first version; afterwards the admin panel is the source of truth and re-seeding only adds new keys.
2. Super-admin and support can edit the catalogue; vetting and finance can view only.
3. Trades and services are never deleted, only switched off.

## Progress
- 2026-10-04: Drafted for founder review.
- 2026-10-04: Approved as proposed.
- 2026-10-04: Built on `feat/003-catalogue`; tests in `tests/Feature/Catalogue/`. Reviewer agents found: editing a question reset its position, reordering wasn't audit-logged, and view-only admins could not open trades/services — all fixed with tests (read-only View pages added). New items are added at the end of their lists.
- Deviations: `symfony/yaml` became a production dependency so the seeder works on servers (decision 028); the services list shows "active" as an icon (edit the service to switch it) rather than an inline toggle; admins can also create new trades.
- Next: when Phase 2 stores job answers, question deletion must be blocked (policy note in `ScopingQuestionPolicy`).
