# Open questions

Decisions the founder still needs to make. Claude Code must **not** decide these silently: if a task depends on one, ask, or build it as a configurable setting with the default shown.

Updated 2026-10-08 for decision 058 (model B now, payments in Phase M). "Phase" says when an answer is needed.

## Needed before launch (Phase L)

| # | Question | Default until decided | Blocks |
|---|---|---|---|
| Q14 | How does GetSorted earn money on model B (no on-platform payments)? Lead fee per accepted quote, pro subscription, or nothing until Phase M? | Nothing charged | Pricing copy, pro agreement, launch |
| Q11 | Brand: is "GetSorted" cleared (CIPC, trademark)? The domain is usesorted.co.za. | Working name | Public launch |
| Q15 | Customer-facing domain: stay on usesorted.co.za, or also get a getsorted domain? | usesorted.co.za | Marketing, email sender |
| Q13 | Business hours for support and SLA for complaints | Weekdays 08:00–17:00, 2 business days | Admin, legal pages |
| Q16 | Admin MFA: switch back to required before real customer data (decision 047 says revisit)? | Optional | Launch readiness |
| Q10 | Production host (South African provider, decision 014). The test site is on AWS Cape Town (046). | Undecided | Production deploy |

## Needed for Phase M (payments)

| # | Question | Default until decided | Blocks |
|---|---|---|---|
| Q4 | Which payment provider? Shortlist in `money-flow.md`: Paystack, Peach, Ozow, PayFast | Fake gateway in dev | All of Phase M |
| Q1 | Commission rate: 10%, 11% or 12% of labour? Is the call-out fee included? (The old quote preview assumed 12% of labour + call-out; removed in PR #65.) | 12% of labour, admin setting | Payouts |
| Q2 | Commission on materials? | No | Payouts |
| Q3 | Customer booking/service fee? | R0, admin setting | Checkout |
| Q6 | Deposit release timing: at job start, or earlier for materials-heavy jobs? | At job start | Payouts |
| Q7 | Cancellation fees for late customer cancellation? | Call-out fee only | Refunds |
| Q8 | Guarantee terms: what does GetSorted promise, and up to what amount? | Rework or refund decided by admin, 30-day window | Disputes, marketing copy |

## Decided

| # | Question | Answer |
|---|---|---|
| Q5 | WhatsApp provider | Twilio for WhatsApp and SMS (decision 040, 2026-10-05) |
| Q9 | Launch area | Requests from all of Durban/eThekwini (decision 044); recruit pros first in Berea/central and North (spec 004) |
| Q12 | Do pros pay to join? | Free (spec 011, 2026-10-04) |
| — | Payments at launch? | No: model B, payments in Phase M (decision 058, 2026-10-08) |
| — | Product name | GetSorted, one word (decision 057, 2026-10-07) |
