# CLAUDE.md — GetSorted

> The Laravel Boost section at the end of this file is generated from Laravel's guidelines plus our project rules in `.ai/guidelines/`. To change those rules, edit `.ai/guidelines/*.md` (never the generated section), run `php artisan boost:update`, and commit `CLAUDE.md` and `AGENTS.md` so cloud sessions have them. Everything above the generated section is kept by Boost and is edited here directly.

## Read first, every session
1. `.ai/guidelines/getsorted-project.md` — how to work and hard rules (also included below)
2. `.ai/guidelines/getsorted-domain.md` — domain vocabulary and rules (also included below)
3. `docs/roadmap.md` — current phase and next tasks
4. The spec you are working on in `docs/specs/` (create it with `/spec` if missing)

## The founder
- Does not write code. Expects you to do the engineering and explain results in plain language.
- Approves specs and plans, clicks through previews, and makes product/money/privacy decisions.
- Ask before deciding anything listed in `docs/product/open-questions.md`.

## Current phase: S — Stabilise (feature freeze), then L — Launch on model B
Read `docs/roadmap.md` → "Where we are" first. Until Phase S is done, build no new features: only fixes, honest copy and docs (decision 058). The MVP takes no payments: customers pay pros directly; payments are Phase M.
- Product scope: `docs/product/prd.md` (v2). Decisions: `docs/architecture/decisions.md` (latest 062).
- Verify every package version against Laravel 13 / Filament 5 / Livewire 4 before installing (Boost's `search-docs` helps).

## Commands
- `/spec <idea>` · `/build-feature <spec>` · `/review` · `/adr <decision>` · `/phase-check`
- Agents: `code-reviewer`, `security-reviewer`, `spec-checker`

## Quality gate
`composer check` must pass before any task is reported done (it runs on a default PHP install; tests use the `getsorted_testing` database, never the dev database `getsorted`). GitHub CI cannot run while the GitHub account has a billing lock (decision 020), so also run `npm audit --audit-level=high` locally before every merge and record both results in the PR. After changing Blade or Tailwind classes, run `npm run build` before checking in a browser.

===

<laravel-boost-guidelines>
=== .ai/getsorted-domain rules ===

# GetSorted — domain rules

## Vocabulary
| UI word | Code | Notes |
|---|---|---|
| Job | `ServiceJob` / `service_jobs` | Laravel already uses `jobs` for the queue |
| Pro | `Pro` / `pros` | A vetted tradesperson business, linked to one `User` |
| Customer | `User` with role `customer` | |
| Trade | `Trade` | Plumbing, Electrical, Painting, Tiling; seeded from `docs/product/trades/*.yaml` |
| Facts | `service_jobs.facts` (jsonb) | Short job facts Siya records, each backed by the customer's exact words (decision 051). Services and scoping questions no longer exist. |
| Invite | `ServiceJobInvite` | A pro invited to quote |
| Quote / estimate | `Quote` + `QuoteLine` | Labour / materials / call-out lines; customers see "estimate" |

## Lifecycle
Statuses and allowed transitions: `docs/product/job-lifecycle.md`. Implement as a backed enum `ServiceJobStatus` plus one transition map in `ServiceJobStateMachine`. Each transition is an Action, runs in a transaction with `lockForUpdate()`, checks its guards, writes a `service_job_events` row, and dispatches side effects after commit. Today no Action moves a job past `Scheduled` / `AwaitingDeposit`; finishing a job is Phase L work.

## Privacy rules in the domain
- Pros see the area name and an approximate distance, not the street address or exact coordinates, and no customer contact details until **their** quote is accepted. Enforce in Policies and in what views/resources expose. Test it.
- The AI assistant receives only the trade, the facts and the customer's free-text description (with phone numbers and emails stripped). Never addresses, names, IDs or payment data.

## Money
MVP is model B (decision 058): GetSorted takes **no payments**; customers pay pros directly and the copy must say so. Never add copy that promises on-platform payment, payouts, commission or a guarantee. When Phase M starts, the rules in `docs/product/money-flow.md` apply: the server calculates every total from lines, payment state changes only from verified webhooks, the ledger is append-only and balances per event, payouts and refunds carry idempotency keys.

## Matching
Rules: `docs/product/matching.md`. Eligibility is one query object (`EligibleProsQuery`): approved pros of the trade, nearest first, within each pro's radius plus a soft edge. Up to `matching.invite_count` (10) invites at once; the first `matching.max_quotes` (5) quotes are accepted. Invite waves are retired.

## Configurable values
Timers, radius, invite count, quote limit, invite expiry (normal and urgent), deposit cap and (later) commission come from settings (`spatie/laravel-settings`), never literals in code. Defaults are listed in the lifecycle, money-flow and matching docs.

=== .ai/getsorted-project rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
