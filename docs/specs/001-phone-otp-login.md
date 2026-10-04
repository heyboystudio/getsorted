# Spec 001 · Phone + OTP login and consent

Status: Done (merged in PR #15) · Phase: 1 · Owner: founder

## Goal
Customers sign up and log in with their South African mobile number and a 6-digit one-time code, and new customers accept the terms and privacy notice (POPIA consent recorded). This is the front door for every customer feature that follows.

## User stories
- As a customer, I want to log in with my phone number and a code sent to WhatsApp, so I don't need a password.
- As a customer, I want to get the code by SMS instead if WhatsApp doesn't arrive, so I'm never stuck.
- As a new customer, I want to give only my name (and optionally my email) and accept the terms, so signing up is quick.
- As the founder, I want to test logins on my own computer without a real WhatsApp/SMS provider, without codes leaking into logs.

## Acceptance criteria
**Phone entry**
1. Given the login page, when I enter a valid SA mobile number in any common format (`082 123 4567`, `+27 82 123 4567`, `27821234567`), then it is normalised to E.164 (`+27821234567`) and a code is sent.
2. Given an invalid or non-SA-mobile number, when I submit, then I see "Enter a valid South African mobile number" and no code is sent.
3. Given any number, when I request a code, then the response looks the same whether or not the number already has an account (no account enumeration).

**Sending the code**
4. When a code is sent, then it is 6 random digits, stored only as a hash, expires after 10 minutes, and is delivered through `MessagingChannel` with the `otp_code` template on WhatsApp.
5. When a new code is sent for a number, then any earlier unused code for that number stops working.
6. Given 30 seconds have passed since the code was sent, when I tap "Send by SMS instead", then a new code is sent by SMS (counts toward the rate limit).
7. Given 3 codes were already sent to this number in the last 15 minutes, or 10 from my IP in the last hour, when I ask for another, then I see "Too many codes requested. Try again in N minutes." and nothing is sent (`otp-send` limiter).

**Verifying**
8. Given a valid, unexpired, unused code, when I enter it, then I am logged in, `phone_verified_at` is set, the code is marked used, and the session ID is regenerated.
9. Given a wrong code, when I enter it, then I see "That code is incorrect" with attempts left, and the attempt is counted.
10. Given 5 wrong attempts on a code, when I try again, then the code is invalid and I must request a new one.
11. Given an expired or already-used code, when I enter it, then I see "This code has expired. Request a new one."
12. Code verification is limited per IP (`otp-verify`: 10 per 15 minutes).

**New vs returning customers**
13. Given my number has no account, when my code is accepted, then I see "Tell us about you": first name and surname (required), email (optional), "I accept the terms of service" and "I accept the privacy notice" (both required, unticked), and "Send me tips and offers" (optional, unticked).
14. When I complete that step, then a user is created with role `customer`, and one `consents` row per accepted item stores type, document version, time, IP and browser.
15. Given my number already has an account, when my code is accepted, then I go straight to my account home (`/app`).
16. Given I tick "Keep me logged in", then I stay logged in for 30 days; otherwise the normal session timeout applies.
17. When I log out, then my session ends and I return to the home page.

**Safety**
18. Given a number belongs to a user with any admin role, when a code is requested or entered, then phone login refuses it exactly like an unknown failure. Admins must use `/admin` with email, password and MFA. *(Phone and admin login share a session, so allowing it would bypass MFA.)*
19. Logs never contain codes or full phone numbers (masked as `+278******67`).
20. **Local development only:** the code-entry screen shows "Development: your code is 123456" so the founder can test without a provider. This never appears in testing, staging or production (tested).

## Screens / UX
Mobile first, 360 px.
1. **`/login` — Your number**: one field (`tel` keyboard), "Send code" button (disabled + spinner while sending). Error text under the field.
2. **Enter code**: "We sent a code to +27 82 *** 4567 on WhatsApp." Six-digit field (`one-time-code` autocomplete), "Verify". "Send by SMS instead" appears after a 30 s countdown. "Wrong number?" goes back. Errors under the field; rate-limit message with minutes left.
3. **Tell us about you** (new customers only): first name, surname, email (optional), consent checkboxes with links to `/terms` and `/privacy`, "Keep me logged in", "Create account".
4. **`/app` — Account home**: placeholder "Hi Thandi 👋 — your jobs will appear here" with a log-out button (real content comes in Phase 2).
States: loading on every button (`wire:loading`), double-submit disabled, friendly error if the messaging service fails ("We couldn't send your code. Try SMS or try again shortly.").

## Rules and edge cases
- One active code per number; requesting again replaces it.
- Codes are compared in constant time.
- If sending fails, the code is discarded and the user can retry (subject to rate limits).
- The "tell us about you" step must be completed within the same session; abandoning it creates no account.
- An account soft-deleted or anonymised cannot log in (deleting accounts comes in a later spec).

## Data changes
Update `docs/architecture/data-model.md` in the same PR.
- `users`: add `public_id` (ULID, unique), `first_name`, `last_name`, `phone_e164` (unique, nullable for admins), `phone_verified_at`, `locale` (default `en`), `deleted_at`; make `email` and `password` nullable; replace `name` with `first_name` + `last_name` (existing admin account migrated by splitting its name).
- New `phone_otps`: `phone_e164`, `code_hash`, `channel` (whatsapp/sms), `purpose` (login), `expires_at`, `attempts`, `consumed_at`, `ip`, timestamps.
- New `consents`: `user_id`, `type` (terms/privacy/marketing), `version`, `granted_at`, `withdrawn_at`, `ip`, `user_agent`.
- Scheduled prune: OTP rows older than 90 days (POPIA retention).
- Remove the closed starter routes test for `/login` (it becomes the real login page); keep the others.

## Security and privacy
- PII: phone number, names, optional email, IP, user agent. Phone and email are never shown in URLs or logs.
- Policies: customers can only see and edit their own account; `/app` requires login with a verified phone; customers are kept out of `/admin` and `/pro` (already tested).
- Rate limits: `otp-send` and `otp-verify` (decision 025). Filament admin login is unaffected.
- Audit log: account created, consent granted.
- Codes: hashed, 10-minute expiry, 5 attempts, single use (security baseline §1).

## Out of scope
- Real WhatsApp/SMS provider (Q5); the fake channel is used until a hosted environment exists.
- Pro sign-up and application (pros will use the same phone login; their application wizard is spec 008).
- Editing profile, changing phone/email, deleting the account (C6 account spec).
- Final legal wording of the terms and privacy notice.

## Decisions (founder, 2026-10-03)
1. **Terms and privacy text:** placeholder pages marked "Draft — not yet in force", version `2026-10-draft`; lawyer-reviewed text before launch.
2. **Local testing:** show the code on the code-entry screen in local development only (AC 20).
3. **Pros:** this spec covers customers; a follow-up adds "Join as a pro" on the same login, pro panel locked until spec 008.
4. **Q5 (WhatsApp provider):** still open; not needed to build this spec.

## Progress
- 2026-10-03: Drafted for founder review.
- 2026-10-03: Approved by the founder with the proposed answers.
- 2026-10-04: Built on `feat/001-phone-otp-login`. Every AC has tests (`tests/Feature/Auth/PhoneLoginTest.php`). Reviewer agents (spec, code, security) found admin-number enumeration, a crash for deleted accounts, AC11 wording for used codes, email enumeration and an MFA gap for later-promoted admins — all fixed with tests. Decision 026 records the implementation choices.
- Small deviations: "Keep me logged in" sits on the code step (so returning customers get it too); a verified number must finish sign-up within 15 minutes; an email already used by another account is silently not stored, so the form never reveals it.
- Follow-ups (not in this spec): trusted-proxy settings with hosting (step 12), OTP-send spike alerts with Sentry/monitoring, email verification, "Join as a pro" spec.
