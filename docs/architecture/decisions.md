# Decisions log (ADRs)

Short architecture decision records. **Add an entry for every significant choice** (new dependency, new provider, schema pattern, security trade-off). Template: `docs/templates/adr.md`. Never edit an accepted decision; supersede it with a new one.

| # | Decision | Status | Date |
|---|---|---|---|
| 001 | Laravel 13 modular monolith, PHP 8.4 | Accepted | 2026-10-03 |
| 002 | PostgreSQL + PostGIS as the only database | Accepted | 2026-10-03 |
| 003 | Filament 5 for admin and pro portals; Livewire 4 for customer and public UI | Accepted | 2026-10-03 |
| 004 | Phone + one-time code login for customers and pros; email + password + MFA for admins | Superseded for customers and pros by 039 | 2026-10-03 |
| 005 | Domain entity named `ServiceJob` to avoid clashing with Laravel queue `jobs` | Accepted | 2026-10-03 |
| 006 | Business logic in Action classes; thin controllers/components/resources | Accepted | 2026-10-03 |
| 007 | Money as integer cents + `brick/money`; append-only ledger | Accepted | 2026-10-03 |
| 008 | Third parties behind contracts with Fake implementations | Accepted | 2026-10-03 |
| 009 | Database queue driver in v1; Redis later | Accepted | 2026-10-03 |
| 010 | Scoping trees stored as data, seeded from YAML | Superseded by 051 | 2026-10-03 |
| 011 | ULID `public_id` in URLs; never expose numeric IDs | Accepted | 2026-10-03 |
| 012 | Payment provider | Open (Phase M; decision 058) | — |
| 013 | WhatsApp provider | Superseded by 040 (Twilio) | — |
| 014 | Hosting: a South African provider; deployment deferred | Accepted (provider TBD) | 2026-10-03 |
| 015 | Error tracking: Sentry (EU data region) | Accepted | 2026-10-03 |
| 016 | Local foundation tools and rootless Docker | Accepted | 2026-10-03 |
| 017 | Official Livewire scaffold with closed account routes | Accepted | 2026-10-03 |
| 018 | Laravel Boost for AI guidelines and MCP | Accepted | 2026-10-03 |
| 019 | Quality gate: Pint, Larastan, Pest arch tests, Rector | Accepted | 2026-10-03 |
| 020 | CI workflow paused; free GitHub plan without branch protection | Accepted (temporary); billing fix on hold by founder 2026-10-08 | 2026-10-03 |
| 021 | Filament 5 admin panel with mandatory MFA; pro panel locked | Accepted; MFA made optional by 047 | 2026-10-03 |
| 022 | Roles via spatie/laravel-permission; super-admin by console command | Accepted | 2026-10-03 |
| 023 | Supporting packages: audit log, settings, media, money, phone, PostGIS | Accepted | 2026-10-03 |
| 024 | Integration contracts and Fakes; no fallback to Fakes outside local/testing | Accepted | 2026-10-03 |
| 025 | Security hardening baseline (headers, HTTPS, sessions, rate limits, strict models) | Accepted | 2026-10-03 |
| 026 | Phone + OTP login implementation (spec 001) | Accepted | 2026-10-04 |
| 027 | One account for customer and pro; pro sign-up (spec 011) | Accepted | 2026-10-04 |
| 028 | Catalogue: YAML seeds, admin panel is the source of truth (spec 003) | Superseded by 051 | 2026-10-04 |
| 029 | Suburbs and properties; location = suburb centre for now (spec 004) | Superseded by 051 | 2026-10-04 |
| 030 | Booking flow and job state machine (spec 005) | Accepted | 2026-10-04 |
| 031 | Job photos and iPhone HEIC (spec 012) | Accepted | 2026-10-04 |
| 032 | Coverage guard before booking and waitlist (spec 006) | Changed by 044 (open mode) and 051 | 2026-10-04 |
| 033 | AI scoping assistant: Laravel AI SDK, Anthropic, shipped switched off (spec 007) | Provider superseded by 043, then 049 | 2026-10-04 |
| 034 | Pro application and vetting (spec 008) | Accepted | 2026-10-04 |
| 035 | Daily login-code cap off on local machines (temporary) | Accepted | 2026-10-04 |
| 036 | Quotes, comparison and acceptance (spec 010) | Accepted | 2026-10-04 |
| 037 | Private test site on a temporary AWS server ("preview" mode) | Accepted | 2026-10-04 |
| 038 | Email through Resend | Accepted | 2026-10-04 |
| 039 | Email or Google sign-in, then verified email and mobile (supersedes 004 for customers and pros) | Accepted | 2026-10-05 |
| 040 | Twilio for WhatsApp and SMS (Q5) | Accepted | 2026-10-05 |
| 041 | Test site: mobile saved without a code while SMS is blocked | Accepted | 2026-10-05 |
| 042 | Test site without a password | Accepted (founder) | 2026-10-05 |
| 043 | Amazon Bedrock (EU) for the AI assistant (spec 016) | Superseded by 049 | 2026-10-05 |
| 044 | Take requests from all of Durban before pros are signed up | Accepted (founder) | 2026-10-05 |
| 045 | Booking follows Kandua: one Siya thread (spec 017) | Quote and wave rules superseded by 051 | 2026-10-05 |
| 046 | Test site moves to Cape Town on usesorted.co.za | Accepted (founder) | 2026-10-05 |
| 047 | Admin MFA optional (changes 021 and the security baseline) | Accepted; confirmed for real data 2026-10-08 | 2026-10-05 |
| 048 | Home page redesign and the name "Get Sorted" | Accepted (founder) | 2026-10-06 |
| 049 | Google Gemini API for the AI assistant (supersedes the provider in 043) | Accepted (founder) | 2026-10-06 |
| 050 | Siya understands the conversation before booking | Accepted (founder; spec 019) | 2026-10-06 |
| 051 | Trades, extracted job facts and distance matching (supersedes the service/suburb model) | Accepted (founder; spec 020) | 2026-10-06 |
| 052 | The app and the database share one clock: Africa/Johannesburg (SAST) | Accepted (founder) | 2026-10-07 |
| 053 | Pin a patched `shell-quote` for the dev tool `concurrently` | Accepted (founder) | 2026-10-07 |
| 054 | Web push with `laravel-notification-channels/webpush` (spec 022) | Accepted (founder) | 2026-10-07 |
| 055 | Home page v3: its own layout, self-hosted fonts and GSAP motion | Accepted (founder) | 2026-10-07 |
| 056 | Siya is for signed-in users only; auth pages use the home v3 look | Accepted (founder) | 2026-10-07 |
| 057 | One name everywhere: GetSorted | Accepted (founder) | 2026-10-07 |
| 058 | MVP launches on model B (quotes, then hand-off); payments after launch | Accepted (founder) | 2026-10-08 |
| 059 | "Start a job" means sign in, then Siya | Accepted (founder) | 2026-10-08 |

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

## 014 · Hosting: a South African provider; deployment deferred
**Context:** Roadmap step 12. Requirements (`tech-stack.md`): PHP 8.4 or Docker with separate web/queue/scheduler processes, managed Postgres with PostGIS, daily backups and point-in-time recovery, encryption at rest, TLS, staging and production separated; an SA region preferred for latency and POPIA. Options compared: Laravel Forge + AWS Cape Town, Laravel Cloud (no Africa region; nearest Frankfurt/Ireland), a local SA hosting provider.

**Decision:** The founder chose **hosting with a South African provider** (option C) and to **keep developing locally for now**. The specific provider is chosen when the first hosted environment is needed (expected early Phase 1, when real OTP messages need a public URL). Before choosing, compare SA providers against the requirements above; if none offers managed Postgres with PostGIS and point-in-time recovery, bring the fallback (self-managed Postgres with WAL archiving/pgBackRest and tested restores) back to the founder as a decision.

**Consequences:** Phase 0's "staging live" exit criterion stays open until then. Data stays in South Africa, which simplifies the POPIA position. HTTP→HTTPS redirect and trusted proxy settings (decision 025) are configured with the provider.

## 015 · Error tracking: Sentry (EU data region)
**Context:** Roadmap step 12. Options: Sentry (free developer plan, Team ~$26/month; EU data residency; built-in PII scrubbing) or Laravel Nightwatch (free tier 300k events, Pro $20/month; data location not confirmed).

**Decision:** The founder chose **Sentry**, starting on the free plan with the **EU data region**. `sentry/sentry-laravel` is installed (after a compatibility check) together with the first hosted environment; it is not needed locally. Configure it to send no request bodies, cookies or user PII (`send_default_pii=false`) and to scrub phone numbers, emails and addresses.

**Consequences:** Error data leaves South Africa (EU), so Sentry is listed as an operator in the POPIA checklist and privacy notice, with the cross-border basis documented.

## 016 · Local foundation tools and rootless Docker
**Context:** The initial Zorin workstation lacked PHP, Composer and Docker, and supplied Node 18. Phase 0 requires PHP 8.4, Node LTS and PostgreSQL 17 with PostGIS. The founder authorized local installation and enters administrator credentials directly in a desktop terminal.

