# Spec 005 · Booking flow: service → questions → property → when → post

Status: Draft · Phase: 2 · Owner: founder

## Goal
A customer can describe a job by picking a trade and service, answering that service's scoping questions, choosing a property and a preferred date, and **post** it. Posted jobs appear for admins. This creates the job record and the job state machine that every later feature (coverage, AI, matching, quotes, payments) builds on.

## User stories
- As a customer, I want to tap through a few simple questions about my problem, so pros understand it without a phone call.
- As a customer, I want to pick one of my saved properties (or add one) and a day and time window, so pros know where and when.
- As a customer, I want to review everything before posting, and see a clear confirmation afterwards.
- As an admin, I want to see posted jobs, so I know demand is coming in.

## Acceptance criteria
**Starting**
1. Given the home page, then it shows the active trades as tiles; tapping a trade lists its active services; tapping a service starts a booking for it. (Free-text "describe your problem" is spec 007.)
2. Given a guest starts a booking, then they can answer the questions, but must log in (spec 001 flow, returning to the booking) before choosing a property. Answers are kept across the login.

**Scoping**
3. Given a service, then its questions are shown **one per screen** in order, with tap answers for single/multi choice and yes/no, a number field for `number`, and a text box for `text`; required questions can't be skipped; back works without losing answers.
4. After the questions, an optional "Anything else your pro should know?" box (up to 1 000 characters).
5. Given an answer listed in a question's `urgent_if`, then the job is marked **urgent** and the service's safety advice is shown on that screen.

**Property and timing**
6. The customer picks one of their saved properties or adds one (spec 004 form, returning to the booking). A property in a suburb Sortd isn't in yet stops the booking with "Sortd isn't in {suburb} yet" (the waitlist is spec 006).
7. The customer picks a preferred date within the next 30 days and a window (morning 07–12, afternoon 12–17, flexible). If the service is emergency-capable, "Urgent — today" is offered and sets urgency to urgent.

**Review and post**
8. A summary screen shows service, answers, notes, property (label, suburb, street), date/window and urgency, each with "Change"; "Post job" posts it.
9. Posting moves the job from `draft` to `open` through `ServiceJobStateMachine`, records `posted_at` and `quote_window_ends_at` (now + the job quote window setting, default 72 h), and writes a `service_job_events` row (draft → open, actor = customer).
10. Posting is refused (with a clear message, job stays `draft`) if: the phone isn't verified, a required answer is missing, the property isn't the customer's or its suburb is inactive, or the service is inactive.
11. After posting, a confirmation page shows "Your job is posted" with a summary; a `job_posted` message is sent to the customer through `MessagingChannel` (fake locally) after the database commit.
12. A booking left unfinished stays a `draft` the customer can resume from their account home ("Finish your request"); drafts older than 7 days are cancelled automatically (event row, actor = system).

**Customer account**
13. `/app` lists the customer's jobs (service, suburb, status in plain words, posted date) with an empty state; each opens a read-only job page.
14. A customer can only see their own jobs; URLs use the job's `public_id`.

**Admin**
15. `/admin` → **Jobs** lists jobs (status, service, suburb — **not** street address or customer phone — urgency, posted date) with filters by status and suburb; a job view shows answers, notes and its event timeline. All admin roles can view; nobody can edit jobs here in this spec.

**State machine**
16. `ServiceJobStatus` has all lifecycle states (`docs/product/job-lifecycle.md`); the one transition map in `ServiceJobStateMachine` contains the full diagram, but only `draft → open` and `draft → cancelled` have Actions in this spec. Any other transition attempt throws. Tests iterate the map.

## Screens / UX
Mobile first (360 px), one decision per screen, big tap targets, progress bar ("Step 3 of 7"), back button, autosave of the draft on every step, loading states and double-tap protection on "Post job".

## Rules and edge cases
- Answers are validated against the question type and options on the server (never trust the browser).
- Editing the catalogue later doesn't change a job's stored answers (answers are stored with the question key, prompt and chosen option text at the time).
- One customer may have at most 5 open drafts (config); posting has a rate limit of 10 per day per customer (abuse protection; config).
- Timers come from settings (`spatie/laravel-settings`), not code.

## Data changes
- `service_jobs` (per data-model.md): public_id, customer_id, property_id, service_id, status, urgency, preferred_date, time_window, scoping_answers jsonb, customer_notes, ai_summary (null until spec 007), posted_at, quote_window_ends_at, cancelled_at, cancel_reason, timestamps. (Quote/payment columns arrive with their specs.)
- `service_job_events` (append-only).
- Settings class `JobTimers` (quote window 72 h, draft expiry 7 days) via a settings migration.

## Security and privacy
- Policy: customers see/act on their own jobs only; admins view only.
- Street address is shown to the owner only; admin job screens show suburb, not street (pros come in Phase 3).
- Customer notes may contain personal data; they are shown to the owner and admins only for now (and later to pros, with phone numbers/emails stripped — spec 007/009).
- Audit: the `service_job_events` timeline.

## Out of scope
- Photos (separate spec — see open question 3), free-text + AI (007), coverage check and waitlist (006), invites/matching (009), quotes (010), cancelling a posted job.

## Open questions
1. **Posting before any pros exist.** Until pros are vetted (Phase 3) no job can be matched. Proposal: posting works now (jobs go to `open` and wait; admins see them); the "at least one eligible pro" guard is added with the coverage check (spec 006). OK?
2. **Time windows.** Proposal: Morning 07:00–12:00, Afternoon 12:00–17:00, Flexible; "Urgent — today" only for emergency-capable services. OK?
3. **Photos.** Proposal: a separate small spec right after this one (keeps this PR reviewable). It also needs a decision on iPhone HEIC photos: accept them (needs an extra image library on the server) or ask phones to send JPEG (most do automatically). OK to split?
4. **Job summary for pros.** The AI summary is spec 007; until then the summary is the customer's answers + notes. OK?

## Progress
- 2026-10-04: Drafted for founder review.
