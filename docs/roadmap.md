# Roadmap

Phases are done when their **exit criteria** pass, not when a date arrives. Rewritten 2026-10-08 after the audit and decision 058 (launch on model B: quotes, then hand-off; payments after launch). The original Phase 0–6 plan is kept at the bottom as history.

## Where we are (2026-10-08)

| Area | State |
|---|---|
| Accounts | Email or Google sign-up, verified email and an SA mobile number saved without a code (decisions 039, 062). Admin on its own host, MFA optional (047). |
| Booking | Siya, one thread: trade, facts in the customer's own words, Google Places address, time, photos, summary, confirm (specs 017, 019, 020). Works by taps when the AI is off. Signed-in users only (056). |
| Matching | Up to 10 approved pros of the trade, nearest first, within each pro's radius; first 5 quotes; urgent invites expire after 4 hours (settings). |
| Pros | Application, documents, references, admin vetting with a real approval guard, profile and change requests, pause. |
| Quotes | Itemised quotes, revise and withdraw, customer compares and accepts; contact details shared on acceptance. |
| Comms | Job chat, notifications inbox, web push (spec 022), email through Resend. No WhatsApp or SMS (decision 062). |
| Money, reviews, disputes | **Not built** (`app/Domain/Payments`, `Reviews`, `Disputes` are empty). Customers pay pros directly (058). |
| Quality | 883 tests pass; `composer check` passes locally. GitHub CI is blocked by a billing lock on the GitHub account (decision 020). |
| After acceptance | **Dead end:** nothing moves a job past "Awaiting deposit" or "Booked". Phase L fixes this. |

Roughly: the old Phases 1–3 are built (redesigned), Phase 4 is moved to M, Phase 5 is about a quarter done, Phase 6 has barely started.

---

## Phase S · Stabilise (feature freeze) — done (2026-10-08)

Goal: what exists is correct, honest and documented before anything new is built.

| # | Task | State |
|---|---|---|
| S1 | One repo folder, one branch line, rescue uncommitted work | Done (PRs #61, #62; spec 018 part 2 parked on `feat/018-final-amount`) |
| S2 | Rename everything to GetSorted (decision 057) | Done: code, local machine and test server (2026-10-08; `~/getsorted`, database `getsorted`, `main` deployed) |
| S3 | Safety net: separate dev and test databases, `composer check` passing locally | Done (#63). CI stays manual: GitHub billing fix on hold (founder, 2026-10-08). |
| S4 | Fix the audit bugs | Done (#64) |
| S5 | Honest copy for model B, real contact details | Done (#65) |
| S6 | Docs match the product: PRD v2, roadmap, decisions index, specs index, agent instructions | Done (#66) |
| S7 | Tidy GitHub: close superseded PRs, archive old branches | Done: PRs #49, #52, #53, #55, #60 closed; old branches kept as `archive/*` tags; open branches are `main`, `feat/018-final-amount`, `docs/spec-013-deposit-payments` |
| S8 | Remove Twilio; email only (decision 062) and a full click-through on the test site | Done (#74, 2026-10-08). Click-through passed on usesorted.co.za: customer booked, pro quoted, customer accepted. WhatsApp wording replaced with email. |

**Exit criteria:** `composer check` green; docs describe the app as it is; one `main`; test server renamed and deployed from `main`; a full click-through (customer books → pro quotes → customer accepts) passes on the test site (passed 2026-10-08).

---

## Phase L · Launch the MVP on model B — next (about 2–3 weeks)

Goal: real customers get real quotes from real pros in a few Durban suburbs, and every job can finish.

1. **Close the loop without money (spec 024, built 2026-10-08).** Customer or pro marks the job done (`Scheduled` → `Completed` through the state machine); the customer can cancel after acceptance with a reason; jobs never sit in "Awaiting deposit" forever. Needs a spec.
2. **Reviews (spec 025, built 2026-10-08).** After "done", the customer rates the pro (1–5 + comment), the pro can reply once; ratings show on quotes. Needs a spec.
3. **Supply.** Registration fields on the pro application are built (specs 008, 020, 021); recruit 5–10 approved pros per trade in 2–3 launch suburbs; then set `GETSORTED_REQUIRE_PROS=true` so nobody is promised quotes that cannot come.
4. **Operations (spec 027, built 2026-10-08).** Admin list of stalled jobs (no quote after N hours, urgent with no reply); the PRD's success measures on the admin dashboard (time to first quote, jobs with ≥ 2 quotes, quote → accept).
5. **Launch readiness.** Full drafts of the terms, privacy notice and pro agreement exist (2026-10-08, `docs/security/legal-drafting-notes.md`); a lawyer must review them. The production move is planned in `docs/engineering/production-move.md`; recruiting in `docs/product/pro-recruitment-pack.md`. Still needed: lawyer-reviewed terms, privacy notice (naming Google, Gemini, Resend) and pro agreement; POPIA checklist done; Sentry error tracking; database backups tested; South African production host decided (decision 014); admin MFA stays optional (founder, 2026-10-08).
6. **Introductions (decision 061, spec 023, built 2026-10-08).** Pros send estimates (range + call-out); the client chooses a pro to visit, which unlocks contact details; before that, a paywall (contact details blocked in chat, business name hidden). Record every introduction; the fee setting stays off at launch ("first 10 jobs free"). PayFast credits come when the fee is switched on.

**Exit criteria:** soft launch in 2–3 suburbs; the first 20 real jobs posted; ≥ 60% get 2+ quotes; every accepted job can be marked done and reviewed; no known P1 bugs.

---

## Phase M · Money on-platform (model A) — after launch

Goal: the PRD's original business: payments on GetSorted, commission, next-day payouts.

- Decide the provider (Q4) and money rules (Q1–Q3, Q6–Q8).
- Revive spec 013 (deposit payments, PR #37) as the first slice: deposit on acceptance, verified webhooks, append-only ledger.
- Hold the pro's contact details until the deposit is paid.
- Port the parked final-amount work (spec 018 part 2, branch `feat/018-final-amount`).
- Final payment, payouts, refunds, PDFs; disputes and the guarantee (Q8).

**Exit criteria:** > 90% of accepted jobs paid on-platform; payouts within 1 business day (> 95%); external security review done.

---

## Later

Public SEO pages per suburb × trade, pro performance dashboards, more trades, isiZulu, native app via API.

---

## History: the original plan (2026-10-03)

| Phase | Goal | Outcome |
|---|---|---|
| 0 · Foundations | Production-grade empty app | Done except CI (billing lock, decision 020) and staging (014); a private test site exists instead (037, 046). |
| 1 · Accounts & catalogue | Phone login, catalogue, properties | Done, then redesigned: email/Google sign-in (039); trades and facts replaced the catalogue (051). |
| 2 · Booking | Booking flow, coverage, AI, photos | Done, then redesigned as one Siya thread (017, 019, 020). |
| 3 · Pros & quotes | Vetting, matching, quotes | Done, plus chat, panels and push. Waves replaced by distance matching (051). |
| 4 · Payments | Money on-platform | Not started; moved to Phase M (058). |
| 5 · Trust & comms | WhatsApp, reviews, disputes | Partly (notifications by email and push; no WhatsApp/SMS); reviews in Phase L, disputes in M. |
| 6 · Launch readiness | Legal, POPIA, backups, monitoring | Folded into Phase L. |
