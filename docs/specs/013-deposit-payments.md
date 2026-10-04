# Spec 013 · Deposit payments, webhooks and the ledger

Status: Draft · Phase: 4 · Owner: founder

## Goal
A customer who accepted a quote with a deposit can pay it online, and the job is then booked. This is the first time money moves on Sortd, so it also builds the foundations every later payment uses:
- invoices;
- payments that only count once the provider's signed webhook confirms them;
- a balanced, append-only ledger.

It is built against the `PaymentGateway` contract with the fake gateway. The real provider plugs in through a separate, small spec once it is chosen (open question 1).

## User stories
- As a customer, I want to pay the deposit securely by card or instant EFT, so the pro is booked.
- As a pro, I want to know as soon as the deposit is paid, so I can plan the job and buy materials.
- As the founder, I want every rand recorded in a ledger that always balances, and payments that can't be faked by a browser.

## Acceptance criteria
**Deposit invoice**
1. When a quote with a deposit is accepted (spec 010), a `deposit` invoice is created in the same transaction. It is issued by the pro, gets the pro's next sequential invoice number, and its amount is the quote's `deposit_cents`. It records the labour, materials and VAT shares in proportion to the deposit, worked out in cents and rounded half-up so the shares add up exactly. Its status is `issued`, and it is due 48 hours (setting) after acceptance.

**Paying**
2. Given a job `awaiting_deposit` and its customer, the job page shows "Pay deposit: R X" with the due time. Tapping it creates a `pending` payment, with a unique idempotency key, through `PaymentGateway::createCheckout`, and sends the customer to the provider's hosted checkout. Sortd never sees card details.
3. A double tap, or coming back to the page, reuses the open checkout instead of creating a second payment. Only one payment can be pending for an invoice at a time.
4. When the customer returns from checkout, the page shows "Checking your payment…" and refreshes until the webhook arrives. The redirect itself never changes any status.

**Webhooks (the source of truth)**
5. `POST /webhooks/payments` accepts only events whose signature `PaymentGateway::verifyWebhook` confirms. Anything else gets a 400 and is logged without its body. The route is exempt from CSRF and rate-limited.
6. A verified `payment.succeeded` event for the right amount, in one locked transaction:
   - marks the payment `succeeded` and the invoice `paid`;
   - writes balanced ledger entries;
   - moves the job `awaiting_deposit → scheduled` through `ServiceJobStateMachine` (event `deposit_paid`).

   After the commit, the pro gets a `deposit_paid` WhatsApp message and the customer gets a receipt message.
7. The same event processed twice changes nothing (unique provider event id). An event for an unknown payment, a different amount or a job that is no longer `awaiting_deposit` changes no status. It is recorded and flagged for admins. A payment that succeeds after the job moved on must still be refunded by an admin; refunds come in a later spec.
8. A verified `payment.failed` event marks the payment `failed`. The customer sees "Your payment didn't go through" and can try again.

**Ledger**
9. Every money event writes entries to `ledger_entries`. The entries never change and are never deleted, debits always equal credits for each event, and balances are always derived from the entries. A deposit paid writes: debit `provider_clearing`, credit `customer_funds_held`, for the deposit amount. Provider fees are recorded once the real provider reports them (open question 3).

**Unpaid deposits**
10. If the deposit isn't paid by its due time, the scheduler (safe to run twice):
    - cancels the deposit invoice;
    - marks the accepted quote `released`;
    - moves the job `awaiting_deposit → open` (event `deposit_expired`) with a fresh quote window.

    The customer is told the booking lapsed, and the pro is told the job was released. What happens to the other quotes is open question 2.

**Admin**
11. Admin → Jobs shows the job's invoices, payments (status, provider reference, amounts) and ledger entries, read-only. Payment events that were flagged under AC7 appear in an admin list with the reason.

**Test site and local development**
12. With the fake gateway (local and the preview site only), checkout opens a Sortd page marked "Test payment" with "Pay successfully" and "Fail payment" buttons. Each sends a signed fake webhook through the same path as a real one, so the whole flow can be tried on a phone. This page doesn't exist in staging or production.

