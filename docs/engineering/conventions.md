# Coding conventions

Laravel Boost's guidelines cover general Laravel, Livewire, Pest, Tailwind and Pint conventions. This file adds GetSorted-specific rules; where they conflict, **this file wins**.

## General

- `declare(strict_types=1);` in every PHP file.
- Typed properties, parameters and return types everywhere. No `mixed` unless unavoidable.
- `final` classes by default (Actions, Data objects, Integrations); remove `final` only when extension is intended.
- Enums (PHP backed enums) for every fixed set of values: statuses, types, roles, channels. Never compare against string literals.
- No magic numbers or durations in code: timers, rates and caps come from `spatie/laravel-settings` or `config/getsorted.php`.
- Dates: `CarbonImmutable`. Columns are `timestamptz`; the app and database connection share `Africa/Johannesburg` (decision 052).
- Money: `Brick\Money\Money` in code, `*_cents` integers in the DB. A `MoneyCast` handles conversion.
- Comments explain **why**, not what. Public Actions get a one-line docblock stating the business rule.

## Naming

| Thing | Pattern | Example |
|---|---|---|
| Action | Verb + noun | `AcceptQuote`, `SendInviteWave` |
| Query object | Noun + `Query` | `EligibleProsQuery` |
| Event | Past tense | `QuoteAccepted` |
| Enum | Singular noun | `ServiceJobStatus` |
| Data object | Noun + `Data` | `ScopingResultData` |
| Contract | Capability noun | `PaymentGateway` |
| Integration | Provider + contract | `PaystackPaymentGateway` (example) |
| Fake | `Fake` + contract | `FakePaymentGateway` |
| Livewire | Feature + screen | `Booking\ChooseService` |
| Test | Mirrors class | `tests/Feature/Domain/Quotes/AcceptQuoteTest.php` |

## Actions

```php
final class AcceptQuote
{
    public function __construct(
        private ServiceJobStateMachine $stateMachine,
    ) {}

    /** Customer accepts one quote; other quotes are declined and the pro gets the address. */
    public function handle(User $customer, Quote $quote): ServiceJob
    {
        Gate::forUser($customer)->authorize('accept', $quote);

        return DB::transaction(function () use ($quote, $customer) {
            $job = $quote->serviceJob()->lockForUpdate()->firstOrFail();
            // ... guards, transition, side effects via events
            return $job;
        });
    }
}
```

- One public `handle()` method. Dependencies via constructor injection.
- Authorise inside the Action as well as at the entry point (defence in depth).
- Write operations run in a transaction and lock the rows they change.
- Side effects that talk to the outside world (WhatsApp, email, payment provider) are dispatched **after commit** (`afterCommit`) as queued jobs or event listeners.
- Throw a domain exception (e.g. `QuoteExpired`) for broken business rules; entry points turn it into a friendly message.

## Database

- Every schema change is a migration; never edit a migration that has run in staging or production — add a new one.
- Migrations must be reversible (`down()`) unless explicitly noted.
- Foreign keys and indexes declared in the migration. Check constraints for invariants (non-negative money, rating range).
- Seeders: `CatalogueSeeder` (from scoping YAML), `SuburbSeeder`, `DemoSeeder` (local/staging only, guarded by environment check).
- Factories for every model, with states for each status.

## Filament

- One Resource per model per panel (`app/Filament/Admin/Resources`, `app/Filament/Pro/Resources`).
- Resources call Actions for anything beyond plain field edits; table/page actions that change state call an Action.
- Every Resource relies on its Policy; the pro panel scopes every query to the logged-in pro (`getEloquentQuery()`), tested.

## Livewire

- Components are thin: validation rules, authorisation, call Action, render.
- No business logic in Blade. No queries in Blade.
- Use `wire:loading` states on every button that calls the server; disable double-submits.

## Front end

- Tailwind utility classes; shared UI in Blade components (`resources/views/components`).
- Mobile first: design at 360 px, then scale up.
- Every string through `__()` so isiZulu can be added later.
- Accessible: labels on inputs, focus states, colour contrast AA, buttons are `<button>`.

## Git

- Branch per task: `feat/…`, `fix/…`, `chore/…`. Small PRs (aim < 400 changed lines excluding generated files).
- Conventional commit messages (`feat(quotes): allow pros to revise a submitted quote`).
- `main` is protected: PR + passing CI required. No force pushes.
