# Agent handoff

Updated: 2026-10-08 · Agent: Claude

Read this, then `docs/roadmap.md` → "Where we are". Older handoff notes (spec 020 build, Codex setup) are in git history.

## State

- **One folder, one line of work:** `~/Documents/getsorted/getsorted`, branch `main`. The old `sortd*` worktrees are gone. Parked work: `feat/018-final-amount` (spec 018 part 2, built on the pre-spec-020 model; port in Phase M), PR #37 (spec 013 payments draft).
- **Phase S (feature freeze, decision 058):** S1–S7 done (PRs #61–#67). Old branches are kept as `archive/*` tags. No new features until the roadmap's Phase S exit criteria pass.
- **Name:** GetSorted everywhere (decision 057). Old names remain only in historical decisions/specs and on the test server until `docs/engineering/rename-server-checklist.md` is run.
- **Model B:** no payments; copy says customers pay pros directly (PR #65). Do not add payment, payout, commission or guarantee promises.

## Local machine

- PHP 8.4 (MacPorts `php84`), Node 24 in `~/.local/bin`, Postgres 17 + PostGIS (MacPorts) with data in `~/.local/share/getsorted-postgresql17`. Admin access to Postgres: peer auth over the socket in `~/.local/share/getsorted-postgresql17/socket`.
- Databases: `getsorted` (dev, used by `.env`) and `getsorted_testing` (tests only; `phpunit.xml`). Never point the dev server at the test database: the test suite wipes it.
- `composer check` passes on default PHP (`memory_limit` is raised in `phpunit.xml`). Run `npm run build` after Blade/Tailwind changes before checking in a browser.
- Locally every outside service is a Fake: Siya runs without AI (tap fallback), the geocoder only knows a few demo addresses (search "Innes", "Musgrave", "Lighthouse", "Jan Hofmeyr"), SMS codes show on the verify page, email goes to `storage/logs/laravel.log`.

## Blockers for the founder

- GitHub CI: the account has a billing lock, so Actions jobs never start (decision 020).
- Test server: run the rename checklist before deploying `main`.
- Open questions before launch: Q14 (how model B earns money), Q11, Q13, Q15, Q16 (`docs/product/open-questions.md`).
