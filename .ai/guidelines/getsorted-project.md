# GetSorted — project rules (read before any task)

GetSorted is a Durban/eThekwini marketplace connecting households with vetted tradespeople (plumbing, electrical, painting, tiling in v1). The founder does not write code: you write all code, tests and docs, and you explain decisions in plain language.

## Where things are
- Product: `docs/product/` — PRD, user journeys, job lifecycle (state machine), money flow (Phase M), matching, trades YAML, open questions.
- Architecture: `docs/architecture/` — overview and code layout, data model, tech stack, decisions log.
- Engineering: `docs/engineering/` — conventions, testing, workflow and definition of done.
- Security: `docs/security/` — security baseline (non-negotiable), POPIA checklist.
- Plan: `docs/roadmap.md`. Feature specs: `docs/specs/`. Templates: `docs/templates/`.

## How to work
1. Read the relevant spec and docs **before** planning. If there is no spec for a feature, write one first (`/spec`) and get approval.
2. Plan first, then write failing tests from the acceptance criteria, then implement.
3. Small, focused changes on a feature branch; one PR per spec or sub-task.
4. Run `composer check` before saying something is done. Never claim tests pass without running them.
5. Update docs in the same change: data model, decisions log, spec progress.
6. If something in the docs is ambiguous, contradictory, or listed in `docs/product/open-questions.md`, **ask** — do not guess on product, money, security or privacy matters. For anything else, pick the conventional option and note it in the PR.
7. Explain results to the founder in plain language: what changed, how to try it, what needs their decision.

## Hard rules
- These GetSorted rules take precedence over the generic Laravel Boost guidelines that follow them in `CLAUDE.md` (for example, docs **must** be updated in the same change, and hosting stays undecided until decision 014).
- Follow `docs/security/security-baseline.md` in every change.
- Business logic lives in `app/Domain/*/Actions`. Livewire components, Filament resources, controllers and queued jobs stay thin.
- Job status changes only through `ServiceJobStateMachine` transitions; every transition writes a `service_job_events` row.
- Money is integer cents (`*_cents`) and `Brick\Money` in code. Never floats.
- Third-party services only through `app/Contracts` interfaces; tests use Fakes; tests never hit the network.
- Never expose numeric IDs in URLs — use `public_id` (ULID).
- Never read `.env` files or print secrets. Never run commands against staging or production databases.
- Never add a dependency without checking it supports the installed Laravel/Filament/Livewire versions and adding a decisions-log entry.
- Never edit a migration that may have run elsewhere; add a new migration.
- Never weaken a test to make it pass; fix the code or ask.
- Never write copy (UI, emails, home page) that promises something the app does not do yet, such as on-platform payments, payouts or a guarantee (decision 058).
