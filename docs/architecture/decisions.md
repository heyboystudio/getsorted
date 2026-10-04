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
| 014 | Hosting: a South African provider; deployment deferred | Accepted (provider TBD) | 2026-10-03 |
| 015 | Error tracking: Sentry (EU data region) | Accepted | 2026-10-03 |
| 016 | Local foundation tools and rootless Docker | Accepted | 2026-10-03 |
| 017 | Official Livewire scaffold with closed account routes | Accepted | 2026-10-03 |
| 018 | Laravel Boost for AI guidelines and MCP | Accepted | 2026-10-03 |
| 019 | Quality gate: Pint, Larastan, Pest arch tests, Rector | Accepted | 2026-10-03 |
| 020 | CI workflow paused; free GitHub plan without branch protection | Accepted (temporary) | 2026-10-03 |
| 021 | Filament 5 admin panel with mandatory MFA; pro panel locked | Accepted | 2026-10-03 |
| 022 | Roles via spatie/laravel-permission; super-admin by console command | Accepted | 2026-10-03 |
| 023 | Supporting packages: audit log, settings, media, money, phone, PostGIS | Accepted | 2026-10-03 |
| 024 | Integration contracts and Fakes; no fallback to Fakes outside local/testing | Accepted | 2026-10-03 |
| 025 | Security hardening baseline (headers, HTTPS, sessions, rate limits, strict models) | Accepted | 2026-10-03 |
| 026 | Phone + OTP login implementation (spec 001) | Accepted | 2026-10-04 |
| 027 | One account for customer and pro; pro sign-up (spec 011) | Accepted | 2026-10-04 |
| 028 | Catalogue: YAML seeds, admin panel is the source of truth (spec 003) | Accepted | 2026-10-04 |
| 029 | Suburbs and properties; location = suburb centre for now (spec 004) | Accepted | 2026-10-04 |
| 030 | Booking flow and job state machine (spec 005) | Accepted | 2026-10-04 |
| 031 | Job photos and iPhone HEIC (spec 012) | Accepted | 2026-10-04 |
| 032 | Coverage guard before booking and waitlist (spec 006) | Accepted | 2026-10-04 |
| 033 | AI scoping assistant: Laravel AI SDK, Anthropic, shipped switched off (spec 007) | Accepted | 2026-10-04 |
| 034 | Pro application and vetting (spec 008) | Accepted | 2026-10-04 |

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

**Consequences:** Scaffold defaults do not activate any customer/admin authentication. *(2026-10-04: `/login` and `/logout` are now Sortd's own phone login, spec 001 / decision 026; the starter's email/password routes stay closed.)* User schema and factory remain bootstrap infrastructure, not a completed accounts feature. Quality tools already supplied by the starter may be used for validation; the full configured quality gate still belongs to step 5.

## 018 · Laravel Boost for AI guidelines and MCP
**Context:** Roadmap step 4. Claude Code (and Codex, which the founder has also used) need the Sortd rules plus version-accurate Laravel guidance, and a way to search docs and inspect the app.

**Decision:** Install `laravel/boost` ^2.10 as a **dev-only** dependency (2.10.1, MIT; requires PHP ^8.2 and Illuminate ^13.0 among others, so it supports Laravel 13). Configure it for Claude Code and Codex: guidelines (`CLAUDE.md`, `AGENTS.md`), skills (`.claude/skills`, `.agents/skills`) and the `laravel-boost` MCP server (`.mcp.json`, `.codex/config.toml`). All generated files are committed so cloud sessions have them. Our `.ai/guidelines/*.md` are the source for the Sortd section; a precedence line in `sortd-project.md` makes Sortd rules win over generic Boost guidance. `config/boost.php` excludes the `deployments` guideline and `boost.json` disables Cloud, because both recommend Laravel Cloud while hosting is still open (decision 014).

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

## 014 · Hosting: a South African provider; deployment deferred
**Context:** Roadmap step 12. Requirements (`tech-stack.md`): PHP 8.4 or Docker with separate web/queue/scheduler processes, managed Postgres with PostGIS, daily backups and point-in-time recovery, encryption at rest, TLS, staging and production separated; an SA region preferred for latency and POPIA. Options compared: Laravel Forge + AWS Cape Town, Laravel Cloud (no Africa region; nearest Frankfurt/Ireland), a local SA hosting provider.

**Decision:** The founder chose **hosting with a South African provider** (option C) and to **keep developing locally for now**. The specific provider is chosen when the first hosted environment is needed (expected early Phase 1, when real OTP messages need a public URL). Before choosing, compare SA providers against the requirements above; if none offers managed Postgres with PostGIS and point-in-time recovery, bring the fallback (self-managed Postgres with WAL archiving/pgBackRest and tested restores) back to the founder as a decision.

**Consequences:** Phase 0's "staging live" exit criterion stays open until then. Data stays in South Africa, which simplifies the POPIA position. HTTP→HTTPS redirect and trusted proxy settings (decision 025) are configured with the provider.

## 015 · Error tracking: Sentry (EU data region)
**Context:** Roadmap step 12. Options: Sentry (free developer plan, Team ~$26/month; EU data residency; built-in PII scrubbing) or Laravel Nightwatch (free tier 300k events, Pro $20/month; data location not confirmed).

**Decision:** The founder chose **Sentry**, starting on the free plan with the **EU data region**. `sentry/sentry-laravel` is installed (after a compatibility check) together with the first hosted environment; it is not needed locally. Configure it to send no request bodies, cookies or user PII (`send_default_pii=false`) and to scrub phone numbers, emails and addresses.

**Consequences:** Error data leaves South Africa (EU), so Sentry is listed as an operator in the POPIA checklist and privacy notice, with the cross-border basis documented.

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
- No geocoding yet (founder decision): customers pick a suburb from Sortd's list and type their street; `properties.location` is the suburb centroid, enough for suburb-level matching. A map pin/street autocomplete comes with a geocoding provider.
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

