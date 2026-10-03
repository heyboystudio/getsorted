# CLAUDE.md — Sortd (bootstrap version)

> This is the **pre-Laravel bootstrap** file. In Phase 0, step 4, Laravel Boost regenerates `CLAUDE.md` from its own Laravel guidelines plus our project rules in `.ai/guidelines/`. After that, edit `.ai/guidelines/*.md`, never `CLAUDE.md` directly, and run `php artisan boost:update`. Commit the regenerated `CLAUDE.md` so cloud sessions have it.

## Read first, every session
1. `.ai/guidelines/sortd-project.md` — how to work and hard rules
2. `.ai/guidelines/sortd-domain.md` — domain vocabulary and rules
3. `docs/roadmap.md` — current phase and next tasks
4. The spec you are working on in `docs/specs/` (create it with `/spec` if missing)

## The founder
- Does not write code. Expects you to do the engineering and explain results in plain language.
- Approves specs and plans, clicks through previews, and makes product/money/privacy decisions.
- Ask before deciding anything listed in `docs/product/open-questions.md`.

## Current phase: 0 — Foundations
Follow the numbered tasks in `docs/roadmap.md` → "Phase 0". Notes for bootstrapping:
- This repo already contains `docs/`, `.ai/`, `.claude/`, `CLAUDE.md` and `README.md`. `laravel new` needs an empty folder, so create the Laravel app in a temporary folder and move its files in, **keeping** the existing files. Merge `.gitignore` entries rather than replacing.
- Use the official Livewire starter kit, PostgreSQL, Pest.
- Verify every package version against Laravel 13 / Filament 5 / Livewire 4 before installing (Boost's documentation search helps once installed).
- After Boost is installed, confirm the regenerated `CLAUDE.md` contains the Sortd project and domain rules. If not, stop and report.

## Commands
- `/spec <idea>` · `/build-feature <spec>` · `/review` · `/adr <decision>` · `/phase-check`
- Agents: `code-reviewer`, `security-reviewer`, `spec-checker`

## Quality gate (once Phase 0 step 5 is done)
`composer check` must pass before any task is reported done.
