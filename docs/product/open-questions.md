# Open questions

Decisions the founder still needs to make. Claude Code must **not** decide these silently: if a task depends on one, ask, or build it as a configurable setting with the default shown.

Updated 2026-10-08 for decision 058 (model B now, payments in Phase M). "Phase" says when an answer is needed.

## Needed before launch (Phase L)

| # | Question | Default until decided | Blocks |
|---|---|---|---|
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
| Q5 | WhatsApp provider | None in the MVP: emails only through Resend (decision 062, 2026-10-08) |
| Q9 | Launch area | Requests from all of Durban/eThekwini (decision 044); recruit pros first in Berea/central and North (spec 004) |
| Q12 | Do pros pay to join? | Free (spec 011, 2026-10-04) |
| — | Payments at launch? | No: model B, payments in Phase M (decision 058, 2026-10-08) |
| — | Product name | GetSorted, one word (decision 057, 2026-10-07) |
| Q14 | How GetSorted earns money | Introductions: free at launch, later a fixed fee paid by the pro to unlock a client; "first 10 jobs free"; clients never pay GetSorted (decision 061, 2026-10-08) |
| Q11 | Company registration and trademark | Not now: the company will be registered later; no trademark (founder, 2026-10-08) |
| Q15 | Domain | usesorted.co.za only (founder, 2026-10-08) |
| Q16 | Admin MFA | Stays optional, including with real data (founder, 2026-10-08; decision 047) |
| Q13 | Support hours and response times | Mon–Fri 08:00–17:00 and Sat 08:00–13:00 SAST, closed Sundays and public holidays; first reply within 4 business hours (1 business hour for urgent jobs); complaints answered within 2 business days. On the contact page (founder approved, 2026-10-08) |
| Q17 | Introduction fee for pros | R99 per introduction, paid by the pro; off at launch ("first 10 jobs free"); switched on later (chosen by Claude at the founder's request, 2026-10-08; decision 061) |
| — | GitHub billing lock / CI | On hold by founder choice (2026-10-08); CI stays manual, checks run locally (decision 020) |
