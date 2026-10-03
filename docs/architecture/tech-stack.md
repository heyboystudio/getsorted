# Tech stack

Versions checked 2026-10-03. **Before installing any package, Claude Code must confirm it supports the installed Laravel, Filament and Livewire versions** (check Packagist / the package docs via Boost's doc search). If it doesn't, stop and propose an alternative in the decisions log rather than forcing it.

## Core

| Layer | Choice | Version target |
|---|---|---|
| Language | PHP | 8.4 (Laravel 13 needs ≥ 8.3) |
| Framework | Laravel | 13.x (released 2026-03-17; security fixes to 2028-03) |
| Database | PostgreSQL + PostGIS | 17 or newer |
| Admin + pro portals | Filament | 5.x (needs Livewire 4, Tailwind 4.1+) |
| Customer + public UI | Livewire 4 + Blade + Tailwind CSS 4 | Start from Laravel's official Livewire starter kit |
| Auth | Admins: Filament's own login with built-in multi-factor auth. Customers and pros: custom phone + OTP flow (spec 001). The starter kit's email/password pages are removed or disabled. | — |
| Front-end build | Vite + Node LTS | — |

## Packages (proposed — verify compatibility before install)

| Need | Package | Notes |
|---|---|---|
| AI coding guidelines + MCP for Claude Code | `laravel/boost` (dev) | Generates `CLAUDE.md`; merges our `.ai/guidelines/` |
| AI scoping assistant | Laravel AI SDK (first-party) | Structured output; provider = Anthropic |
| Tests | Pest 4 (+ Pest browser testing if needed) | All new code test-first |
| Static analysis | Larastan (PHPStan) | Level 6 at start, raise to 8 by launch |
| Code style | Laravel Pint | Run on every change |
| Automated refactors | Rector | Used for upgrades (`driftingly/rector-laravel` dropped: abandoned dependency, decision 019) |
| Roles/permissions | `spatie/laravel-permission` | Plus a Filament integration if compatible |
| Audit log | `spatie/laravel-activitylog` | Admin + sensitive actions |
| Files | `spatie/laravel-medialibrary` + Filament media plugin | Private disk, signed URLs |
| Settings | `spatie/laravel-settings` | Timers, commission, caps |
| Money | `brick/money` | Value object; never floats |
| Phone numbers | `propaganistas/laravel-phone` | ZA validation + E.164 |
| PostGIS | `clickbar/laravel-magellan` (or equivalent) | Geography columns, distance/contains queries |
| PDFs | `spatie/laravel-pdf` | Quotes, invoices, statements |
| Feature flags | `laravel/pennant` | Gradual rollouts, fake doors |
| Error tracking | Sentry (`sentry/sentry-laravel`) or Laravel Nightwatch | Pick one in Phase 0 |
| Backups (files) | Provider snapshots + `spatie/laravel-backup` if needed | DB uses managed point-in-time recovery |

## Tooling

| Tool | Purpose |
|---|---|
| GitHub | Code, pull requests, issues, Actions CI |
| GitHub Actions | Pint, Larastan, Pest, `composer audit`, `npm audit` on every PR |
| Dependabot | Weekly dependency PRs |
| Docker (Laravel Sail or a plain Dockerfile) | Same environment locally, in CI and in production |

## Hosting requirements (provider chosen in Phase 0)

- Runs a Docker container or a standard PHP 8.4 runtime, with separate web, queue-worker and scheduler processes.
- Managed Postgres with PostGIS, automatic daily backups and point-in-time recovery, encryption at rest.
- Region in South Africa preferred (latency, simpler POPIA position); otherwise document the cross-border transfer basis.
- TLS everywhere, secrets managed outside the repo, zero-downtime deploys, staging and production kept separate.
- Candidates to compare: Laravel Cloud, Laravel Forge + a VPS in an SA region (AWS af-south-1, Azure South Africa North, or a local provider), a container platform.
