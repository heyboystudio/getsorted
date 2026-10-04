# Spec 011 · Join as a pro (sign-up entry)

Status: Approved · Phase: 1 · Owner: founder

## Goal
Tradespeople can sign up as a pro with the same phone + code login customers use (spec 001), so Phase 1's exit criterion "customer **and pro** can sign up with OTP" is met. The full application wizard and vetting stay in spec 008; the pro panel stays locked until then.

## User stories
- As a tradesperson, I want a "Join as a pro" page that explains Sortd and lets me start with my phone number, so signing up is quick.
- As a tradesperson, I want to know what happens next after signing up, so I'm not left wondering.
- As the founder, I want pro sign-ups recorded with their own consent, so we can contact them when the application form opens.

## Acceptance criteria
1. Given the public page `/pros/join`, when I open it, then I see a short explanation ("Get jobs from Durban households…", what Sortd checks, that joining is free — see Q12) and a "Get started" button to `/login?as=pro`.
2. Given `/login?as=pro`, then the number and code steps behave exactly as in spec 001 (same rate limits, decoys, local code display), with the heading "Join Sortd as a pro".
3. Given a new number signing up as a pro, when the code is accepted, then "Tell us about you" asks for first name, surname, email (optional) and requires the terms, the privacy notice **and the pro agreement**; marketing stays optional and unticked.
4. When that step is completed, then the user gets the `pro` role (not `customer`), and consents are recorded for terms, privacy and `pro_agreement` with version, time, IP and browser.
5. Given an existing customer signs up as a pro with the same number, when the code is accepted, then they are asked only to accept the pro agreement, and the `pro` role is **added** to their account (they stay a customer). *(See open question 1.)*
6. Given a pro (with or without the customer role) logs in, then they land on `/pros/welcome`: "Thanks, {first name} — your application form opens soon. We'll WhatsApp you when it does." with a log-out button.
7. Given a pro who is also a customer, then `/app` still works for them and `/pros/welcome` links to it.
8. Given anyone, then `/pro` (the Filament pro panel) still returns 404 — it opens with spec 008.
9. Given a logged-in user without the `pro` role, when they open `/pros/welcome`, then they are sent to `/pros/join`.
10. Admin accounts are refused exactly as in spec 001 (AC18).
11. Given `/pros/agreement`, then a placeholder page marked "Draft — not yet in force" shows version `2026-10-draft`.

## Screens / UX
Mobile first (360 px), same look as the customer login.
1. **`/pros/join`** — heading, three short points (local jobs near you; you set your own prices; Sortd checks ID, registrations and references), "Free to join", "Get started" button. A link to it from the home page footer.
2. **`/login?as=pro`** — spec 001 flow with pro wording; the details step adds the pro agreement checkbox.
3. **Existing customer → pro** — a one-step "Become a pro" screen: the pro agreement checkbox and "Continue".
4. **`/pros/welcome`** — the holding page above.
States as in spec 001 (loading, double-submit protection, friendly errors).

## Rules and edge cases
- `?as=pro` only changes wording, the consents asked for and the role granted; every security rule from spec 001 applies unchanged.
- A pro who signs up via `/login` without `?as=pro` is just a customer; they can add the pro role later via `/pros/join`.
- Abandoning the pro-agreement step leaves an existing customer unchanged.

## Data changes
- `consents.type` gains `pro_agreement` (enum value only; no migration needed — the column is a string).
- No `pros` table yet: business details, services, areas and documents arrive with the application wizard (spec 008).

## Security and privacy
- Same OTP, rate-limit and enumeration protections as spec 001.
- Roles: `pro` grants nothing beyond `/pros/welcome` in this spec; `/pro` stays closed.
- Audit log: "account created" / "pro role granted" and "consent granted".
- PII: as spec 001.

## Out of scope
- Application wizard, documents, references, bank details, vetting and approval (spec 008).
- Opening the `/pro` panel, pro phone login inside Filament.
- Emails or WhatsApp messages to pros about their application.

## Decisions (founder, 2026-10-04)
1. One account can be both customer and pro (one phone number, both roles).
2. Pro agreement: placeholder page "Draft — not yet in force", version `2026-10-draft`; lawyer-reviewed before launch.
3. Business name is collected later, in the application wizard (spec 008).
4. Q12: pros join free ("Free to join").

## Progress
- 2026-10-04: Drafted for founder review.
- 2026-10-04: Approved as proposed.