**Decision:** Install PHP 8.4 CLI and required extensions from [Ondřej Surý's Ubuntu PHP PPA](https://launchpad.net/~ondrej/+archive/ubuntu/php), using its maintained noble packages. Use the official Composer installer with its SHA-384 verification and official Node 24 LTS binaries with SHA-256 verification. Install Docker from its signed apt repository and use its rootless user context for development. Verify PostgreSQL/PostGIS in a disposable `postgis/postgis:17-3.5-alpine` container from the PostGIS project, with no networking or published ports. GitHub CLI uses browser authentication and the desktop keyring.

**Alternatives:** The downloaded Herd Lite PHP 8.4 binary reported 8.4.1 and was not installed; the PPA supplied 8.4.26. Native PostgreSQL is unnecessary when Docker can supply the required database. Rootless Docker avoids adding the user to the root-equivalent Docker group.

**Consequences:** The local OS trusts the added PHP and Docker package repositories. Docker's Ubuntu packages are used on Zorin's noble base; upstream does not officially support Ubuntu derivatives. Tool patch versions must stay updated. Composer/Node/GitHub CLI are user-local; new shells prepend `~/.local/bin`. Rootless Docker starts with the user's session. The system Docker service installed by the package also remains present; development checks use the rootless context. No application packages are introduced in this step, so Laravel/Filament/Livewire package compatibility checks remain part of subsequent steps.

**Sources and maintenance:** [PHP supported versions](https://www.php.net/supported-versions.php), [Composer download/verification](https://getcomposer.org/download/), [Node releases](https://nodejs.org/en/download), [Docker installation](https://docs.docker.com/engine/install/ubuntu/), [rootless Docker](https://docs.docker.com/engine/security/rootless/), [PostGIS image](https://github.com/postgis/docker-postgis). These are actively maintained language/runtime or project distribution channels, checked during installation. PHP uses the PHP License, Composer and Node MIT, Docker Engine Apache-2.0, PostgreSQL the PostgreSQL License, and PostGIS GPL-2.0-or-later; bundled operating-system packages retain their respective licenses.

## 017 · Official Livewire scaffold with closed account routes
**Context:** Step 2 needs Laravel 13, Livewire 4 and Pest on PHP 8.4. The existing architecture excludes starter email/password accounts from the customer experience.

**Decision:** Adapt the official [Livewire starter](https://github.com/laravel/livewire-starter-kit) at commit `0f62a26c4e4b401c1300930f47d72a497add8cce`. Keep Laravel, Livewire, free Flux and Tailwind; close the account surface until the dedicated authentication tasks. Use Pest 4 rather than upstream PHPUnit syntax. Use Vite directly for local asset builds, with no remote font dependency. Existing Get Sorted rules and documentation take precedence over generated defaults.

**Compatibility evidence before installation:** Upstream Composer dry-run on PHP 8.4.26 resolves Laravel 13.34.0, Livewire 4.4.7 and Flux 2.20.1. Packagist metadata for Pest Laravel plugin 4.1.0 permits Laravel `^13.0` and Pest `^4.4.1`; Pest 4.7 permits PHP `^8.3`. npm metadata for Vite 8 and Laravel Vite plugin 3.1 permits Node 24. Composer resolution and audits will verify the final lock. Filament is not installed until step 7; the planned Filament 5 requirement for Livewire 4 is retained.

**Dependencies and alternatives:** Unused starter packages (Fortify, Chisel, Pail, Pao, Sail, Larastan) were removed before locking; Larastan returns with the quality tools in step 5. Retained direct packages, from the resolved lock: Laravel framework 13.34.0, Tinker 3.0.2, Livewire 4.4.7, Blaze 1.0.19, Pint 1.32.1, Pest 4.7.8, Pest Laravel plugin 4.1.0, Collision 8.9.5, Faker 1.24.1 (all MIT), Mockery 1.6.15 (BSD-3-Clause) and **Flux 2.20.1 (free edition, proprietary licence)**. Flux is currently only imported by `resources/css/app.css`; no view uses it yet. The founder approved keeping Flux despite its proprietary licence (2026-10-03). Pest replaces direct PHPUnit (BSD-3-Clause), which remains transitive. Tailwind, its Vite plugin, Vite, Laravel Vite plugin and concurrently (MIT) provide the build toolchain. Starting from Laravel's bare skeleton was rejected because the roadmap explicitly selects the official Livewire kit. The paid Flux Pro edition is not used.

**Consequences:** Scaffold defaults do not activate any customer/admin authentication. *(2026-10-04: `/login` and `/logout` are now Get Sorted's own phone login, spec 001 / decision 026; the starter's email/password routes stay closed.)* User schema and factory remain bootstrap infrastructure, not a completed accounts feature. Quality tools already supplied by the starter may be used for validation; the full configured quality gate still belongs to step 5.

## 018 · Laravel Boost for AI guidelines and MCP
**Context:** Roadmap step 4. Claude Code (and Codex, which the founder has also used) need the Get Sorted rules plus version-accurate Laravel guidance, and a way to search docs and inspect the app.

**Decision:** Install `laravel/boost` ^2.10 as a **dev-only** dependency (2.10.1, MIT; requires PHP ^8.2 and Illuminate ^13.0 among others, so it supports Laravel 13). Configure it for Claude Code and Codex: guidelines (`CLAUDE.md`, `AGENTS.md`), skills (`.claude/skills`, `.agents/skills`) and the `laravel-boost` MCP server (`.mcp.json`, `.codex/config.toml`). All generated files are committed so cloud sessions have them. Our `.ai/guidelines/*.md` are the source for the Get Sorted section; a precedence line in `sortd-project.md` makes Get Sorted rules win over generic Boost guidance. `config/boost.php` excludes the `deployments` guideline and `boost.json` disables Cloud, because both recommend Laravel Cloud while hosting is still open (decision 014).

**Transitive dependencies (all MIT):** `laravel/mcp` 1.0.1, `laravel/roster` 1.0.0, `composer/semver` 3.5.0, `symfony/yaml` 8.1.8 *(now a direct production dependency — decision 028)*.

**Consequences:** After editing `.ai/guidelines/*.md`, run `php artisan boost:update` and commit `CLAUDE.md`/`AGENTS.md`. `boost:install --no-interaction` did not record the selected agents, so `agents` was added to `boost.json` by hand; without it `boost:update` refuses to run. Boost is not loaded in production because it is a dev dependency. Re-enable the Cloud guidance only if decision 014 picks Laravel Cloud.

## 019 · Quality gate: Pint, Larastan, Pest arch tests, Rector
**Context:** Roadmap step 5. The founder relies on automated checks, so one command must prove a change is safe to merge.

**Decision:** `composer check` runs, in order: Pint in test mode, Larastan (level 6, `phpstan.neon`), the Pest suite (Unit, Feature and the new Arch suite from `docs/engineering/testing.md`) and `composer audit`. It fails on the first problem. Rector (`rector.php`, PHP 8.4 set plus dead-code, code-quality, type-declaration and early-return sets) is available as `composer rector` / `composer rector:check` but is not part of the gate, because its suggestions are improvements, not defects. Rector skips removing "unused" parameters in `app/Policies`, because Laravel inspects policy signatures (e.g. guest handling).

**Dependencies (dev only, all MIT):** `larastan/larastan` 3.12.2 (Illuminate `^13` supported; brings `phpstan/phpstan` 2.2.16, `iamcal/sql-parser` 0.7) and `rector/rector` 2.6.7 (PHP ≥7.4; shares PHPStan 2.2). Pest's `arch()` is built into Pest 4, so no plugin is needed. `driftingly/rector-laravel` was tried and removed: it pulls in the abandoned `symplify/rule-doc-generator-contracts`, which makes `composer audit` (and therefore the gate) fail.

**Consequences:** Rector's first run added return types to closures in the framework migrations, `bootstrap/app.php` and `routes/console.php` (no schema changes; these migrations have only run locally). Larastan stays at level 6 for now; raising it is a later choice.

## 020 · CI workflow paused; free GitHub plan without branch protection
**Context:** Roadmap step 6. The repository is private on a free personal GitHub account. Branch protection and rulesets need GitHub Pro for private repos (API: "Upgrade to GitHub Pro or make this repository public"). GitHub also refused to start Actions jobs: "recent account payments have failed or your spending limit needs to be increased".

**Decision:** The founder chose to stay on the free plan and not change billing for now. `.github/workflows/ci.yml` is committed and complete (PHP 8.4, Node from `.nvmrc`, PostGIS 17 service, `composer check`, `npm audit --audit-level=high`, actions pinned to commit SHAs), but runs only on manual dispatch until billing is cleared. Until then Claude runs `composer check` and `npm audit` locally before every merge and records the result in the PR. `main` is not technically protected; merges happen only after those checks pass and the founder approves. Making the repo public was rejected (exposes code, specs and security design). Dependabot is configured weekly for Composer, npm and Actions; its runs may also be blocked by the billing flag.

**Consequences:** Phase 0's "CI green" exit criterion is not met yet. To complete it: clear the billing flag in GitHub settings, restore the `pull_request`/`push` triggers, confirm a green run. Revisit branch protection if the plan changes.

## 021 · Filament 5 admin panel with mandatory MFA; pro panel locked
**Context:** Roadmap step 7 and the security baseline: admins use email + strong password + mandatory app-based MFA; access is default-deny; the pro portal uses phone + OTP later.

**Decision:** Install `filament/filament` ^5.9 (5.9.0, MIT; requires Laravel `^11.28|^12|^13` and Livewire `^4.4.2`, so compatible with Laravel 13.34 / Livewire 4.4.7). The **admin** panel (`/admin`) has Filament's login, a profile page, and app-based (TOTP) MFA with recovery codes, set as **required**: an admin without MFA is sent to set it up before reaching anything else. Secrets and recovery codes are encrypted columns on `users` and hidden from serialisation. `User::canAccessPanel()` returns false for every panel until roles exist (step 8), so no one can sign in yet. The **pro** panel (`/pro`) is registered with no login, and `PanelNotYetOpen` middleware returns 404 for all its routes until pro phone login ships. Panel code lives under `app/Filament/Admin` and `app/Filament/Pro`. Filament's published assets are not committed; `php artisan filament:upgrade` republishes them after every `composer install`.

**Dependencies:** Filament's own packages (actions, forms, infolists, notifications, query-builder, schemas, support, tables, widgets) and their dependencies, including `pragmarx/google2fa` (TOTP), `chillerlan/php-qrcode` (MIT/Apache-2.0), `blade-ui-kit/blade-heroicons`, `league/csv`, `openspout/openspout`, `ueberdosis/tiptap-php`, `spatie/laravel-package-tools`, `kirschbaum-development/eloquent-power-joins`, `nette/php-generator` (BSD-3-Clause option of its BSD/GPL dual licence). `composer audit` found no advisories.

**Consequences:** "Log in as admin with MFA" (Phase 0 exit criterion) becomes possible in step 8, once a super-admin role and the creation command exist. The 2-hour admin idle timeout from the security baseline is not set yet; it belongs with session hardening (step 11). Email-based password reset is not enabled because there is no mail provider yet.

## 022 · Roles via spatie/laravel-permission; super-admin by console command
**Context:** Roadmap step 8. The data model defines six roles; the security baseline needs least-privilege admin roles, and the super-admin must never come from a seeder with a real password.

**Decision:** Install `spatie/laravel-permission` ^8.3 (8.3.0, MIT; requires PHP ^8.3 and Illuminate `^12|^13`; no extra dependencies beyond `spatie/laravel-package-tools`, already present). Teams are off. Roles (`customer`, `pro`, `admin_super`, `admin_support`, `admin_vetting`, `admin_finance`, guard `web`) are reference data, so a migration inserts them idempotently and they exist in every environment; the `Role` enum in `App\Domain\Accounts\Enums` mirrors them. `User::canAccessPanel()` lets any admin role into `/admin` (Filament then forces MFA set-up) and keeps `/pro` closed. `php artisan sortd:create-super-admin` creates a super-admin via the `CreateSuperAdmin` Action: it only runs interactively, the password is typed at a hidden prompt (never an argument, so it stays out of shell history), must be 12+ characters with mixed case, numbers and symbols, and is checked against Have I Been Pwned's k-anonymity API (only the first 5 characters of the hash leave the machine). No permissions are defined yet; they arrive with the first admin resources.

**Alternatives:** Filament Shield was not added; it is unnecessary until there are resources to guard and would be another dependency to vet. A role column on `users` was rejected because the data model and tech stack specify this package.

**Consequences:** The founder runs the command once per environment to create their own account. Fine-grained permissions (e.g. second-admin approval for large refunds) come with the features that need them.

## 023 · Supporting packages: audit log, settings, media, money, phone, PostGIS
**Context:** Roadmap step 9 installs the packages named in `tech-stack.md`, each after a compatibility check against Laravel 13.34 / Filament 5.9 / Livewire 4.4 / PHP 8.4.

**Decision and compatibility (all MIT):**
| Package | Version | Supports | Setup |
|---|---|---|---|
| `spatie/laravel-activitylog` | 5.1.1 | PHP ^8.4, Illuminate ^12\|^13 | `activity_log` table. Audit rows are append-only, so `activitylog:clean` must never be scheduled |
| `spatie/laravel-settings` | 3.9.0 | Illuminate ^11\|^12\|^13 | `settings` table; settings classes arrive with their features (Q1 commission etc. still open) |
| `spatie/laravel-medialibrary` | 11.23.8 | Illuminate ^10–^13 | `media` table on a new private `media` disk (`storage/app/private/media`), served only through short-lived signed URLs at `/files/media` (unsigned requests get 403, tested). Pro-edition setting nulled |
| `filament/spatie-laravel-media-library-plugin` | 5.9.0 | Filament 5.9 | Upload fields for admin/pro panels later |
| `brick/money` | 0.15.2 | PHP ^8.2 (`brick/math` already present) | `App\Casts\MoneyCast`: `*_cents` integer ↔ `Money` (ZAR by default); refuses floats, ints, strings and other currencies |
| `propaganistas/laravel-phone` | 6.1.0 | Illuminate ^11\|^12\|^13 | `phone:ZA` validation and E.164 formatting |
| `clickbar/laravel-magellan` | 2.2.0 | Illuminate ^11\|^12\|^13 | Geography columns + PostGIS functions; our own migration already enables PostGIS, so Magellan's is not published |

**Transitive:** `spatie/image` 3.9.7, `spatie/image-optimizer` 1.10.0, `maennchen/zipstream-php` 3.2.2, `spatie/temporary-directory` 2.4.0, `phpdocumentor/type-resolver` 2.1.0 (all MIT) and `giggsey/libphonenumber-for-php-lite` 9.0.40 (**Apache-2.0**, permissive; Google's phone-number data). `composer audit`: no advisories.

**Not added:** Filament's settings plugin (no settings page yet) and `spatie/laravel-data` (the settings `DataCast` entry was removed from config). `brick/money` is pre-1.0, so it is pinned to `^0.15` (0.x minor releases can break).

**Consequences:** Upload rules from the security baseline (MIME allow-list, re-encoding, EXIF stripping) are applied per collection when the first upload feature is built.

## 024 · Integration contracts and Fakes; no fallback to Fakes outside local/testing
**Context:** Roadmap step 10. Third parties are reached only through `app/Contracts`; tests and local development must never hit the network or move money; providers are not chosen yet.

**Decision:** Four provisional contracts with small, provider-neutral surfaces and immutable data objects (`App\Contracts\Data`): `PaymentGateway` (hosted checkout, signed-webhook verification, refund, payout; every instruction carries an idempotency key), `MessagingChannel` (template messages, WhatsApp or SMS), `ScopingAssistant` (suggest a service, summarise; it only suggests), `Geocoder` (autocomplete, resolve). Fakes live in `App\Integrations\Fakes`, record calls and offer assertions (e.g. `assertRefunded`, `assertSent`); the fake gateway dedupes by idempotency key and verifies HMAC-signed webhooks; the fake messaging channel logs messages (including OTP codes) locally. `IntegrationServiceProvider` binds the Fakes as singletons **only** in `local` and `testing`. In staging and production nothing is bound until real providers are added, so a missing provider fails loudly instead of silently faking payments or messages. Domain module folders (`app/Domain/*`) are created empty.

**Consequences:** Each provider decision (messaging Phase 1, payments Phase 4, AI and geocoding when built) adds an implementation under `app/Integrations/<Provider>` and its binding, and may refine the contract. Arch tests enforce that contracts are interfaces, data objects are final and readonly, and integrations are only used from providers and tests. Fakes use PHPUnit assertions, which is fine because they are never loaded in production.

## 025 · Security hardening baseline (headers, HTTPS, sessions, rate limits, strict models)
**Context:** Roadmap step 11 and security baseline §1 and §6.

**Decision:**
- **Headers** (global `SecurityHeaders` middleware, so Filament panels get them too): `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, a restrictive `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, and a CSP with `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'none'`. Scripts and styles still allow `'unsafe-inline'` and `'unsafe-eval'` because Livewire, Alpine and Filament need them; a nonce-based CSP is a later hardening task. Locally the Vite dev server is allowed. `form-action` must be widened if the payment provider's hosted checkout uses a form post (Phase 4).
- **HTTPS:** `Strict-Transport-Security: max-age=31536000; includeSubDomains` on HTTPS responses; `URL::forceHttps()` outside local/testing; session cookies are `Secure` by default outside local/testing, HTTP-only and SameSite=Lax. Redirecting plain HTTP and trusting the host's proxy headers depend on the hosting choice (step 12).
- **Sessions:** 120-minute idle lifetime (the admin limit). The admin login is a custom Filament page without "remember me", so admins cannot get long-lived cookies. Customer/pro 30-day remember-me comes with phone login.
- **Rate limiters** (named, for later routes): `otp-send` (3 per phone per 15 min + 10 per IP per hour; phone hashed in the key), `otp-verify` (10 per IP per 15 min), `webhooks` (120 per IP per minute). Filament's own login throttling stays on.
- **Development safety:** `Model::shouldBeStrict()` outside production; tests already block stray HTTP requests.
- **Logs:** the fake messaging channel now masks phone numbers and never logs message parameters (no OTP codes in logs). Fixes an oversight from step 10.

**Consequences:** Strict models surfaced a factory gap (MFA columns missing on new users); factories now mirror the full schema.

## 026 · Phone + OTP login implementation (spec 001)
**Context:** Spec 001 and security baseline §1. Customer and admin logins share the `web` guard.

**Decision:**
- **Codes:** 6 digits from `random_int`, stored as HMAC-SHA256 with the app key (a leaked table cannot be brute-forced offline), compared with `hash_equals`; 10-minute expiry, 5 attempts counted under `lockForUpdate`, single use; a new code expires earlier ones. Values live in `config/sortd.php`.
- **Rate limits** are enforced in the login component via `LoginThrottle` (Livewire actions are not routes): 3 sends per number per 15 min, **10 per number per day** (blunts slow guessing from rotating IPs), 10 sends per IP per hour, 10 code checks per IP per 15 min, 5 sign-up attempts per IP per 15 min. Hits are recorded before comparing, so parallel requests cannot slip past. Phone keys are HMAC'd so the cache never holds a reversible number.
- **No enumeration:** admin and deleted accounts get a stored but unsent decoy code, so every number sees identical screens and wrong-code messages; an email already used by another account is silently not stored instead of "already in use".
- **Admin MFA cannot be bypassed:** phone login refuses admin accounts, and the admin panel only accepts sessions started on its own login page (`EnsureAdminSignedInThroughPanel`), so a phone session or remember-me cookie of a user later given an admin role is signed out and sent to password + MFA.
- **Sending** is synchronous after the code is saved (the customer is waiting, failures show on screen); a failed send deletes the code.
- **Sessions:** "Keep me logged in" (30 days) is on the code step so returning customers get it too; the verified number is held in the server session for 15 minutes to finish sign-up; Livewire state that matters is `#[Locked]`.
- **Records:** consents per type with version `2026-10-draft`, time, IP and user agent; audit-log entries "account created" and "consent granted"; OTP rows pruned after 90 days.

**Deferred:** trusted-proxy configuration (needs the hosting choice; until then all users behind one proxy would share IP limits), OTP-send spike alerts (with Sentry/monitoring), equalising response time between decoy and real sends once a real messaging provider exists, email verification, re-collecting consent when the lawyer-reviewed legal text replaces the drafts.

## 027 · One account for customer and pro; pro sign-up (spec 011)
**Context:** Phase 1 requires pros to sign up with OTP; the application wizard and vetting come in spec 008.

**Decision:** The founder chose one account per phone number that can hold both the `customer` and `pro` roles. Pros sign up through `/login?as=pro` (spec 001's flow and protections unchanged) and accept a draft pro agreement (`pro_agreement` consent, version `2026-10-draft`); existing customers add the role on `/pros/become`, with the user row locked so a double submit records the agreement once. `User::homeRoute()` is the single landing rule: any pro → `/pros/welcome` (which links to `/app` if they are also a customer); joining-as-pro non-pros → `/pros/become`; others → `/app`. Pro-only accounts are kept out of `/app`. `RecordConsent` is the one place consents and their audit entries are written; audit entries now include "pro role granted". Pros join free (Q12). Business details wait for spec 008; the `/pro` panel stays closed.

## 028 · Catalogue: YAML seeds, admin panel is the source of truth (spec 003)
**Context:** Spec 003 loads `docs/product/scoping/*.yaml` and lets admins edit trades, services and questions.

**Decision:**
- `CatalogueSeeder` validates every file first (types, keys, required fields, options, `urgent_if`), then `ImportCatalogue` adds only trades, services and questions whose keys are new, in one transaction. After the first seed the admin panel is the source of truth; re-seeding never overwrites edits. The seeder runs with `db:seed` and is safe in production.
- **`symfony/yaml` ^8.1 is now a direct production dependency** (same 8.1.8 already locked through Boost; MIT; Symfony-maintained) so the seeder can read YAML on servers.
- Keys are fixed after creation (form + model guard + unique indexes) and used in admin URLs instead of numeric ids (service URLs are scoped to their trade).
- Permissions `catalogue.view` (all admin roles) and `catalogue.edit` (super-admin, support), created by migration and enforced by policies; trades and services are never deleted, only switched off; questions may be deleted until Phase 2 stores answers.
- Every change is audit-logged with before/after via activitylog; Filament's bulk reorder fires no model events, so reorders are logged explicitly ("reordered …" with the new order). New items are appended to the end of their list.

## 029 · Suburbs and properties; location = suburb centre for now (spec 004)
**Context:** Spec 004 and Q9 (launch with Berea/central + North).

**Decision:**
- `SuburbSeeder` loads the 12 launch-area suburbs with approximate centre points (≈1 km); only Berea/central and North are active. Like the catalogue, the admin panel is the source of truth: re-seeding skips existing slugs and names. Slugs are fixed and used in admin URLs. Suburbs use the catalogue permissions and are never deleted; centre moves are audit-logged explicitly (geometry isn't captured by the model audit options).
- Properties belong to one customer, are reached only by `public_id` through the owner (404 otherwise), soft-delete, and are capped at 10 per customer (`config/sortd.php`, owner row locked to make the cap race-safe). The street address is encrypted with the app key, hidden from serialisation, not on any admin screen, and never in logs or the audit log (audit entries hold only the suburb).
- No geocoding yet (founder decision): customers pick a suburb from Get Sorted's list and type their street; `properties.location` is the suburb centroid, enough for suburb-level matching. A map pin/street autocomplete comes with a geocoding provider.
- `/app` routes require the customer role (`EnsureCustomer`); pro-only accounts are redirected to the pro area.

## 030 · Booking flow and job state machine (spec 005)
**Context:** Spec 005 and `docs/product/job-lifecycle.md`.

**Decision:**
- `ServiceJobStateMachine` holds the **full** lifecycle transition map; only it writes `service_jobs.status` (not fillable) and every change appends a `service_job_events` row (model refuses edits/deletes). Spec 005 adds the `PostServiceJob` (draft → open) and `CancelServiceJob` (draft → cancelled) Actions; others arrive with their specs. Tests iterate the map.
- Posting runs in a transaction with the job locked and re-checks every guard (verified phone, required answers, own non-deleted property in an active suburb, active service/trade, date within 30 days in **Africa/Johannesburg** time, "today" only for emergency services, notes length). The customer's `job_posted` message is a queued job dispatched after commit on the `notifications` queue. The "eligible pro" guard arrives with spec 006 (founder decision).
- Answers are checked on the server against type and options and stored with the prompt as asked, so catalogue edits don't rewrite history; they are shown in question order.
- Drafts start on the first answer (not on opening a service), are reused per customer and service, can be removed by the customer, are capped at 5 per customer, and expire after `job_timers.draft_expiry_days` via a daily, idempotent command that re-checks staleness under lock. Posting is limited to 10 per customer per day.
- Guests answer questions without an account; their answers are kept in the session through login (`url.intended`, server-built paths only). "Add property" returns to the booking only for whitelisted booking paths (no open redirect).
- Admins see jobs read-only with suburb but never street address or customer contact details.
- Timers live in `spatie/laravel-settings` (`JobTimers`), not code.

## 031 · Job photos and iPhone HEIC (spec 012)
**Context:** The founder delegated the iPhone photo-format decision after spec 005 split photos into a separate feature.

**Decision:** Accept JPEG, PNG, WebP and HEIC source images, up to five per job and 10 MB each. Process accepted images into WebP and discard the source bytes and metadata before storing them on the private `media` disk. Serve each photo through a five-minute signed URL with job authorization. A cancelled draft deletes its photos. Repeated upload requests for the same processed content reuse the existing photo.

**Deployment requirement:** HEIC decoding requires PHP Imagick with a working HEIC codec. The current local PHP runtime does not have Imagick, so local HEIC uploads show a clear fallback message; the chosen South African host must provide and verify the codec before this feature is deployed.

## 032 · Coverage guard before booking and waitlist (spec 006)
**Context:** Spec 005 deliberately allowed jobs to post before pro eligibility existed. The founder approved spec 006 and its build plan.

**Decision:** Ask for the suburb before scoping. Only an approved pro serving that service and suburb, with a current required registration, provides coverage. Recheck against the property's suburb when posting; a lost match keeps the job as a draft and offers the waitlist. Live bookings have no zero-pro bypass. The local-only `LocalCoverageSeeder` can attach the existing demo pro to active, non-registration services and active suburbs for phone walkthroughs. Admins see aggregate waitlist demand only; a verified customer can remove requests tied to their phone. Entries are pruned after 12 months.

**Eligibility inputs:** The query also checks a rolling seven-day `pro_job_allocations` count against the optional weekly cap and `pro_customer_exclusions` for an upheld dispute involving the signed-in customer. Invite and dispute workflows in later specs will write these records. Guests are rechecked with their customer identity before posting. Pro application, vetting UI and individual waitlist contact access are later work. Until approved pro records exist in a live environment, customers reach the waitlist.

## 033 · AI scoping assistant: Laravel AI SDK, Anthropic, shipped switched off (spec 007)
**Context:** Spec 007 adds a "describe your problem" service suggestion and an editable job description. The tech stack named the first-party Laravel AI SDK with Anthropic. Anthropic processes data outside South Africa, which POPIA treats as a cross-border transfer.

**Decision:** Install `laravel/ai` ^1.0 (1.0.1, MIT; requires PHP ^8.3 and Illuminate ^12|^13, so it supports Laravel 13). `App\Integrations\Anthropic\AnthropicScopingAssistant` implements `ScopingAssistant` with two structured-output agents and model `claude-haiku-4-5-20251001` (config `sortd.ai.model`, chosen for cost and latency), an 8-second timeout, and customer text JSON-encoded inside delimiter tags. It is bound only outside local/testing **and** only when `ai.providers.anthropic.key` is set; local and tests keep the fake. The domain calls it only when the `ai.enabled` setting is on. Founder decisions (2026-10-04): (1) ship with `ai.enabled` off until the privacy notice names the AI provider and the cross-border transfer and the founder has accepted the provider's data processing terms; (2) a 2,000 calls per Durban day budget, adjustable by super-admins in Admin → AI settings; (3) the customer's edited description always wins.

**Contract change:** `ScopingAssistant` methods now return `ScopingSuggestionReply` / `ScopingSummaryReply` with an `AssistantUsage` (provider, model, tokens) so usage can be logged, and throw `AssistantUnavailable` (with a timed-out flag) on outages. All calls go through `App\Domain\Assistant\Support\AssistantCalls`, which applies the switch, per-visitor/per-draft rate limits, the daily budget, output validation and `ai_usage` logging (no customer text; pruned after 90 days by default).

**Consequences:** The package publishes its own conversation migrations only on request; none are used. The architecture test now lets `App\Integrations` classes use each other (an adapter and its own helpers), while the rest of the app still reaches integrations only through contracts. Turning the assistant on in a hosted environment needs `ANTHROPIC_API_KEY` in the host's secret manager, the privacy notice update and the provider terms; there is no other code change.

## 034 · Pro application and vetting (spec 008)
**Context:** No real pro could become approved, so coverage (spec 006) always sent customers to the waitlist. The founder approved spec 008 with four decisions (2026-10-04).

**Decision:** Pros apply in a Livewire form at `/pros/apply` (the Filament `/pro` panel stays closed until invites, spec 009). One `ProStatusMachine` changes `pros.status` (draft → submitted → approved/changes_requested/rejected; changes_requested → submitted; approved ↔ suspended; rejected → draft after the wait), each change in a locked transaction with a `pro_events` row and an activity-log entry. Vetting is limited to `admin_vetting` and `admin_super`, never on their own application (policy `vet`/`viewVetting`; the admin list excludes it). Approval needs a verified ID and proof of address, two positive references and at least one service the pro can do (registration verified and unexpired where required). Founder decisions: (1) bank details wait for the payments phase; (2) no criminal-record checks in v1; (3) rejected or abandoned applications lose documents, references, bio and vetting reasons 12 months after the decision or last activity (`sortd:prune-vetting-records`, daily); (4) a 90-day reapply wait (`vetting.reapply_after_days`).

**Privacy and safety:** ID numbers are never stored; registration numbers and reference phones are encrypted. Documents sit on the private `media` disk and are served only through a five-minute signed link that re-checks the policy and needs the admin login session for admins; images are re-encoded (shared `App\Support\Images\ImageReencoder`, extracted from job photos), PDFs are checked by content, refused if they contain `/JavaScript`, `/JS`, `/Launch` or `/EmbeddedFile`, and always downloaded with a sandbox CSP. Uploads (30/hour) and submissions (5/hour) are rate limited per pro. A reapplication resets every earlier check, and a changed registration number must be verified again.

**Consequences:** The `paused` status (journey P4) is not in the state machine yet; it arrives with the pro's own profile screens. `pro_events` stays append-only except for the prune's deliberate blanking of `reason`. Before launch, consider malware scanning of uploads and turning off `serve` on the `media` disk (nothing uses it). The spec's `vetting.abandoned_after_days` setting was dropped: decision 3's 12-month rule decides when an abandoned application is pruned.

## 035 · Daily login-code cap off on local machines (temporary)
**Context:** While testing on a local machine, the founder hit the daily limit of 10 login codes per number and asked for it to be switched off temporarily.

**Decision:** `sortd.otp.daily_cap_in_local` (default `false`) skips only the daily per-number cap, and only when `APP_ENV=local`. It is always enforced in testing, staging and production, and the 15-minute per-number and hourly per-IP limits still apply everywhere. Today's local counters were cleared.

**Consequences:** To turn it back on locally, set `daily_cap_in_local` to `true` (or remove the switch once testing is done). A test pins that non-local environments keep the cap.

## 036 · Quotes, comparison and acceptance (spec 010)
**Context:** Phase 3's last step: invited pros quote, customers compare up to three and accept one. Payments are Phase 4.

**Decision:** Quotes are versioned rows (`quotes` + `quote_lines`); a revision supersedes the previous version and an expired quote can be renewed while the job is open. `QuoteCalculator` derives every amount from the lines with `Brick\Money`, rounding half-up once per line and once each for VAT, deposit and the commission estimate; the browser never sends totals. At most three current quotes per job (`service_jobs.quotes_count`, kept by the quote actions under the job lock); the third closes the remaining invites, and later invite waves now count quotes rather than invite statuses. Accepting runs in one locked transaction: quote accepted, other quotes declined, open invites closed, `pro_job_allocations` written, and the job moves `open → scheduled` (no deposit) or `open → awaiting_deposit` through `ServiceJobStateMachine` (`quote_accepted`). The scheduler (`sortd:expire-quotes`, every 5 minutes) expires quotes after their valid-until date and open jobs after `quote_window_ends_at` (`job_expired`). Contact details and the street address are shown only to the pro whose quote was accepted; customers see the accepted pro's business name and mobile.

**Founder decisions (2026-10-04):** (1) deposits can be set now; an accepted quote with a deposit waits in "awaiting deposit" with "payment opens soon", and the 48-hour release timer arrives with payments; (2) VAT at the `money.vat_percent` setting (15%) only for pros with a VAT number, to confirm with an accountant; (3) pros see an *estimated* payout using `money.commission_percent` (12%, Q1 default) on labour and call-out, none on materials (Q2 default).

**Consequences:** Quote text is masked for phone numbers, emails, links, ID/card numbers and bank details (`ContactMasker`); `pros.contact_masking_count` flags repeat attempts for admins at 3. Withdrawing closes that pro's invite (they cannot quote the job again). Ordinary text that only changes under Unicode normalisation ("m²", "½") is not masked or counted, and bank details need a banking word plus six or more digits. Rand prices accept a decimal comma ("120,50" is R120.50); a comma is only a thousands separator in groups of three. Authorisation sits in `QuotePolicy` (revise, withdraw, accept, view) and `ServiceJobPolicy::viewContact`; a quote's validity is compared by South African calendar date. Withdrawals and job expiries are written to the activity log, as acceptances are. Customer profile photos on quotes are served only once an admin has verified them. No PDFs, payments or ledger entries yet; those come with Phase 4. `composer check` now needs `COMPOSER_PROCESS_TIMEOUT=0` locally because the suite runs longer than Composer's 300-second default.

## 037 · Private test site on a temporary AWS server ("preview" mode)
**Context:** The founder wants to click through Get Sorted on a phone before a real host exists. They have an AWS EC2 server for about a month (until around 2026-11-04) and the domain `sortd.heyboy.co.za`. The server is in eu-north-1 (Stockholm), not South Africa, so it is not the hosting choice in decision 014 and must not hold real personal data. No WhatsApp provider is chosen yet (Q5). In staging and production nothing fakes messages (decision 024), so nobody could sign in there.

**Decision:** A new environment, `APP_ENV=preview`, for a private test site that holds fake data only.
- `App\Support\AppMode` decides what preview may do. Preview uses the same Fakes as local development: WhatsApp, payments, AI and maps. It also shows the login code on screen, as local development does (spec 001 AC20 now covers preview too). Staging and production are unchanged.
- The persistent test-site banner was removed at the founder’s request on 2026-10-05; preview remains password-protected and uses fake integrations.
- The whole site sits behind one shared password (HTTP basic auth), and `X-Robots-Tag: noindex` keeps it out of search engines.
- It runs in Docker (`deploy/preview/`) because Ubuntu 26.04 offers no PHP 8.4 package:
  - FrankenPHP (`dunglas/frankenphp:1-php8.4-bookworm`) runs PHP and handles HTTPS with Let's Encrypt;
  - the database is PostGIS 17, as in local development;
  - separate queue and scheduler containers.
- `deploy/preview/deploy.sh` builds the assets, copies the code without `.env` or local data, rebuilds, migrates and seeds the catalogue and suburbs.
- The server's `.env` and the site password are created on the server and never committed or printed.

**Consequences:** The test site can be thrown away at any time and nothing depends on it. Phase 0's "staging live" criterion stays open until the South African host is chosen. Testers must not enter real personal details; the banner says so. iPhone HEIC uploads work only if the image's ImageMagick reads HEIC; otherwise the spec 012 fallback message shows. When the server expires, its data goes with it.

## 038 · Email through Resend
**Context:** Receipts and admin emails need a provider. The founder already uses Resend.

**Decision:** Laravel's built-in `resend` mail transport, with `resend/resend-php` ^1.16 (MIT; checked with `composer require --dry-run` against Laravel 13 / PHP 8.4, and no advisories). Production code needs no other change: `MAIL_MAILER=resend`, `RESEND_API_KEY` and `MAIL_FROM_ADDRESS` are set per environment. The preview site sends from `noreply@sortd.heyboy.co.za`, a subdomain verified in Resend so heyboy.co.za's own email isn't affected. Local development and tests keep the `log` and `array` mailers.

**Consequences:** The API key lives only in each server's `.env`. The founder entered it on the server directly, and it never passed through chat or git. When Get Sorted moves to its own domain, verify that domain in Resend and change `MAIL_FROM_ADDRESS`.

## 039 · Email or Google sign-in, then verified email and mobile (spec 014)
**Context:** The founder decided that the phone number must not be the main way in. Customers and pros should sign up like on other sites, then prove their email and mobile. This supersedes decision 004 for customers and pros; admins keep email, password and an authenticator app.

**Decision:**
- **Sign-up and sign-in:** email and password, or "Continue with Google" through `laravel/socialite` ^5.31 (MIT; checked with `composer require --dry-run` against Laravel 13 and PHP 8.4, with no advisories). Google asks only for `openid profile email` and stores `users.google_id`.
- **Passwords:** at least 10 characters, rejected if they appear in the Have I Been Pwned breach list (only a 5-character hash prefix is sent), and stored with Laravel's hasher.
- **Google matching an existing password account:** never linked silently. The user signs in with the password once, and the link is made then.
- **Email verification:** a 60-minute signed link with the user's public ID and an HMAC of the address, so changing the email kills old links.
- **Mobile verification:** reuses spec 001's code rules (`OtpPurpose::VerifyPhone`).
- **The gate:** `EnsureAccountIsVerified`, in the `web` group, sends any signed-in customer or pro to the next step (email, then mobile) and remembers where they were going. Only the verification pages, sign-out and the legal pages are open until then.
- **Password reset:** Laravel's broker. Emails go through Resend (decision 038). Admins never get public reset links.

**Consequences:** Phone + code is no longer a way to sign in; the `login` OTP purpose remains only for old rows. Test data on the preview site and local machines was wiped, so there is no migration for phone-only accounts. Google sign-in needs `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` per environment. The privacy notice draft must mention that email is now required, which is flagged for the legal review.

## 040 · Twilio for WhatsApp and SMS (Q5)
**Context:** Login and verification codes and job updates need WhatsApp with SMS as the backup. The founder chose Twilio for both and switched on its WhatsApp feature. MailerSend's SMS only reaches the US and Canada, and Amazon SNS has no registered sender IDs for South Africa. The founder is happy for SMS to come from a random number for now.

**Decision:** `App\Integrations\Twilio\TwilioMessagingChannel` implements `MessagingChannel` by calling Twilio's Messages API through Laravel's HTTP client, so there is no SDK dependency.
- WhatsApp goes to `whatsapp:+27…` from `TWILIO_WHATSAPP_FROM`; SMS goes from `TWILIO_SMS_FROM`.
- Message bodies are short plain text per template (`MessageTexts`) and carry only the template's parameters.
- It binds only when all four `TWILIO_*` values are set: in staging and production, and on the preview site (decision 037), where it replaces the fake messenger. Local development and tests always use the fake.
- Error messages keep Twilio's status and error code but never the recipient number.

**Consequences:** On the test site, each tester's phone must first join Twilio's WhatsApp sandbox ("join <words>" to the sandbox number). Before launch:
- register a production WhatsApp sender through Twilio, which includes Meta business verification;
- get the message templates approved, and switch to template SIDs, because WhatsApp only allows free text inside a 24-hour customer-initiated window;
- consider a branded SMS sender ID.

Q5 is answered. Costs: about US$0.01 per WhatsApp code and US$0.03–0.05 per SMS to South Africa.

## 041 · Test site: mobile saved without a code while SMS is blocked
**Context:** Twilio trial accounts only send Twilio's predefined SMS templates (error 572006), so Get Sorted's code messages are refused until the account is upgraded. The founder asked to keep the mobile step but skip the code for now.

**Decision:** `PHONE_CODES_ENABLED=false` (config `sortd.otp.phone_codes_enabled`) makes "Add your mobile" save the number as verified without sending a code, then continue to the account. It still checks the number's format and refuses numbers that belong to another account, and it logs `phone saved without code (test site)`. `AppMode::skipsPhoneCodes()` honours the switch only in `local` and `preview`, so staging and production always send codes. A test proves it.

**Consequences:** Numbers on the test site aren't really proven. Switch it back on (remove the setting) once Twilio is upgraded. The first SMS-first channel setting stays as decided.

## 042 · Test site without a password

**Date:** 2026-10-05 · **Status:** Accepted (founder)

**Decision:** The preview site at sortd.heyboy.co.za no longer asks for the shared password (HTTP basic auth removed from the Caddyfile). It still holds fake data only, uses Fake payments and maps, keeps `X-Robots-Tag: noindex`, and the admin panel still needs password + MFA. Changes decision 037's "shared password" point.

**Why:** The founder wants to share the site without handing out a password.

**Watch:** anyone can now sign up and trigger real emails (Resend) and SMS (Twilio) from the site; rate limits apply. Don't put real customer data on it.

## 043 · Amazon Bedrock (EU) for the AI assistant (spec 016)

**Date:** 2026-10-05 · **Status:** Accepted (founder)

**Decision:** Claude runs through Amazon Bedrock in eu-north-1 using the EU cross-region profile (`eu.anthropic.claude-haiku-4-5-20251001-v1:0`), via the Laravel AI SDK's Bedrock driver (`aws/aws-sdk-php`). Set with `SORTD_AI_PROVIDER=bedrock`, `SORTD_AI_MODEL` and `AWS_BEDROCK_REGION`; the direct Anthropic API stays available as `anthropic`. On the test server, credentials come from the EC2 role `sortd-preview-bedrock` (only `bedrock:InvokeModel*` on Anthropic models); no AWS keys in `.env`. A $20/month AWS budget alert emails the founder.

**Why:** Founder's $100 AWS credit covers Bedrock; EU processing; one provider for suggestion, summaries and Siya.

**Watch:** confirm in AWS Billing → Credits that Bedrock usage comes off the credit. The privacy notice must name AWS/Anthropic before a live launch (spec 007 decision 1).

**Update 2026-10-05 (decision 043):** Claude on Bedrock needs an AWS Marketplace subscription, which this AWS account couldn't complete (likely the free account plan). Founder chose **Amazon Nova Lite** (`eu.amazon.nova-lite-v1:0`), a first-party Bedrock model covered by the AWS credit. Switching back to Claude is one setting (`SORTD_AI_MODEL`) once the account can subscribe. The server role also allows `amazon.nova-*` and the Marketplace view/subscribe actions.

## 044 · Take requests from all of Durban before pros are signed up

**Date:** 2026-10-05 · **Status:** Accepted (founder)

**Decision:** The coverage check (spec 006) no longer needs an eligible pro or an active suburb: any active service in any eThekwini suburb can be booked and posted. Controlled by `SORTD_REQUIRE_PROS` (default `false`); set it to `true` to restore spec 006's rule. Tests run with it `true`, plus tests for the open mode.

**Why:** No pros are loaded yet; the founder wants to collect real requests across Durban.

**Watch:** posted jobs may get no invites until pros exist (matching finds no one), so customers can wait indefinitely. Switch back on, or follow up manually, before promising response times. Addresses outside eThekwini still go to the waitlist.

**Update 2026-10-05:** "All of Durban" means every eThekwini suburb: the suburb list grew from 12 to 86 (Pinetown to Umhlanga to the Bluff and Toti, all active), and any address Google places in eThekwini whose area isn't listed yet is added automatically as an active suburb.

**Update 2026-10-05:** "All of Durban" means every eThekwini suburb: the suburb list grew from 12 to 86 (Pinetown to Umhlanga to the Bluff and Toti, all active), and any address Google places in eThekwini whose area isn't listed yet is added automatically as an active suburb.

## 045 · Booking follows Kandua: one Siya thread (spec 017)

**Date:** 2026-10-05 · **Status:** Accepted (founder)

**Decision:** Booking copies Kandua's Jess flow (`docs/product/kandua-reference.md`): one Siya conversation with cards for service, questions, location, date, photos and summary. The 8-step booking wizard and the "Continue booking" hand-off are removed. When the AI is unavailable, the same thread works by taps. Kept from Get Sorted: up to 3 quotes in waves (specs 009/010), the date **plus** a Morning / Afternoon / Flexible window (and Urgent — today), and no prices from Siya.

**Why:** The founder found the chat-then-wizard flow lost track of the customer and repeated questions (location twice, notes twice, service confirmed twice).

**Next:** spec 018: after posting, the customer chats with the quoting pros and can send more photos. Pros send an estimate quote and the customer pays the deposit on it. After accepting, the pro can raise or lower the final amount, and the customer must accept the change. This brings in-app chat into v1, which the PRD had parked.

## 046 · Test site moves to Cape Town on usesorted.co.za

**Date:** 2026-10-05 · **Status:** Accepted (founder)

**Decision:** The test site (decision 037) runs on an EC2 **t3.small in af-south-1 (Cape Town)**: 2 vCPU, 2 GB memory plus a 2 GB swap file, 20 GB gp3 disk, Elastic IP 13.247.209.13, CPU credits set to `standard` (no surplus charges). It has the same Ubuntu 26.04 + Docker setup and the same `sortd-preview-bedrock` instance role (Bedrock stays in eu-north-1). The address is **https://usesorted.co.za** (and www), a domain the founder registered, with a Let's Encrypt certificate from Caddy. The test data was copied from the Stockholm server. `ssh aws` now points at Cape Town; the old server is `ssh aws-stockholm`.

**Why:** Round trips from Durban dropped from about 200 ms to about 35 ms (page first byte 0.74 s → 0.2 s), so taps feel instant (spec 017 AC14). The account is on the AWS free plan, which in Cape Town only allows c7i-flex.large (4 GB, about $89/month) or smaller types. The founder chose the t3.small (about $25/month), so the $120 credit lasts about four months.

## 047 · Admin MFA optional

**Date:** 2026-10-05 · **Status:** Accepted (founder)

**Decision:** Admins sign in to `/admin` with email and a strong password only. App-based MFA stays available, so any admin can switch it on in their profile, and admins who already set it up are still asked for a code. This applies everywhere, including the future live site. It changes decision 021 and the security baseline's "mandatory MFA".

**Why:** The founder asked for 2FA to be turned off.

**Risk:** A leaked or guessed admin password now gives full admin access. Strong passwords (12+ characters, checked against known breaches), login rate limits and panel-only sessions (`EnsureAdminSignedInThroughPanel`) still apply. Revisit before real customer data or money goes through the admin panel.

## 048 · Home page redesign and the name "Get Sorted"

**Date:** 2026-10-06 · **Status:** Accepted (founder)

**Decision:** The public home page (`/`) is redesigned as a dark, app-like page with a collapsible left sidebar on desktop, a serif headline, a "What needs sorting?" box and gallery-style sections (trades, popular jobs, one-thread explainer, sample quotes, sample pros, sample reviews, pros sign-up, FAQ). It is called **Get Sorted** on this page, including the browser title and description. Styles live in `resources/css/home.css`, scoped under `.gs-home`. Geist, Libre Caslon Display and a trimmed Phosphor icon set are self-hosted in `public/fonts/` (the CSP only allows our own fonts). Other pages keep the shared header, footer and styles.

**Why:** The founder wanted a fuller, more modern home page modelled on refero.design's layout, and "Get Sorted" as the product name (usesorted.co.za).

**Watch:** Prices, sample pros, sample reviews and the "now covering Umhlanga and Durban North" banner are invented placeholders and need founder approval or real data before launch. The name still reads "Get Sorted" on every other page, in emails and in the footer of those pages until the founder decides to rename the whole site. The box on the page sends the typed description to the Siya booking thread (spec 017); it does not call the older suggest-a-service step. Photos are the existing generated images. The logo carousel under the hero (replacing the suburb strip) shows placeholder logos of eight Durban-linked organisations (Mr Price Group, Tongaat Hulett, Illovo Sugar Africa, uShaka Marine World, Gateway Theatre of Shopping, AmaZulu FC, Comrades Marathon, Durban University of Technology), saved in `public/images/partners/`. They imply no partnership. Replace them with approved partners, or get permission for each logo, before launch.

## 049 · Google Gemini API for the AI assistant (supersedes the provider in 043)

**Date:** 2026-10-06 · **Status:** Accepted (founder)

**Decision:** Siya, the service suggestion and the job summaries can run on the Google Gemini API through the Laravel AI SDK's Gemini driver. Set `SORTD_AI_PROVIDER=gemini`, `SORTD_AI_MODEL` (a Gemini model id, for example `gemini-2.5-flash`) and `GEMINI_API_KEY`. Bedrock and the direct Anthropic API stay available by changing the same settings. No new dependency.

**Why:** The founder prefers Gemini over Bedrock/Nova for Siya.

**Watch:**
- **POPIA and data location:** unlike Bedrock in the EU (decision 043), Gemini API requests are processed by Google and may leave South Africa and the EU. The privacy notice must name Google before a live launch (spec 007 decision 1), and the cross-border transfer basis needs checking against `docs/security/popia.md`.
- **Training on data:** use a **paid** (billing-enabled) Gemini API project. On the free tier Google may use prompts to improve its products. The assistant only receives the stripped description, service and answers (domain rules), never names, addresses, IDs or payment data.
- **Key handling:** `GEMINI_API_KEY` lives only in the server's `.env`; never commit it.
- **Quality:** check that Gemini keeps to the structured-reply schemas (spec 007 AC11); replies are still validated before use.

## 050 · Siya understands the conversation before booking

**Date:** 2026-10-06 · **Status:** Accepted (founder; spec 019)

**Decision:** Siya supports natural text throughout booking and answers Get Sorted questions from approved public product facts. Service classification happens in the background as a trial; the editable review replaces the separate service confirmation card (supersedes spec 017 AC6 for the trial). Required scoping, secure property/date controls and explicit Confirm booking still run through the existing server-side rules. Gemini support follows decision 049.

**Character:** The founder chose Siya Kolisi as inspiration, then explicitly confirmed inspiration only. The assistant has a warm, grounded, encouraging voice and identifies itself as Get Sorted’s AI assistant. It does not impersonate him, use a likeness or quotes, invent personal experiences or claim endorsement.

**Safety:** Possible emergencies pause ordinary booking. Application-owned, reviewed guidance appears even when the model is unavailable. Only explicit selection of “Discuss a later repair” resumes booking; that action does not establish safety. Multiple unrelated home problems are scoped as separate jobs.

**Implementation:** Structured intents and proposals are validated against the active catalogue, question schemas and verbatim customer note excerpts. The model cannot post jobs or change payment/status state. Corrected services update the existing authorised draft, preserve photos and recheck coverage. Product questions and ordinary conversation do not become pro notes. After founder clarification, greetings, casual chat and unrelated questions have a separate conversation intent; unsupported work receives a relevant explanation instead of unrelated trade suggestions. The visible wizard progress bar is removed so the thread presents a continuous conversation. Conversation state remains in the existing session/draft boundaries; no new transcript table or dependency is introduced.

**Validation:** Deterministic fakes cover safety, corrections, booking, privacy and provider schema failures. Live Gemini conversation quality must still be evaluated with synthetic examples on a configured preview before judging the trial successful. Homepage UI alignment is deferred.

## 051 · Trades, extracted job facts and distance matching (supersedes the service/suburb model)

**Date:** 2026-10-06 · **Status:** Accepted (founder; spec 020)

**Decision:** Services, scoping questions, suburbs and suburb coverage are removed. A job is a trade plus the customer's own words, short facts Siya extracts from natural conversation, photos, a time preference and a Google Places address. Customers and pros both give a geocoded address. A posted job is offered to up to 10 approved pros of that trade, nearest first, within each pro's travel radius (default 15 km, soft edge 2 km that only fills open invites). The first 5 submitted quotes are accepted. Registrations (PIRB, registered electrician) are never a gate: verified pros show a badge, unverified pros can still quote and are labelled to the customer.

**Why:** Fixed sub-services and questions limited what a customer could say and caused Siya to repeat questions and reject answers. Pros are better placed to judge from the facts whether they can help, and distance matches how tradespeople actually work.

**Consequences:**
- **Siya** is a tool-using agent: the app owns `BookingState`; the model changes it only through validated tools (`set_trade`, `add_job_fact` with an exact customer quote as evidence, `remove_job_fact`, `set_urgency`, `park_job`, `offer_next_step`, `flag_emergency`) and replies in plain text. A reply that breaks a rule is regenerated once, then replaced by a deterministic reply built from the state.
- **Privacy:** pros see the area name and an approximate distance, never the street address or exact coordinates, until their quote is accepted. Pro base addresses are encrypted. The privacy notice must mention Google Places for pro addresses before launch.
- **Data:** one irreversible migration (`2026_10_07_000001`) carries existing rows over and drops `services`, `scoping_questions`, `pro_services`, `pro_service_areas` and `suburbs`. The "Today" window is open to every trade. Safety advice lives on the trade.
- **Settings:** `matching.invite_count` (10), `max_quotes` (5), `default_radius_km` (15), `soft_edge_km` (2); invite waves are retired.
- **Deferred:** the quality gate was deferred by founder instruction while this was built; see `docs/engineering/handoff.md`.

**Live evaluation, 2026-10-06 (addendum to 049 and 051):** `siya:eval --live` on synthetic conversations with the Gemini API.
- `gemini-2.5-flash` and `gemini-2.5-flash-lite` are **retired for new keys** (HTTP 404). Set `SORTD_AI_MODEL` to a current model. `gemini-3.1-flash-lite` with `SORTD_AI_THINKING_LEVEL=minimal` (the default) answered in about 1.5–7 s when the API was healthy and passed 7 of 8 cases in two runs, including the breaker-tripping message that used to fail, parking a second job, refusing unsupported work and ignoring prompt injection. The larger Flash models were overloaded (503) or timed out in the same window, and Gemini 3 with default thinking took 10–24 s per turn.
- Provider latency is spiky (the same call took 1.5 s and 15 s+). The per-call timeout is now 25 s, provider overload gets one retry, and a failed turn keeps the validated facts so the customer can tap Try again. Re-run `siya:eval --live` after any model or prompt change and read the replies; it checks state, not tone.

## 052 · The app and the database share one clock: Africa/Johannesburg (SAST)

**Date:** 2026-10-07 · **Status:** Accepted (founder)

**Decision:** The application timezone (`APP_TIMEZONE`) and the Postgres connection timezone (`DB_TIMEZONE`) both default to `Africa/Johannesburg`. Columns stay `timestamptz`, so every stored moment is still an absolute instant; the setting only makes PHP and Postgres agree on how a time written without an offset is read.

**Why:** Postgres on the founder's Mac runs on South African time while the app wrote UTC, so a time written by PHP was read back two hours off. That broke nine tests (sign-in codes, job posting windows, chat notifications) and could break expiry and quote windows anywhere the server's timezone differs from the app's.

**Update 2026-10-07:** spec 020's branch had separately fixed the same mismatch the other way (app and database session both on UTC). The founder chose SAST, so this decision replaces that setting.

**Watch:** Rows written while the two clocks disagreed keep their shifted values. This only matters on databases that already hold real data written under a mismatch; the test site holds fake data. Before launch, set `APP_TIMEZONE` and `DB_TIMEZONE` explicitly in each environment.

## 053 · Pin a patched `shell-quote` for the dev tool `concurrently`

**Date:** 2026-10-07 · **Status:** Accepted (founder)

**Decision:** `package.json` has an npm `overrides` entry that makes the dev tool `concurrently` use `shell-quote` 1.12.0 (was 1.9.0). `concurrently` itself stays on 10.x.

**Why:** `npm audit` reported a critical command-injection advisory for `shell-quote` 1.8.4 to 1.10.0. npm's suggested fix was to downgrade `concurrently` to 9.2.1, which is a breaking change; 1.11.0 and later are outside the affected range. `shell-quote` is used only by the local `composer dev` script, never in production. `npm audit` now reports 0 vulnerabilities and `npm ci` accepts the lockfile.

**Watch:** remove the override once `concurrently` ships with a patched `shell-quote` of its own.

## 054 · Web push with `laravel-notification-channels/webpush` (spec 022)

**Date:** 2026-10-07 · **Status:** Accepted (founder)

**Decision:** Pop-up notifications on phones and desktops use `laravel-notification-channels/webpush` 13.x (brings `minishlink/web-push`, `web-token/jwt-library` and a few small HTTP helpers). Every notice sent through `Notify::user` also goes to the person's subscribed devices. VAPID keys live only in each environment's `.env`.

**Why:** Clients and pros only got in-app notices that appeared on refresh. A browser push is the only way to pop up on a phone or desktop without a native app. This package is the standard Laravel channel for it, installs on Laravel 13 with no conflicts, stores each device's subscription and deletes subscriptions the push service reports as gone. `composer audit` reports no advisories.

**Watch:** iPhone and iPad allow web push only for sites added to the Home Screen (iOS 16.4 or later), so the site has a manifest and an install hint. Safari requires every push to show a notification. Push is skipped quietly when no VAPID keys are set, so local development and tests need no setup.

## 055 · Home page v3: its own layout, self-hosted fonts and GSAP motion

**Date:** 2026-10-07 · **Status:** Accepted (founder)

**Decision:** The public home page (`/`, `App\Livewire\Welcome`) uses a dedicated layout, `components.layouts.home`, with plain CSS and JS in `public/home/` instead of the Vite bundle. Motion uses GSAP 3.13 (core, ScrollTrigger, SplitText; free under the GSAP Standard License since 3.13) and Lenis 1.1.20 (MIT) for smooth scrolling. Fonts are Satoshi (ITF Free Font License) and Sedgwick Ave (SIL OFL); icons are Phosphor bold and fill (MIT). Every file is self-hosted under `public/home/` because the security headers only allow `'self'` (no CDN was added to the CSP). Images live in `public/images/home/` with a `-v3` suffix so the week-long image cache never serves stale copies. The "Start a job" form keeps the existing Livewire `start()` action, so the typed description still reaches Siya.

**Why:** The founder approved a ProjectOne-style redesign with full scroll animation. A separate layout keeps the home page's styles and libraries off every other page, and avoids adding npm dependencies to the app bundle.

**Watch:** Animations switch off for visitors whose device asks for reduced motion; adding `?motion=on` to the URL forces them for previewing. The pro list was removed from the page: Get Sorted does not list pros publicly. Logo files (SVG, outlined lettering) are in `public/home/logo/`.

## 056 · Siya is for signed-in users only; auth pages use the home v3 look

**Date:** 2026-10-07 · **Status:** Accepted (founder)

**Decision:** `/book` and `/book/{trade}` (the Siya booking thread) now require a signed-in user with a verified mobile. A guest who opens them, or who starts a job from the home page, is sent to sign in and returns to the thread afterwards. This supersedes spec 017's guest describe-first flow. `/help` still redirects to `/book`. The sign-in, sign-up, pro sign-up, password reset and verification pages use a dedicated layout, `components.layouts.auth`, with `public/home/auth.css` on top of the home v3 styles.

**Why:** The founder changed their mind: under no circumstances may a visitor who is not logged in reach Siya.

**Watch:** Guest handling inside `Thread` is now unreachable through routes; it can be removed in a later clean-up. The home page box still stores the description in the session so it survives sign-up (it expires after 30 minutes).

**Update 2026-10-07 (founder):** "Start a job" on the home and trade pages sends guests to `/register` (the description is kept and they land in Siya after sign-up and verification). The home start form is the description box only: no trade list and no common-job list. Pressing Google on a sign-up page never signs an existing account in; it sends the visitor to sign in instead. `/pros/join` and the admin sign-in page use the home v3 look (`public/home/pages.css`, `public/home/admin.css`).

**Update 2026-10-07 (founder):** A new sign-in or sign-up lands on the account home, never on Siya, unless the visitor pressed "Start a job" (home page, trade pages or `/start`) in the last 30 minutes (`App\Support\BookingStart`); visiting the home page cancels it. The signed-in pages (`/app`, `/app/jobs`, `/messages`, `/app/account` and the other account pages, and `/book`) use the home v3 look through `public/home/panel.css`, which re-themes the Tailwind palette for pages whose layout passes `gs`.

**Update 2026-10-07 (founder):** The product is now called **Get Sorted** everywhere users and docs see it (it was "Sortd"). Technical identifiers are unchanged for now: the `sortd` config file and `SORTD_*` environment variables, package names, database names, the `sortd.heyboy.co.za` preview host and the `~/sortd` server folder. The whole admin panel uses the home v3 look (`public/home/admin.css`), light theme only, with initials avatars drawn inline (`App\Support\InitialsAvatarProvider`) because the security headers block outside images.

**Update 2026-10-07 (founder):** The admin panel moved from `admin.usesorted.co.za` to **`dashboard.usesorted.co.za`** (`SORTD_ADMIN_DOMAIN` and `SERVER_NAME` on the server). Cloudflare proxy is switched on for the site hosts; `SORTD_BEHIND_CLOUDFLARE=true` was already set and SSL mode must be Full (strict).

## 057 · One name everywhere: GetSorted

**Date:** 2026-10-07 · **Status:** Accepted (founder)

**Decision:** The product is **GetSorted** (one word) in every user-facing string, email, document and page title. Every technical identifier follows: `config/getsorted.php` and `config('getsorted.…')`, `GETSORTED_*` environment variables, `getsorted:*` console commands, the `getsorted` / `getsorted_testing` databases and role (local password `getsorted_local`), the `heyboystudio/getsorted` package name, the `.getsorted-site` CSS class, `.ai/guidelines/getsorted-*.md`, and the logo files (`public/home/logo/getsorted-*.svg`, `public/images/getsorted-*.png`). This supersedes the "technical identifiers are unchanged for now" note in decision 056.

**Why:** Three spellings (Sortd, Get Sorted, Sorted.) were in use at once, before launch, while the brand is still being cleared (Q11). Renaming now is cheap; after launch it is not.

**Kept on purpose:** historical records (this log above 057, specs 001–022, the Phase 0 reports) keep the names that were true when they were written. The old preview host `sortd.heyboy.co.za` (also the current mail sender domain) and the AWS role `sortd-preview-bedrock` stay until the server steps in `docs/engineering/rename-server-checklist.md` are done; the customer domain `usesorted.co.za` is unchanged.

**Watch:** the test server must have its database and role renamed **before** this code is deployed, or the app cannot connect. Existing sessions are signed out once (the session cookie name follows `APP_NAME`).

## 058 · MVP launches on model B (quotes, then hand-off); payments after launch

**Date:** 2026-10-08 · **Status:** Accepted (founder)

**Decision:** GetSorted launches without on-platform payments. A customer describes the job, vetted pros near them quote (up to 5), the customer accepts one, and both get each other's contact details to arrange the work and payment directly (any deposit in the quote is paid to the pro). On-platform payments (deposit, final payment, ledger, payouts, refunds, commission) become Phase M, after launch. Until Phase S is finished there is a **feature freeze**: only fixes, honest copy and docs.

**Why:** The 2026-10-07 audit found the product ends at "Payment opens soon" while the copy promised payments; no provider or commission is decided (Q1–Q4, Q6–Q8). Launching honestly on what works proves demand and supply first, without blocking on payments.

**Consequences:**
- Copy says customers pay pros directly; the pro quote preview no longer shows a commission or payout (PR #65).
- `app/Domain/Payments`, `Disputes` and `Reviews` stay empty for now; spec 013 (deposit payments, PR #37) and spec 018 part 2 (final amount, branch `feat/018-final-amount`) are parked for Phase M.
- Phase L must close the loop without money: a job can be marked done, then reviewed, so jobs do not stay in "Awaiting deposit"/"Booked" forever.
- How GetSorted earns money on model B (lead fee, subscription, or nothing until Phase M) is a new open question (Q14).
- Supersedes the PRD v1 payment scope, the roadmap's Phase 4 order and the money promises in spec 018. `docs/product/prd.md` (v2) and `docs/roadmap.md` describe the new plan.

## 059 · "Start a job" means sign in, then Siya

**Date:** 2026-10-08 · **Status:** Accepted (founder)

**Decision:** Every "Start a job" button (home hero, "One thread" section, footer, the home description form and the trade pages) goes to `/start`. Signed-in users land on `/book`. Guests are sent to **sign in** (the page offers "New to GetSorted? Create an account") and land on `/book` after signing in or signing up. No button scrolls to the form at the bottom of the home page any more. Starting a job forgets any page remembered from an earlier visit, so it cannot override Siya. This changes the "guests go to /register" update in decision 056.

**Why:** The founder wanted one predictable behaviour; the hero button jumped about 11,000 px to the bottom of the page on a phone.
