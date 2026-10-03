# Phase 0 — Laravel scaffold

Scope: roadmap step 2, authorized by the founder after approving step 1. Based on the official `laravel/livewire-starter-kit` main commit `0f62a26c4e4b401c1300930f47d72a497add8cce` (2026-10-03). The tagged v1.0.1 starter is Laravel 12, so the compatible current main commit is used instead.

## Plan and acceptance criteria

- Preserve all existing project documentation, AI rules and tool scripts; merge ignore rules.
- Install Laravel 13 and Livewire 4 on PHP 8.4; retain the starter's free Flux/Tailwind foundation.
- Use Pest 4, with meaningful HTTP checks for the welcome page, health endpoint and disabled account routes.
- Do not expose starter email/password signup, login, account settings or dashboard routes. Phone login and Filament admin authentication belong to later tasks.
- Keep a minimal local welcome page, accessible at 360px width, with compiled local assets.
- Record direct dependencies, compatibility evidence and lock files. Run the relevant tests, format checks, production asset build and dependency audits.
- Leave full PostgreSQL/PostGIS application configuration, Boost, quality-gate expansion, CI and admin panels to their numbered tasks.

## Progress

Upstream source fetched into a temporary directory and copied without replacing project files. Upstream Composer dry-run resolves Laravel 13.34.0, Livewire 4.4.7, and Flux 2.20.1 on PHP 8.4.26. Pest Laravel plugin 4.1.0 explicitly supports Laravel 13 and Pest 4. Vite 8 and Laravel Vite plugin 3.1 accept installed Node 24.

Starter account features (Fortify login/registration, settings, dashboard) removed; tests confirm those routes return 404 and that a deny-all `UserPolicy` guards the bootstrap `User` model. Merged `.gitignore` corrected so `CLAUDE.md`, `.claude/`, `boost.json` and `.mcp.json` stay committed (the upstream starter ignores them).

Verified 2026-10-03: Pest 21 passed (32 assertions), `pint --test` passed, `npm run build` passed, `composer audit` and `npm audit` found no advisories. Desktop and 360px screenshots: `scaffold-desktop.jpg`, `scaffold-mobile.jpg`.

Known follow-ups:
- `.env.example` still uses file sessions/cache and sync queue; step 3 moves them to the database.
- Founder decided 2026-10-03 to keep the starter's empty `tests/Feature/Auth/`, `tests/Feature/Settings/` folders and the icon overrides in `resources/views/flux/`.
- Founder decided 2026-10-03 to keep Flux (free edition, proprietary licence) for future screens (decision 017).
