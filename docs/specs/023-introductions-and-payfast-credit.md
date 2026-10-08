# Spec 023 · Estimates, introductions and PayFast credit

Status: Approved (founder said "proceed", 2026-10-08; defaults below were chosen by Claude and can be changed) · Phase: L · Owner: founder

Decisions: 061 (earning from introductions), 063 (PayFast for pros' credit). Answers Q14; narrows Q4 to "PayFast for the introduction fee only".

## Goal
GetSorted earns from the **introduction**, never from the quote or the job. A client picks one pro to come and look; that choice is the introduction. It unlocks the client's name, phone number and address. Before it, the client cannot learn who the pro is, so the fee cannot be skipped. The fee (R99, paid by the pro from prepaid credit bought on PayFast) is built but **switched off at launch**.

## User stories
- As a client, I compare estimates from pros without learning their business names, then choose one to visit.
- As a pro, I send an estimate (a price, optionally a range) and, once the client chooses me, I see their details.
- As a pro, once my free introductions are used and the fee is on, I buy credit on PayFast and see my balance and history.
- As the founder, I turn the fee on or off and change its amount without a release, and every introduction is recorded.

## Acceptance criteria
1. Before the introduction, a client sees a pro only by first name (estimate cards, the pro's profile page, the job chat list, notifications). After it, the business name shows.
2. A pro can add "could be up to" to an estimate. The top must be above the total and within the quote limit. Clients see "R 450.00 to R 650.00".
3. The estimate screens say "estimate"; the client's button reads "Choose this pro to visit" and the confirmation explains what is shared.
4. Contact details stay masked in chat until the introduction (already built, spec 010 and 018).
5. Choosing a pro (accepting the estimate) writes exactly one **introduction** for the job, in the same transaction as the booking: job, estimate, pro, client, kind and fee. It cannot be edited or deleted.
6. While the fee is off, or while the pro still has free introductions, the introduction is `free` with fee 0 and costs no credit. The free allowance is the pro's first 10 introductions (setting).
7. When the fee is on and the allowance is used, the introduction is `credit`: the fee (R99, setting) is taken from the pro's credit as a negative ledger row.
8. When the fee is on and the allowance is used, a pro whose credit is below one fee cannot send a new estimate; they are told to top up. Credit can dip below zero if a pro had several estimates out; they then cannot quote until topped up.
9. A pro can open "Introduction credit" from their profile: balance, free introductions left, credit packs (R297, R495, R990 by default) and the last 20 ledger rows. While the fee is off the page says introductions are free and offers no packs.
10. Buying a pack records a pending purchase and sends the pro to PayFast with a signed link. Only approved pros, only configured packs, only while the fee is on.
11. Credit is added only from a PayFast notification that passes all checks (signature, PayFast's address, PayFast confirms it, amount matches). Returning to the site never adds credit. A repeated notification adds credit once.
12. Failed or cancelled payments add nothing and mark the purchase failed. Unknown references and wrong amounts are logged and ignored.
13. The ledger is append-only; the balance is the sum of its rows.
14. Admin can change `fee_enabled`, `fee_cents`, `free_introductions` and the pack sizes (settings; no release needed).

## Screens / UX
- Client, job page: "Estimates (n of 5)". Each card: first name, photo, registration badges, "since", estimate (range if given), start date, "Choose this pro to visit". Confirmation: "Choose Pat to visit? We will share your name, phone number and address so you can arrange the visit. The visit, the final price and the payment are between you and the pro."
- Pro, estimate builder: new optional field "Could be up to, in rand".
- Pro, `/pros/credit`: balance card, free-introduction note, pack buttons, history. Linked from the profile page.
- PayFast hosted page handles payment; it returns to `/pros/credit`.

## Rules and edge cases
- The deposit field stays as built (customer pays pro directly, decision 058). Stuck "awaiting deposit" jobs are spec 024.
- Pros who already quoted before the fee goes on are charged only when chosen.
- A notification for a purchase that is no longer pending (already complete or failed) is ignored.
- If PayFast keys are missing in an environment, buying fails loudly rather than using a fake. Local development and tests use the fake gateway.
- Refunds of credit are manual (admin adds an adjustment row later); PayFast refunds and payouts are not used in the MVP.

## Data changes
`quotes.high_total_cents`; new tables `introductions`, `credit_purchases`, `pro_credit_entries`; settings group `introductions`. See data-model.md.

## Security and privacy
- Webhook route `POST /webhooks/payfast`: no session, throttled (`webhooks` limiter), CSRF-exempt, accepts only notifications that pass PayFast's checks. Rejections are logged without bodies.
- PayFast sees the pro's payment but GetSorted stores no card data. The pro's email is not sent to PayFast.
- First-name-only before the introduction is enforced where names are rendered (`ProIdentity`), with tests.
- Activity log: `introduction_recorded`, `credit_purchase_started`, `credit_purchased`.

## Out of scope
Admin screens for credit adjustments and introductions (use the database for now), invoices and VAT receipts for credit (needs the accountant), reviews (spec 025), money for jobs (Phase M), WhatsApp reminders to top up (email notice only if added later).

## Open questions
Defaults chosen without asking: fee R99; first 10 free; packs R297 / R495 / R990; credit never expires; no refunds. The founder must still supply live PayFast merchant details before the fee is switched on, and the accountant must confirm VAT treatment of credit.

## Progress
Built 2026-10-08 on branch `feat/023-introductions-payfast`. Tested against PayFast's sandbox on the test site.
