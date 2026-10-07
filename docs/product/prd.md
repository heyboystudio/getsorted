# Get Sorted — Product Requirements (v1)

Status: Draft v1 · Owner: founder · Last updated: 2026-10-03

## 1. Problem

Durban households struggle to find a trustworthy tradesperson quickly. National platforms (Kandua) have thin coverage in eThekwini: a 2026-10-03 probe found **no Kandua pros** in Morningside for electrical fault finding, leak repair, handyman, painting, tiling, paving, carpentry or gate motors, and no fault-finding electricians in any of six Durban suburbs tested (Sandton had them).

Tradespeople, meanwhile, lose 20% of labour to commission and wait 3 business days to be paid.

## 2. Product in one sentence

Get Sorted turns a household's description of a problem into a clear, scoped job, invites vetted Durban pros in waves to collect up to three quotes, and runs quote → deposit → work → final payment → review in one place, with WhatsApp updates throughout.

## 3. Users and roles

| Role | Who | Main surface |
|---|---|---|
| Customer | A Durban homeowner or tenant | Public website + customer web app (`/app`) |
| Pro | A vetted tradesperson or small trade business | Mobile web pages (`/pros/...`) |
| Admin | Get Sorted staff: support, vetting, finance | Admin panel (`/admin`, Filament panel) |

Pros may later have team members; v1 is one login per pro business.

## 4. Scope of v1

**Trades (demo services for now — see `docs/product/scoping/`):** Plumbing, Electrical, Painting, Tiling.

**Launch area:** a short list of eThekwini suburbs (`docs/product/launch-area.md`). Requests outside it get a waitlist, not an error.

### In scope

1. **Account & identity**: phone-number login with one-time code (WhatsApp first, SMS fallback). Customers and pros verify a mobile number before they can post or quote. Admins use email + password + mandatory two-factor auth.
2. **Guided booking**: pick trade → service → scoping questions (tap answers, optional free text) → property → preferred date + time window → optional photos → summary → post.
3. **AI-assisted scoping**: an assistant turns free text into a structured job summary and suggests the trade/service. It never invents prices, never posts a job on its own, and its output is validated before use.
4. **Properties**: saved addresses with suburb autocomplete and map pin. Street address is hidden from pros until a quote is accepted.
5. **Coverage check before details**: tell the customer early whether pros cover that service in their suburb.
6. **Matching**: invite vetted pros who offer the service and cover the property's location; collect **up to 3 quotes** automatically.
7. **Quotes**: pros build itemised quotes (labour vs materials lines, call-out fee, validity period). Customer compares up to 3 side by side and accepts one.
8. **Payments** (provider TBD behind an adapter): optional deposit, final invoice, card / instant EFT / Apple Pay / Google Pay where the provider supports them. Platform never holds customer funds in its own bank account.
9. **Payouts**: next business day after the customer confirms completion (or after an auto-confirm window). Commission (target 10–12% of labour) deducted at payout.
10. **Job tracking**: one job page with stage tracker, quotes, invoices, documents, timeline, WhatsApp button, cancel-with-reason.
11. **Notifications**: WhatsApp templates for key events, email receipts, in-app status.
12. **Reviews**: customer rates the pro after completion; pro can reply once.
13. **Disputes & guarantee**: customer can raise an issue before or after completion; funds not yet paid out are frozen; admin resolves with a recorded outcome.
14. **Pro onboarding & vetting**: application, ID, trade registrations (PIRB for plumbers; registered-person / CoC ability for electricians), references, bank details, service areas, services offered. Admin approves before a pro sees any job.
15. **Admin**: vetting queue, manual matching override, job oversight, refunds, disputes, payouts, content for scoping trees, audit log, reports.
16. **Public website**: home, how it works, trade pages, suburb × trade SEO pages, join as a pro, help/FAQ, legal pages.

### Out of scope for v1 (parked)

Native mobile apps · pro team members · subscriptions · financing · in-app chat beyond WhatsApp deep links · multi-city · Home Hub / maintenance plans · loyalty.

## 5. Success measures (first 90 days after launch)

- Time from job posted to first quote: median < 4 hours.
- Jobs receiving ≥ 2 quotes: > 60%.
- Quote → accepted conversion: > 35%.
- Payments taken on-platform: > 90% of accepted jobs.
- Pro payout within 1 business day of completion: > 95%.
- Disputes per completed job: < 3%.

## 6. Non-functional requirements

- **Security & privacy**: see `docs/security/`. POPIA compliant from day one.
- **Performance**: pages interactive in < 2 s on a mid-range Android phone on 4G.
- **Availability**: 99.5% monthly for booking and payments.
- **Accessibility**: WCAG 2.2 AA for customer-facing pages.
- **Language**: English v1; copy written so isiZulu can be added later (all strings translatable).
- **Mobile first**: every customer and pro flow must work one-handed on a 360 px wide screen.

## 7. Key product rules

- Money is stored as integer cents (ZAR). Never floats.
- Every job status change goes through the state machine and is written to the job timeline.
- A pro sees: trade, service, scoping answers, suburb, date window, photos. Not: street address, customer phone, customer surname — until their quote is accepted.
- Customers and pros must not be pushed to pay or be paid off-platform; the UI warns against it.
- The AI assistant gives guidance, not guarantees, and says so for safety-critical topics (electrical, gas).

## 8. Open questions

See `docs/product/open-questions.md`.
