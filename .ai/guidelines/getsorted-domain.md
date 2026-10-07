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