## Screens / UX
- **Customer job page (awaiting deposit):** a card with "Pay deposit: R 1 234.50" and "Due by Tue 6 Oct, 14:00". The button reads "Pay deposit". Below it: "Secure payment by <provider>. Sortd never sees your card."
- **Return page:** a spinner with "Checking your payment…", then one of:
  - "Deposit paid. You're booked for …";
  - "Your payment didn't go through", with a "Try again" button.
- **Pro job page:** "Waiting for the customer's deposit (due …)", then "Deposit paid: R X. Booked for …".
- **Errors:** if the provider is unavailable, the customer sees "We couldn't open the payment page. Please try again in a minute."
- 360 px first; amounts shown as "R 1 234.50".

## Rules and edge cases
- Money is integer cents and `Brick\Money`, with no floats.
- Webhooks are the only source of truth (`docs/product/money-flow.md`, principles 3 and 5).
- Job status changes only through `ServiceJobStateMachine`. Payment actions lock the job row, then the invoice row.
- Invoice numbers run in sequence per pro, start at 1, and are unique (pro, number); a gap is not reused. Format: `<pro code>-000123`.
- A cancelled job can't be paid; its checkout links stop working.
- The quote can't change once accepted, so the deposit amount is fixed.

## Data changes
- New tables (from `data-model.md`, refined):
  - `invoices`
  - `payments` (adds `checkout_url_expires_at` and `provider_event_id`)
  - `payment_events`: provider event id unique, type, payment_id nullable, verified_at, outcome (`applied`, `duplicate`, `flagged`), flag_reason; payload stored with personal data stripped
  - `ledger_entries`: append-only, with a database guard against updates and deletes
- `pros`: add `invoice_prefix` and `next_invoice_number`.
- `quotes`: add status `released`.
- Settings: `payments.deposit_due_hours` (48).
- Update `docs/architecture/data-model.md` and `docs/product/job-lifecycle.md` (event names).

## Security and privacy
- **Policies:** only the job's customer can start a payment. Pros and admins can view but not pay. Admins can't edit payments or ledger rows.
- **Webhooks:**
  - signature checked before any other parsing;
  - the payload size is capped;
  - logs never contain card or personal data;
  - a timing-safe comparison is used, as the fake already does.
- **Card data:** a hosted checkout keeps Sortd out of PCI card-data scope.
- **Rate limits and logging:** checkout creation is rate-limited per customer. The activity log records every payment status change and every flagged event.

## Out of scope
- The real provider adapter (next spec, once open question 1 is decided).
- Final invoices and payment, payouts, deposit release, refunds and cancellations (later Phase 4 specs).
- PDFs for invoices and receipts (their own spec).
- Nightly reconciliation against the provider's report (needs the real provider).
- A booking fee (Q3, stays R0).

## Open questions
1. **Payment provider (Q4).** We need a provider that can hold a customer's money until work starts or finishes, then pay the pro (next business day), refund through its API, and send signed webhooks. My recommendation:
   - Apply to **Peach Payments** and **Paystack** now. Ask both specifically about "marketplace hold-and-release with payouts to third-party bank accounts".
   - Paystack's splits pay the pro immediately, which doesn't fit holding a deposit.
   - PayFast is the fallback.

   This spec doesn't need the answer, because it is built on the fake. The next spec does.
2. **When an unpaid deposit lapses**, what happens to the other quotes the customer didn't pick? **Recommended:** quotes that are still valid come back so the customer can choose another one, and those pros are told. Alternative: they stay declined and new invites go out.
3. **Who pays the payment provider's fee** (about 1–3% per payment)? **Recommended:** Sortd absorbs it for now, recorded in the ledger as `provider_fees`, and we review once real volumes exist. Alternative: deduct it from the pro's payout.

## Progress
- 2026-10-04: Drafted after spec 010 merged in PR #35 and the preview site in PR #36. Awaiting founder approval and the three decisions above.
