# Decisions log (ADRs)

Short architecture decision records. **Add an entry for every significant choice** (new dependency, new provider, schema pattern, security trade-off). Template: `docs/templates/adr.md`. Never edit an accepted decision; supersede it with a new one.

| # | Decision | Status | Date |
|---|---|---|---|
| 001 | Laravel 13 modular monolith, PHP 8.4 | Accepted | 2026-10-03 |
| 002 | PostgreSQL + PostGIS as the only database | Accepted | 2026-10-03 |
| 003 | Filament 5 for admin and pro portals; Livewire 4 for customer and public UI | Accepted | 2026-10-03 |
| 004 | Phone + one-time code login for customers and pros; email + password + MFA for admins | Accepted | 2026-10-03 |
| 005 | Domain entity named `ServiceJob` to avoid clashing with Laravel queue `jobs` | Accepted | 2026-10-03 |
| 006 | Business logic in Action classes; thin controllers/components/resources | Accepted | 2026-10-03 |
| 007 | Money as integer cents + `brick/money`; append-only ledger | Accepted | 2026-10-03 |
| 008 | Third parties behind contracts with Fake implementations | Accepted | 2026-10-03 |
| 009 | Database queue driver in v1; Redis later | Accepted | 2026-10-03 |
| 010 | Scoping trees stored as data, seeded from YAML | Accepted | 2026-10-03 |
| 011 | ULID `public_id` in URLs; never expose numeric IDs | Accepted | 2026-10-03 |
| 012 | Payment provider | Open (Phase 3) | — |
| 013 | WhatsApp provider | Open (Phase 1) | — |
| 014 | Hosting provider and region | Open (Phase 0) | — |
| 015 | Error tracking: Sentry vs Nightwatch | Open (Phase 0) | — |

---

## 001 · Laravel 13 modular monolith
**Context:** Founder does not code; all code is written by AI agents. We need a framework with strong conventions, batteries included (auth, queues, scheduler, mail, storage, notifications) and a large hiring pool in South Africa.
**Decision:** One Laravel 13 app on PHP 8.4, organised into domain modules (`app/Domain/*`).
**Consequences:** Simple to deploy and reason about. Modules must keep clean boundaries so one could be extracted later. Laravel 13 receives security fixes until March 2028; plan the upgrade to the next major before then.

## 002 · PostgreSQL + PostGIS
**Context:** Money, quotes and job states need transactions and constraints; matching needs location queries; scoping answers vary per trade.
**Decision:** Postgres (with PostGIS and `jsonb`) for all data, including queue, cache and sessions in v1.
**Consequences:** One database to back up and secure. Portable to any cloud.

## 003 · Filament for internal and pro surfaces, Livewire for customers
**Context:** Admin and pro tools are CRUD-heavy forms and tables; the customer flow is a polished, mobile-first booking experience.
**Decision:** Two Filament panels (`admin`, `pro`); customer and public pages in Livewire + Blade + Tailwind.
**Consequences:** Admin and pro portals come mostly for free. Customer UI is custom but uses the same PHP stack (no separate JS app).

## 004 · Login methods
**Context:** Customers and pros live on WhatsApp; passwords cause support load. Admin accounts are high-value targets.
**Decision:** Phone + OTP (WhatsApp first, SMS fallback) for customers and pros; admins use email + password + Filament multi-factor auth.
**Consequences:** OTP sends cost money and need strict rate limits. Phone number changes need re-verification.

## 005 · `ServiceJob` naming
**Decision:** Code entity `ServiceJob` / table `service_jobs`; UI says "job".

## 006 · Actions
**Decision:** Each business operation is one Action class; entry points (Livewire, Filament, controllers, queued jobs) call Actions.
**Consequences:** Rules are testable in one place and reusable by a future API.

## 007 · Money
**Decision:** `bigint` cents, `brick/money` in code, append-only `ledger_entries`, idempotency keys on payments and payouts.

## 008 · Integrations behind contracts
**Decision:** `PaymentGateway`, `MessagingChannel`, `ScopingAssistant`, `Geocoder` interfaces in `app/Contracts`, implementations in `app/Integrations`, plus Fakes.
**Consequences:** Providers can change without touching domain code; tests never hit real services.

## 009 · Database queue
**Decision:** Laravel `database` queue on Postgres in v1. Revisit when sustained queue latency > 30 s or > 50 jobs/min.

## 010 · Scoping as data
**Decision:** YAML in `docs/product/scoping/` seeds the catalogue tables; admins edit in Filament.

## 011 · Public IDs
**Decision:** ULID `public_id` for every entity addressable by URL; route model binding uses it.
