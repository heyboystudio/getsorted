# Sortd — domain rules

## Vocabulary
| UI word | Code | Notes |
|---|---|---|
| Job | `ServiceJob` / `service_jobs` | Laravel already uses `jobs` for the queue |
| Pro | `Pro` / `pros` | A vetted tradesperson business, linked to one `User` |
| Customer | `User` with role `customer` | |
| Trade | `Trade` | Plumbing, Electrical, Painting, Tiling |
| Service | `Service` | A sub-type of a trade, e.g. Blocked drain |
| Scoping | `ScopingQuestion`, `scoping_answers` (jsonb) | Seeded from `docs/product/scoping/*.yaml` |
| Invite | `ServiceJobInvite` | A pro invited to quote |
| Quote | `Quote` + `QuoteLine` | Labour / materials / call-out lines |

## Lifecycle
Statuses and allowed transitions: `docs/product/job-lifecycle.md`. Implement as a backed enum `ServiceJobStatus` plus one transition map in `ServiceJobStateMachine`. Each transition is an Action, runs in a transaction with `lockForUpdate()`, checks its guards, writes a `service_job_events` row, and dispatches side effects after commit.

## Privacy rules in the domain
- Pros see suburb, not street address, and no customer contact details until **their** quote is accepted. Enforce in Policies and in what views/resources expose. Test it.
- The AI assistant receives only the service, scoping answers and the customer's free-text description (with phone numbers and emails stripped). Never addresses, names, IDs or payment data.

## Money
Rules: `docs/product/money-flow.md`. Server calculates every total from lines. Payment state changes only from verified webhooks. Ledger entries are append-only and balance (debits = credits) per event. Payouts and refunds carry idempotency keys.

## Matching
Rules: `docs/product/matching.md`. Eligibility is one query object (`EligibleProsQuery`) reused by the coverage check and invite waves. Max 3 submitted quotes per job.

## Configurable values
Timers, commission rate, deposit cap and invite wave sizes come from settings (`spatie/laravel-settings`), never literals in code. Defaults are listed in the lifecycle, money-flow and matching docs.
