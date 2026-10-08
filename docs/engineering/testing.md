# Testing strategy

Tests are how a non-coding founder knows the AI's work is right. **No feature is done without tests, and CI must be green before merge.**

## Layers

| Layer | Tool | What | Required for |
|---|---|---|---|
| Unit | Pest | Pure logic: money maths, state machine map, ranking score, value objects | Every domain class with logic |
| Feature | Pest + Laravel HTTP/Livewire/Filament testing | Actions end to end with the database; policies; Livewire components; Filament resources; webhooks | Every Action, Policy, component, resource |
| Architecture | Pest arch tests | Layer rules (see below) | Always on |
| Browser | Pest browser testing (if available for the installed Pest version) or Laravel Dusk | Critical journeys only: book a job, quote, accept, pay (fake), complete | Before each phase sign-off |
| Static | Larastan, Pint | Types and style | Every PR |

## Must-have test cases

- **State machine**: every allowed transition succeeds; every disallowed transition throws; every guard has a failing test.
- **Authorisation**: for each Policy, a test that the owner can and a stranger cannot. Pro cannot see address before acceptance. Pro panel lists only that pro's records.
- **Money**: totals, VAT, commission, refunds and payouts with edge cases (zero deposit, 100% materials, partial refund, rounding to the cent).
- **Webhooks**: valid signature processed once; duplicate event ignored; invalid signature rejected and logged.
- **Idempotency**: scheduled tasks and queued jobs run twice produce the same result.
- **AI assistant**: invalid or hostile model output (unknown service key, injected instructions in customer text) falls back safely. Uses `FakeScopingAssistant`.

## Architecture tests (Pest `arch()`)

```php
arch('domain does not depend on UI')
    ->expect('App\Domain')
    ->not->toUse(['App\Filament', 'App\Livewire', 'App\Http']);

arch('integrations only reached through contracts')
    ->expect('App\Integrations')
    ->toOnlyBeUsedIn(['App\Providers', 'App\Integrations', 'Tests']); // plus: Fakes only in Providers/Tests; each adapter's helpers only within its own folder

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('strict types')->expect('App')->toUseStrictTypes();
```

## Running the checks

- `composer check` — the quality gate: Pint (test mode), Larastan level 6, all Pest suites, `composer audit`. Must pass before any task is reported done.
- `composer test` — Pest only. Arch tests live in `tests/Arch/`.
- `composer lint` — fix formatting. `composer rector:check` / `composer rector` — preview/apply automated refactors.

### Faster runs and the browser test (decision 064)
- `composer test:parallel` runs the same tests in 4 processes (a little faster on a 4-core machine).
- `npm run e2e` drives a real browser through the core path on a running site (see `tests/E2E/README.md`); run it against the test site after a deploy, never production.
- Backups and the weekly restore check live in `deploy/backup/`.

## Conventions

- Test-first for Actions: write the failing test from the spec's acceptance criteria, then implement.
- Factories with named states (`ServiceJob::factory()->open()`, `->withQuotes(3)`).
- Fakes for all integrations (`FakePaymentGateway::assertCharged(...)`); `Http::preventStrayRequests()` globally so no test reaches the internet.
- Freeze time in timer tests (`$this->travelTo(...)`).
- Test database: Postgres (same as production — PostGIS needed), not SQLite.
- Coverage target: 90% on `app/Domain`, measured in CI; not enforced elsewhere.
