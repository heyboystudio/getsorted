# Spec 014 · Sign up and sign in with email or Google, then verify your phone

Status: Draft · Phase: 1 (revisits spec 001) · Owner: founder

## Goal
Customers and pros create an account with **email and password** or **Continue with Google**, and sign in the same way. A verified mobile number is still required before anyone can post a job or quote, but it is checked *after* sign-up with a one-time code. It is no longer the way in. This replaces decision 004's "phone + code login" for customers and pros. Admin sign-in (email, password and an authenticator app) is unchanged.

## User stories
- As a new customer, I want to sign up with my email or Google account, like on other sites I use.
- As a returning user, I want to sign in with my email and password or with Google.
- As the founder, I want every customer and pro to have a verified South African mobile number before they post or quote, because WhatsApp updates and pro contact depend on it.

## Acceptance criteria
**Sign up**
1. `/register` asks for first name, last name, email, password (at least 10 characters, checked against known breached passwords) and acceptance of the terms and privacy notice. Consent is recorded with the version and time, as in spec 001. An email that is already registered is told to sign in instead, without revealing more.
2. "Continue with Google" (Google OAuth through Laravel Socialite) creates the account from Google's verified email, first and last name. If the email already has a password account, the user must first sign in with their password once to link Google (no silent account takeover).
3. After sign-up the user is signed in and taken to **Verify your mobile**.

**Verify mobile**
4. The user enters a South African mobile number. A 6-digit code is sent by WhatsApp, with "Send by SMS instead" after 30 seconds. This reuses spec 001's code rules: expiry, attempt limits, hashing, rate limits and the daily cap.
5. When the code matches, `phone_verified_at` is set. A number already verified on another account is refused with "This number is already linked to another account. Sign in to that account or contact support." Checking the number again doesn't show whose account it is.
6. Until the mobile is verified, a user can browse and save a booking draft, but **posting a job, applying as a pro or quoting** sends them to Verify your mobile first, then back to where they were.
7. A user can change their mobile from their account page. The new number needs a fresh code, and the old number stays until the new one is verified.

**Sign in**
8. `/login` offers email and password, and "Continue with Google". Wrong details show one generic message. Limits: 5 tries per email per 15 minutes and 20 per IP per hour. The session is regenerated on sign-in.
9. "Forgot password" emails a reset link (Laravel's reset broker, 60 minutes, sent through Resend). It always says "If that email has an account, we've sent a link."

**Email**
10. Email addresses are verified with a link (open question 2). Until verified, the account page shows a reminder.

**Existing accounts and pages**
11. Existing phone-only accounts (test data only, as there are no real users yet) are handled per open question 3.
12. "Join as a pro" (spec 011) uses the same sign-up, then phone verification, then the pro application.
13. Admin sign-in at `/admin` is unchanged. Admins can't use Google sign-in.

## Screens / UX
- `/register`: name fields, email, password with show/hide, consent checkboxes, "Create account", "or Continue with Google", and "Already have an account? Sign in".
- `/login`: email, password, "Sign in", "Forgot password?", "or Continue with Google", and "New to Sortd? Create an account".
- Verify your mobile: number field (+27), "Send code by WhatsApp", the code screen with resend and SMS fallback, and success. On the preview site the code shows on screen, as now.
- 360 px first; clear errors next to each field.

## Rules and edge cases
- Passwords are hashed with Laravel's default (bcrypt or argon). A password is never logged or emailed.
- Google sign-in requests only `openid email profile`. The Google ID is stored to link future sign-ins.
- An email change requires the current password and re-verification.
- Throttle and consent records from spec 001 stay. The OTP purpose `login` becomes `verify_phone`.

## Data changes
- `users`:
  - `password` becomes used by customers and pros;
  - add `google_id` (nullable, unique) and `email_verified_at`;
  - `email` becomes required for new non-admin accounts;
  - `phone_e164` becomes nullable until verified, staying unique when set.
- Update `docs/architecture/data-model.md`, decision 004 (superseded by a new decision), and spec 001's notes.

## Security and privacy
- New dependency `laravel/socialite`, compatibility-checked and logged as a decision.
- Google client ID and secret live only in each server's `.env`.
- CSRF protection on all forms, and the OAuth `state` is checked.
- Activity log entries for sign-up, Google linking, password reset, phone verified and phone changed.
- POPIA: email is now required personal information. The privacy notice draft needs updating, which is flagged for legal review.

## Out of scope
- Apple sign-in, Facebook sign-in, passkeys.
- Two-factor authentication for customers and pros (admins already have it).
- Real WhatsApp or SMS delivery: Twilio SMS and the Meta WhatsApp connection are their own specs. Until then, codes use the fake channel (shown on screen on the preview site).

## Open questions
1. **Phone + code as an extra way to sign in** for people who prefer it? **Recommended: no**, to keep one clear path. Phone verification still happens after sign-up.
2. **Must email be verified before posting a job?** **Recommended: send a verification link but don't block posting.** The phone is the verified contact we rely on, so blocking on email adds friction for little gain. Alternative: block until verified.
3. **Existing test accounts** on the test site and local machines: **recommended: reset the test database** (fake data only), rather than building a migration path for phone-only accounts.

## Progress
- 2026-10-04: Drafted at the founder's request ("phone number isn't the primary method"). Planned to build before payments (spec 013), since sign-up is the first screen. Awaiting founder approval and the three decisions above.
