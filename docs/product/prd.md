# GetSorted — Product Requirements (v2)

Status: v2 · Owner: founder · Last updated: 2026-10-08 · Replaces v1 (2026-10-03)

**What changed from v1:** the MVP launches on **model B** (decision 058): customers get quotes from vetted pros and pay the pro directly; on-platform payments, payouts, disputes and the guarantee move to Phase M after launch. Booking is one conversation with Siya (decisions 045, 050, 051), matching is by trade and distance (051), sign-in is email or Google plus a verified mobile (039), and in-app chat is in scope (045). The name is GetSorted (057).

## 1. Problem

Durban households struggle to find a trustworthy tradesperson quickly. National platforms (Kandua) have thin coverage in eThekwini: a 2026-10-03 probe found **no Kandua pros** in Morningside for electrical fault finding, leak repair, handyman, painting, tiling, paving, carpentry or gate motors, and no fault-finding electricians in any of six Durban suburbs tested (Sandton had them).

Tradespeople, meanwhile, lose 20% of labour to commission and wait 3 business days to be paid.

## 2. Product in one sentence

GetSorted turns a household's description of a problem into a clear job, offers it to vetted Durban pros nearby, collects up to five estimates, and lets the customer chat, compare and choose a pro to come and inspect. **MVP (model B):** choosing a pro is the introduction: contact details unlock and customer and pro arrange the visit, the final quote, the work and the payment directly. GetSorted earns from introductions: free at launch, later a fixed fee paid by the pro (decision 061). **Later (model A, Phase M):** deposit → work → final payment → payout → review all on GetSorted.

## 3. Users and roles

| Role | Who | Main surface |
|---|---|---|
| Customer | A Durban homeowner, tenant or landlord | Public site + signed-in app (`/app`, `/book`) |
| Pro | A vetted tradesperson or small trade business | Signed-in pro pages (`/pros/...`), mobile first |
| Admin | GetSorted staff: support, vetting | Admin panel on its own host (`dashboard.usesorted.co.za`, Filament) |

One account can be a customer or a pro (decision 027); v1 is one login per pro business.

## 4. Scope of the MVP

**Trades:** Plumbing, Electrical, Painting, Tiling (`docs/product/trades/`).

**Area:** requests from all of Durban/eThekwini (decision 044); pros recruited first in Berea/central and North (`docs/product/launch-area.md`). Addresses outside eThekwini go to a waitlist.

### Built

1. **Account and identity:** email + password or Google, then a verified email and SA mobile before booking or quoting (039). Admins: email + strong password; MFA available, optional (047).
2. **Booking with Siya:** one conversation (signed-in only, 056): the customer says what's wrong in their own words; Siya records the trade and short facts using only the customer's words, asks for an address (Google Places), a time (date + Morning / Afternoon / Flexible, or Urgent — today) and optional photos, shows an editable summary, and posts only on Confirm. It works by taps when the AI is off. It never gives prices and never posts on its own.
3. **No safety advice or emergency handling** (decision 060): every message is treated as an ordinary job; Siya does not give safety instructions.
4. **Matching:** up to 10 approved pros of the trade, nearest first, within each pro's travel radius (default 15 km + 2 km soft edge); the first 5 quotes are accepted; invites expire after 24 hours, or 4 hours for urgent jobs (all admin settings; `matching.md`).
5. **Privacy:** pros see the area and approximate distance, the customer's words, facts and photos; never the street address, customer phone or surname until **their** quote is accepted.
6. **Quotes:** itemised lines (labour, materials, call-out), optional deposit, earliest start, validity; revise or withdraw; the customer compares and accepts one. Registrations are shown as verified or not; they are never a gate (051).
7. **After acceptance (model B):** both sides see each other's contact details; any deposit is paid directly to the pro; the copy says GetSorted does not handle payments yet.
8. **Chat:** customer ↔ each quoting pro in the job, with photos; contact details are masked before acceptance (spec 018 part 1).
9. **Notifications:** in-app inbox, browser/phone push (spec 022), email through Resend (038). No WhatsApp or SMS in the MVP (062).
10. **Pro onboarding and vetting:** application (business, trades, base address, radius, ID, proof of address, photo, two references); admin verifies documents and records reference calls before approval is allowed; profile, change requests, pause.
11. **Admin:** vetting queue, jobs, manual invites, trades, matching and AI settings, AI usage, waitlist demand, data requests.

### To build before launch (Phase L, `docs/roadmap.md`)

12a. **Introductions (decision 061):** estimates (range + call-out) instead of quotes, "choose a pro to visit" instead of "accept", a paywall before the introduction (contact details blocked in chat, business name hidden), an introduction record and a fee setting that is off at launch.
12. **Finish a job without money:** mark done, cancel after acceptance with a reason.
13. **Reviews:** customer rates the pro after "done"; the pro can reply once; ratings show on quotes.
14. **Registration capture:** PIRB / electrical registration numbers on the application so pros can be shown as verified.
15. **Operations:** stalled-job list; success measures on the admin dashboard.
16. **Launch readiness:** lawyer-reviewed legal pages, POPIA checklist, monitoring, backups, production host, approved email wording.

### Phase M (after launch)

On-platform payments (deposit, final invoice, card / instant EFT; provider behind an adapter; platform never holds funds in its own account), payouts next business day, commission (Q1, Q2), refunds, PDFs, disputes and the guarantee (Q8), final-amount changes approved by the customer (spec 018 part 2, parked).

### Out of scope (parked)

Native mobile apps · pro team members · subscriptions (unless chosen for Q14) · financing · multi-city · Home Hub / maintenance plans · loyalty · public pro directory (055).

## 5. Success measures

**MVP (first 90 days after soft launch):**

- Time from job posted to first quote: median < 4 hours.
- Jobs receiving ≥ 2 quotes: > 60%.
- Quote → accepted conversion: > 35%.
- Accepted jobs marked done: > 70%; done jobs reviewed: > 40%.

**Phase M (once payments exist):** payments on-platform > 90% of accepted jobs; payout within 1 business day > 95%; disputes per completed job < 3%.

## 6. Non-functional requirements

- **Security and privacy:** `docs/security/`. POPIA compliant from day one; the privacy notice must name Google (Places, Gemini) and Resend before launch.
- **Performance:** pages interactive in < 2 s on a mid-range Android phone on 4G.
- **Availability:** 99.5% monthly for booking.
- **Accessibility:** WCAG 2.2 AA for customer-facing pages; animations respect reduced motion.
- **Language:** English v1; strings translatable for isiZulu later.
- **Mobile first:** every customer and pro flow works one-handed on a 360 px wide screen.

## 7. Key product rules

- Money is stored as integer cents (ZAR). Never floats.
- Every job status change goes through the state machine and is written to the job timeline.
- Pros never see street address, customer phone or surname before their quote is accepted.
- Siya changes booking state only through validated tools, uses only the customer's own words as facts, never quotes prices, and never posts a job (051).
- GetSorted gives no safety advice and has no emergency handling (decision 060).
- Copy never promises what is not built (decision 058).

## 8. Open questions

`docs/product/open-questions.md`.
