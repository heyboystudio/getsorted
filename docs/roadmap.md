# Roadmap

Phases are done when their **exit criteria** pass, not when a date arrives. Timings assume one founder directing Claude Code part-time and are rough.

| Phase | Goal | Rough time | Exit criteria |
|---|---|---|---|
| 0 · Foundations | Empty but production-grade app | 1–2 weeks | All Phase 0 tasks ticked; CI green; staging live; can log in as admin with MFA |
| 1 · Accounts & catalogue | Phone login, roles, catalogue, properties | 1–2 weeks | Customer and pro can sign up with OTP; catalogue seeded from YAML and editable in admin; properties with suburb lookup |
| 2 · Booking | Customer can post a job | 2–3 weeks | Full booking flow incl. coverage check, AI scoping, photos, waitlist; admin sees jobs |
| 3 · Pros & quotes | Vetted pros quote | 2–3 weeks | Pro application + vetting; matching waves; quote builder; customer compares and accepts |
| 4 · Payments | Money moves on-platform | 2–3 weeks | Provider chosen; deposit + final payments; webhooks; ledger; payouts; refunds; PDFs |
| 5 · Trust & comms | The loop is complete | 2 weeks | WhatsApp templates live; reviews; disputes; guarantee flow; notifications preferences |
| 6 · Launch readiness | Safe to take real money | 1–2 weeks | External security review done; POPIA checklist done; legal pages; backups tested; monitoring and alerts; soft launch in 2–3 suburbs |

After launch: public SEO pages per suburb × trade, pro performance dashboards, more trades, isiZulu, native app via API.

---

## Phase 0 — Foundations (detailed)

Do these in order, one PR each where sensible. Stop and ask the founder where marked **[decide]**.

1. [x] Confirm tools: PHP 8.4, Composer, Node LTS, Docker, Postgres 17 + PostGIS locally (via Docker/Sail). Verified 2026-10-03; [results and repeatable checks](engineering/phase-0-preflight.md).
2. [x] Create Laravel 13 app with the **Livewire starter kit** in this repo (keep `docs/`, `.ai/`, `.claude/`, `CLAUDE.md`).  Done 2026-10-03; [notes](engineering/phase-0-scaffold.md).
3. [x] Configure Postgres + PostGIS as the only database (queue, cache, session on database). Done 2026-10-03; [notes](engineering/phase-0-database.md).
4. [x] Install **Laravel Boost** (`composer require laravel/boost --dev`, `php artisan boost:install`), select Claude Code. Verify the regenerated `CLAUDE.md` includes everything from `.ai/guidelines/`. Add the Boost MCP server for Claude Code. Done 2026-10-03; decision 018.
5. [x] Install and configure quality tools: Pest (+ arch tests from testing.md), Larastan (level 6), Pint, Rector. Add `composer check` script running Pint (test mode), Larastan, Pest, `composer audit`. Done 2026-10-03; decision 019.
6. [ ] GitHub repo, branch protection on `main`, GitHub Actions CI (PHP 8.4 + Postgres/PostGIS service) running `composer check` and `npm audit`, Dependabot. **Partly done 2026-10-03:** workflow + Dependabot committed; CI paused and no branch protection on the free plan (decision 020).
7. [x] Install Filament 5; create `admin` panel at `/admin` with MFA required; create `pro` panel at `/pro` (empty, locked). Done 2026-10-03; decision 021.
8. [x] Install `spatie/laravel-permission`, seed roles; super-admin user via artisan command (never in a seeder with a real password). Done 2026-10-03; decision 022.
9. [x] Install activity log, settings, media library (private disk), `brick/money` + `MoneyCast`, phone, PostGIS package — each after a compatibility check. Done 2026-10-03; decision 023.
10. [x] Create `app/Domain`, `app/Contracts`, `app/Integrations` skeletons; contracts + Fakes for PaymentGateway, MessagingChannel, ScopingAssistant, Geocoder; bind Fakes in local/testing. Done 2026-10-03; decision 024.
11. [x] Security headers middleware, HTTPS/HSTS, rate limiter definitions, `Http::preventStrayRequests()` in tests, `Model::shouldBeStrict()` in non-production. Done 2026-10-03; decision 025 (HTTP→HTTPS redirect and proxy trust follow hosting, step 12).
12. [ ] **[decide]** Hosting provider + region (decision 014) and error tracking (decision 015). Deploy staging from `main` via CI; production environment created but empty. **Decided 2026-10-03:** SA hosting provider (TBD) and Sentry EU (decisions 014, 015); staging/production deployment **deferred** until a hosted environment is needed (early Phase 1).
13. [x] README "how to run locally" verified from a clean clone. Done 2026-10-03 (fresh clone: setup, migrate, `composer check`, pages load).
14. [ ] `/phase-check` reports Phase 0 complete; founder signs off.
