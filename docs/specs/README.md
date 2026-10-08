# Specs

One file per feature: `NNN-short-name.md`, created with `/spec` from `docs/templates/feature-spec.md`. A spec must be **Approved** by the founder before `/build-feature` will build it. Phase S (feature freeze, decision 058) finished on 2026-10-08.

Statuses checked against the code on 2026-10-08. "Superseded" means a later spec or decision replaced the design; the file is kept as history.

| # | Spec | Status | Phase |
|---|---|---|---|
| 001 | [Phone + OTP login and consent](001-phone-otp-login.md) | Built, then superseded by 014 for customers and pros | 1 |
| 002 | Roles and Filament panel access | Built in Phase 0 (decisions 021, 022) | 1 |
| 003 | [Catalogue from scoping YAML](003-catalogue.md) | Built, superseded by 020 (trades only) | 1 |
| 004 | [Suburbs and properties](004-suburbs-and-properties.md) | Built; suburbs removed by 020 (Google Places addresses) | 1 |
| 005 | [Booking flow](005-booking-flow.md) | Built, superseded by 017 | 2 |
| 006 | [Coverage check + waitlist](006-coverage-check-and-waitlist.md) | Built; open mode since decision 044; waitlist only outside eThekwini | 2 |
| 007 | [AI scoping assistant](007-ai-scoping-assistant.md) | Built, superseded by 016 / 020 | 2 |
| 008 | [Pro application and vetting](008-pro-application-and-vetting.md) | Built | 3 |
| 009 | [Matching and invite waves](009-matching-and-invite-waves.md) | Built, waves superseded by 020 | 3 |
| 010 | [Quotes, comparison and acceptance](010-quotes-and-acceptance.md) | Built (max quotes now 5, decision 051) | 3 |
| 011 | [Join as a pro](011-join-as-a-pro.md) | Built | 1 |
| 012 | [Job photos](012-job-photos.md) | Built | 2 |
| 013 | Deposit payments, webhooks and the ledger | Draft, **parked for Phase M** (PR #37, branch `docs/spec-013-deposit-payments`) | M |
| 014 | [Email or Google sign-in, verified phone](014-email-google-sign-in.md) | Built | 1 |
| 015 | [Address autocomplete](015-address-autocomplete.md) | Built | 2 |
| 016 | [Siya, the AI booking assistant](016-ai-booking-assistant.md) | Built, hand-off replaced by 017 | 2 |
| 017 | [One-thread booking](017-one-thread-booking.md) | Built; guest flow removed by decision 056 | 2 |
| 018 | [Chat with pros, estimates, final amount](018-pro-chat-and-final-amount.md) | Part 1 (chat, estimates) built; part 2 (final amount) **parked for Phase M** on branch `feat/018-final-amount` | 3 / M |
| 019 | [Siya conversation redesign](019-siya-conversation-redesign.md) | Built (PR #55's later commits superseded by 020) | 2 |
| 020 | [Trades, facts, distance matching, Siya agent](020-trade-and-distance-matching.md) | Built (PR #57) | 2–3 |
| 021 | [Customer and pro signed-in panels](021-signed-in-panels.md) | Built (PR #58) | 2–3 |
| 022 | [Web push and live inbox](022-web-push-notifications.md) | Built (PR #61) | 3 |
| 023 | [Estimates, introductions and PayFast credit](023-introductions-and-payfast-credit.md) | Built (PR #76); fee switched off | L |

## Next specs (Phase L, not started)

| # | Spec | Notes |
|---|---|---|
| 024 | Finish a job without payments | Mark done, cancel after acceptance; no job stuck in "Awaiting deposit" |
| 025 | Reviews | After "done"; one pro reply; shown on quotes |
| 026 | Registration capture and verified badges | PIRB / electrical registration numbers |
| 027 | Operations: stalled jobs and success measures | Admin queue and dashboard metrics |
