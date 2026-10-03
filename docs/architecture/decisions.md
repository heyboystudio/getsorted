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
| 016 | Local foundation tools and rootless Docker | Accepted | 2026-10-03 |
| 017 | Official Livewire scaffold with closed account routes | Accepted | 2026-10-03 |

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

## 016 · Local foundation tools and rootless Docker
**Context:** The initial Zorin workstation lacked PHP, Composer and Docker, and supplied Node 18. Phase 0 requires PHP 8.4, Node LTS and PostgreSQL 17 with PostGIS. The founder authorized local installation and enters administrator credentials directly in a desktop terminal.

**Decision:** Install PHP 8.4 CLI and required extensions from [Ondřej Surý's Ubuntu PHP PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php), using its maintained noble packages. Use the official Composer installer with its SHA-384 verification and official Node 24 LTS binaries with SHA-256 verification. Install Docker from its signed apt repository and use its rootless user context for development. Verify PostgreSQL/PostGIS in a disposable `postgis/postgis:17-3.5-alpine` container from the PostGIS project, with no networking or published ports. GitHub CLI uses browser authentication and the desktop keyring.

**Alternatives:** The downloaded Herd Lite PHP 8.4 binary reported 8.4.1 and was not installed; the PPA supplied 8.4.26. Native PostgreSQL is unnecessary when Docker can supply the required database. Rootless Docker avoids adding the user to the root-equivalent Docker group.

**Consequences:** The local OS trusts the added PHP and Docker package repositories. Docker's Ubuntu packages are used on Zorin's noble base; upstream does not officially support Ubuntu derivatives. Tool patch versions must stay updated. Composer/Node/GitHub CLI are user-local; new shells prepend `~/.local/bin`. Rootless Docker starts with the user's session. The system Docker service installed by the package also remains present; development checks use the rootless context. No application packages are introduced in this step, so Laravel/Filament/Livewire package compatibility checks remain part of subsequent steps.

**Sources and maintenance:** [PHP supported versions](https://www.php.net/supported-versions.php), [Composer download/verification](https://getcomposer.org/download/), [Node releases](https://nodejs.org/en/download), [Docker installation](https://docs.docker.com/engine/install/ubuntu/), [rootless Docker](https://docs.docker.com/engine/security/rootless/), [PostGIS image](https://github.com/postgis/docker-postgis). These are actively maintained language/runtime or project distribution channels, checked during installation. PHP uses the PHP License, Composer and Node MIT, Docker Engine Apache-2.0, PostgreSQL the PostgreSQL License, and PostGIS GPL-2.0-or-later; bundled operating-system packages retain their respective licenses.

## 017 · Official Livewire scaffold with closed account routes
**Context:** Step 2 needs Laravel 13, Livewire 4 and Pest on PHP 8.4. The existing architecture excludes starter email/password accounts from the customer experience.

**Decision:** Adapt the official [Livewire starter](https://github.com/laravel/livewire-starter-kit) at commit `0f62a26c4e4b401c1300930f47d72a497add8cce`. Keep Laravel, Livewire, free Flux and Tailwind; close the account surface until the dedicated authentication tasks. Use Pest 4 rather than upstream PHPUnit syntax. Use Vite directly for local asset builds, with no remote font dependency. Existing Sortd rules and documentation take precedence over generated defaults.

**Compatibility evidence before installation:** Upstream Composer dry-run on PHP 8.4.26 resolves Laravel 13.34.0, Livewire 4.4.7 and Flux 2.20.1. Packagist metadata for Pest Laravel plugin 4.1.0 permits Laravel `^13.0` and Pest `^4.4.1`; Pest 4.7 permits PHP `^8.3`. npm metadata for Vite 8 and Laravel Vite plugin 3.1 permits Node 24. Composer resolution and audits will verify the final lock. Filament is not installed until step 7; the planned Filament 5 requirement for Livewire 4 is retained.

**Dependencies and alternatives:** Unused starter packages (Fortify, Chisel, Pail, Pao, Sail, Larastan) were removed before locking; Larastan returns with the quality tools in step 5. Retained direct packages, from the resolved lock: Laravel framework 13.34.0, Tinker 3.0.2, Livewire 4.4.7, Blaze 1.0.19, Pint 1.32.1, Pest 4.7.8, Pest Laravel plugin 4.1.0, Collision 8.9.5, Faker 1.24.1 (all MIT), Mockery 1.6.15 (BSD-3-Clause) and **Flux 2.20.1 (free edition, proprietary licence)**. Flux is currently only imported by `resources/css/app.css`; no view uses it yet. The founder approved keeping Flux despite its proprietary licence (2026-10-03). Pest replaces direct PHPUnit (BSD-3-Clause), which remains transitive. Tailwind, its Vite plugin, Vite, Laravel Vite plugin and concurrently (MIT) provide the build toolchain. Starting from Laravel's bare skeleton was rejected because the roadmap explicitly selects the official Livewire kit. The paid Flux Pro edition is not used.

**Consequences:** Scaffold defaults do not activate any customer/admin authentication. User schema and factory remain bootstrap infrastructure, not a completed accounts feature. Quality tools already supplied by the starter may be used for validation; the full configured quality gate still belongs to step 5.
