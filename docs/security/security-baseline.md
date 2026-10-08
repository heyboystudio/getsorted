# Security baseline

These rules are **non-negotiable**. Claude Code must follow them in every change and the `security-reviewer` agent checks them before every merge. If a task seems to require breaking one, stop and ask.

## 1. Authentication

- Customers and pros: phone + 6-digit OTP. Code stored **hashed**, expires in 10 minutes, max 5 attempts, single use.
- Rate limits: 5 sign-up attempts per IP per 15 min; exponential back-off. No codes are sent by SMS or WhatsApp (decision 062).
- Admins: email + strong password; Filament app-based MFA is **optional** (each admin can switch it on in their profile; decision 047 dropped the requirement). Admin panel can additionally be IP-restricted.
- Sessions: secure, HTTP-only, SameSite=Lax cookies; regenerate on login; idle timeout 2 h for admins, 30 days remember-me for customers/pros.
- Changing phone or email requires verifying the new one; notify the old one.

## 2. Authorisation

- **Every model has a Policy.** Every Livewire action, Filament resource/page/action and controller method authorises explicitly. "Hidden button" is not authorisation.
- Default deny: Filament `canAccessPanel()` checks role + status (pros must be `approved`; admins need an admin role).
- Data scoping: queries for customers/pros always filter by owner (`whereBelongsTo($user)`); never trust an ID from the request without a policy check.
- A pro only sees street address and customer contact after **their** quote is accepted (enforced in the Policy and in the API resource/view model, and tested).
- Admin roles are least-privilege: support, vetting, finance, super. Refunds over a configurable amount need a second admin's approval.

## 3. Input and output

- Validate every input with Form Requests / Livewire validation rules. Use `$validated`, never `$request->all()`.
- Mass assignment: every model defines `$fillable` (never `$guarded = []`).
- Output: Blade `{{ }}` escaping only; `{!! !!}` is banned except for sanitised, reviewed content.
- File uploads: allow-list MIME types (jpeg, png, webp, heic, pdf), max size, re-encode images, strip EXIF location, store on a **private** disk, serve via short-lived signed URLs only.
- No raw SQL with string interpolation; use the query builder / bindings.

## 4. Money and webhooks

- Prices and totals are **calculated on the server** from quote lines. Never trust an amount sent from the browser.
- Payment status changes only from **signature-verified webhooks** (or a server-side status fetch), never from a redirect URL.
- Webhooks: verify signature → store in `webhook_events` (unique provider event id) → process in a queued job → idempotent.
- Payouts and refunds use idempotency keys and are written to the ledger in the same transaction as the status change.

## 5. Secrets and configuration

- Secrets live in environment variables / the host's secret manager. `.env` is never committed, never read by AI tools (blocked in `.claude/settings.json`), never pasted into chat.
- Separate keys for local, staging and production. Production keys are never on a developer machine.
- `APP_DEBUG=false` outside local. No stack traces to users.
- Rotate keys if they ever appear in a log, commit or chat.

## 6. Data protection

- Encrypt at the column level: ID numbers, bank account numbers, registration numbers, street addresses.
- Logs must not contain OTPs, tokens, full phone numbers (mask middle digits), ID numbers, bank details or street addresses.
- HTTPS only with HSTS; security headers (CSP, X-Frame-Options/frame-ancestors, Referrer-Policy, Permissions-Policy).
- Backups encrypted; restore tested at least quarterly.
- See `popia.md` for retention, consent and data-subject rights.

## 7. AI-specific rules

- Customer text sent to the AI model is **untrusted data**. Put it in a clearly delimited data field; never let it change system instructions.
- AI output is parsed into a typed structure and validated (allowed trade/service keys, max lengths). Invalid output → fall back to manual selection.
- The model never receives phone numbers, street addresses, ID numbers or payment data.
- The AI cannot trigger state transitions, send messages or spend money by itself. It only suggests; a human action or a deterministic rule acts.
- Log AI usage (tokens, latency, outcome) without storing raw customer text beyond 30 days.

## 8. Dependencies and supply chain

- `composer audit` and `npm audit` run in CI; high/critical findings block merging.
- Dependabot weekly; patch updates merged after CI passes.
- Every new dependency gets a decisions-log entry: why, maintainer health, licence, alternatives.

## 9. Operations

- Error tracking with PII scrubbing; uptime monitoring on `/up`, booking and webhook endpoints.
- Alerts: failed payouts, webhook signature failures, reconciliation mismatches, OTP send spikes, queue backlog.
- Admin actions written to the audit log (who, what, before/after, when, IP).
- Incident response: see `popia.md` (breach notification) — keep a one-page runbook.

## 10. Before launch (and yearly)

- Paid external review of auth, payments and file handling by a senior Laravel developer or security tester.
- OWASP ASVS Level 1 checklist completed.
- Restore-from-backup drill done.
