# Spec 010 · Quote builder, comparison and acceptance

Status: Approved · Phase: 3 · Owner: founder

## Goal
An invited pro can send an itemised quote from their phone. The customer compares up to three quotes side by side and accepts one. The job is then booked with that pro, who gets the customer's contact details and address. This completes Phase 3. Paying deposits and final invoices is Phase 4.

## User stories
- As a pro, I want to price a job quickly with labour, materials and call-out lines, so the customer sees exactly what they pay for.
- As a pro, I want to correct or withdraw my quote while the customer hasn't decided.
- As a customer, I want to compare quotes clearly and pick one with confidence.
- As the founder, I want no more than three quotes per job, and contact details kept private until a quote is accepted.

## Acceptance criteria
**Quote builder (pro)**
1. Given an open, unexpired invite on an open job, when the pro taps "Send a quote", then they can add lines with a kind (labour, materials or call-out), a description (max 120 characters), a quantity (0.01–9,999, two decimals) and a unit price in rand. They also set:
   - a deposit percentage (0 to the configured cap, default 50%);
   - an earliest start date (today to 60 days ahead);
   - how long the quote is valid (1–30 days, default 7);
   - optional notes (max 1,000 characters).
   At least one line is needed, and at most one call-out line.
2. The server calculates every total from the lines in integer cents: line totals, labour, materials, call-out, VAT (open question 2), total and deposit (rounded to the cent). It never trusts totals sent by the browser. A preview shows the quote exactly as the customer will see it, plus the pro's estimated payout after Sortd's commission (the configured rate on labour and call-out; open question 3).
3. When the pro submits, then the quote becomes `submitted` (version 1), the invite becomes `quoted`, the customer gets a `quote_received` WhatsApp message, and the job's quote count goes up. A double tap submits once.
4. A job accepts at most **three** submitted quotes. When the third arrives, every other open invite is closed with "This job is full" and no more waves run. A pro who tries to submit to a full job is told politely, and nothing is saved.
5. While the job is open and their quote is not accepted, the pro can **revise** it: a new version is submitted, the old one becomes `superseded`, and the customer is told it changed. They can also **withdraw** it with a reason: the quote becomes `withdrawn`, the customer is told, and the slot frees up for another quote.
6. Phone numbers, emails, links and bank details typed into line descriptions or notes are masked before the customer sees them (`docs/product/matching.md`, anti-leakage). A pro whose quotes were masked three times gets a flag that admins can see.

**Comparison and acceptance (customer)**
7. Given a job with submitted quotes, then the customer's job page lists them side by side (stacked on a phone), showing for each:
   - the pro's business name, profile photo, verified registrations and time on Sortd;
   - labour, materials and call-out subtotals, VAT and total;
   - the deposit, the earliest start date, the valid-until date and the masked notes;
   - expandable line items.

   Ratings appear when reviews exist (a later spec). Quotes that are withdrawn, superseded or past their validity are not offered.
8. When the customer accepts a quote, with a confirmation that shows the total and deposit, then in one locked transaction:
   - the job moves `open → scheduled` if the deposit is R0, or `open → awaiting_deposit` if it is more (open question 1);
   - the quote becomes `accepted`;
   - every other submitted quote becomes `declined`;
   - every open invite is closed;
   - a `pro_job_allocations` row is written (it feeds the weekly cap).

   The winning pro is told by WhatsApp. The other quoting pros are told politely that the customer chose someone else.
9. After acceptance, the winning pro's job page shows the customer's first name, mobile number, full street address and the property label. Other pros never see these. The customer's job page shows the accepted pro's business name and mobile number. Both sides see "Keep payments on Sortd" guidance.
10. A customer can accept only a submitted, unexpired quote on their own open job. Accepting twice, a withdrawn or superseded quote, or another customer's quote fails safely, and nothing changes.

**Timers**
11. Given a job still `open` 72 hours (setting, `job_timers.quote_window_hours`) after posting with no accepted quote, then the scheduler moves it to `expired` (lifecycle `open → expired`): its submitted quotes become `expired`, its invites close, and the customer gets a "no quote accepted" message with a link to post again. The scheduler is safe to run twice.
12. Given a submitted quote passes its valid-until date, then it becomes `expired` and can no longer be accepted. The pro can submit a fresh version while the job is still open.

**Admin**
13. Admin → Jobs shows the job's quotes, with versions, statuses and totals and the pro's flag count, and the job timeline shows acceptance. Admins can view but not edit quotes.

## Screens / UX
- Pro invite page: the "Send a quote" button opens the builder, with an add-line sheet, a running total, the deposit as a percentage and in rand, and dates.
  - Preview: shows the quote as the customer will see it, plus the pro's estimated payout.
  - Submit: a busy state while sending, then "Quote sent".
  - After sending, the quote card offers Revise and Withdraw.
- Customer job page: "Quotes (2 of 3)" cards, each with "Accept this quote" leading to a confirmation sheet. Once accepted, the page shows the pro's contact and the booked date.
- Empty and states: "Waiting for quotes"; "This quote has expired"; "This job is full" for pros.
- 360 px first; amounts shown as "R 1 234.50".

## Rules and edge cases
- **Quote statuses:** `draft → submitted → accepted | declined | withdrawn | expired | superseded`. Quote actions run with the job row locked. Job status changes only through `ServiceJobStateMachine`, with events `quote_accepted` and `job_expired`.
- **Three-quote cap:** "three quotes" counts current `submitted` versions only. A revision replaces the pro's previous version and doesn't use a new slot.
- **Suspended pros:** if a pro is suspended while their quote is open, the quote stays visible but can't be accepted, and the customer sees "This pro is unavailable".
- **No payment in Phase 3:** the money shown is what will be paid. No payment is taken in this spec.
- **Price limits:** reject a total above R500,000 and any negative amount. Quantities × unit prices are rounded only at the line total.

## Data changes
- New `quotes`, as in the data model: public_id, service_job_id, pro_id, version, status, labour_cents, materials_cents, callout_cents, vat_cents, total_cents, deposit_percent, deposit_cents, earliest_start_date, valid_until, notes, submitted_at, accepted_at, withdrawn_at, withdraw_reason, supersedes_quote_id. Unique (service_job_id, pro_id, version).
- New `quote_lines`: quote_id, kind, description, quantity (decimal 10,2), unit_price_cents, line_total_cents, sort.
- `service_jobs`: add quotes_count (current submitted), accepted_quote_id, scheduled_for (from the earliest start date); the expired status and event come from the lifecycle.
- `pros`: add contact_masking_count.
- Settings: `quotes.max_deposit_percent` (50), `quotes.default_validity_days` (7), `quotes.max_total_cents` (50,000,000), `money.commission_percent` (12, Q1), `money.vat_percent` (15).
- Update `docs/architecture/data-model.md`.

## Security and privacy
- **Policies:**
  - a pro creates, revises and withdraws only their own quote, on an invite of theirs;
  - a customer sees and accepts quotes only on their own job;
  - admins can view only.
- **Contact details** are revealed only to the accepted pro (a policy on the job's contact view), and tested both ways.
- **Money** is integer cents and `Brick\Money` in code. Totals are calculated on the server; there are no floats.
- **Rate limits** on submit, revise and withdraw. Every acceptance, withdrawal and job expiry is in the activity log.

## Out of scope
- Paying deposits and invoices, payouts, the ledger and refunds (Phase 4); the deposit is shown but not collected.
- Ratings and reviews; pro response-rate stats.
- Starting work, completing work, disputes and cancelling after acceptance (later specs).
- PDF quote documents (with payments).

## Decisions (founder, 2026-10-04)
1. **Deposits before payments:** pros may set a deposit now; accepting a quote with a deposit moves the job to "Awaiting deposit" with "Payment opens soon". The 48-hour release timer starts once payments exist (Phase 4).
2. **VAT:** pros with a VAT number add 15% VAT; others add none. Confirm with an accountant before launch.
3. **Estimated payout:** pros see an estimate using 12% commission on labour and call-out, none on materials (Q1/Q2 defaults, admin setting), labelled as an estimate.

## Progress
- 2026-10-04: Drafted after spec 009 merged in PR #32 and the docs refresh in PR #33. Awaiting founder approval and the three decisions above.
- 2026-10-04: Founder approved the spec with all three recommended defaults.
