# Spec 009 · Matching and invite waves

Status: Done (merged in PR #32) · Phase: 3 · Owner: founder

## Goal
When a customer posts a job, Sortd invites suitable vetted pros automatically, in waves, so the job collects quotes without the customer doing anything more. Pros get a WhatsApp message and see the job (without the customer's identity or street address), and can open or decline it. Quoting itself is spec 010.

## User stories
- As a customer, I want suitable local pros invited as soon as I post, so quotes start coming in.
- As a pro, I want to be told about jobs that fit my services and suburbs, see enough detail to decide, and decline the ones I can't take.
- As an admin, I want to see who was invited to each job, and invite someone manually or stop inviting when needed.

## Acceptance criteria
**Matching and waves**
1. Given a job is posted (spec 005), then after the commit a queued step invites wave 1: the top **5** eligible pros (setting) from `EligibleProsQuery` for the job's service, the property's suburb and the customer. Each invite gets status `invited`, wave 1 and an expiry **24 h** later (setting), and a `job_invite` WhatsApp message goes to the pro through `MessagingChannel`.
2. Ranking (v1, until ratings and response data exist): pros with the fewest invites in the last 7 days come first, so work rotates fairly and new pros get jobs; ties are broken at random. The same pro is never invited twice to the same job.
3. Given **12 h** (setting) after the latest wave, the job is still `open`, has fewer than 2 quotes, and eligible pros remain uninvited, then the scheduler invites the next **3** (setting) as a new wave. Waves stop when no eligible pros are left. (Until spec 010 adds quotes, the quote count is always 0.)
4. Given an invite reaches its expiry without a quote, then the scheduler marks it `expired`. Running the scheduler twice changes nothing more.
5. Given a job is no longer `open` (cancelled, expired, or later accepted), then its open invites are closed (`closed`) and no new waves run.
6. Eligibility is re-checked at invite time, so a pro who was suspended, lost a registration or stopped covering the suburb in the meantime is skipped.

**What pros see (journey P2)**
7. Given a signed-in approved pro, then `/pros/jobs` lists their open invites (newest first) with service, suburb, preferred date and time window, time left, and an "urgent" badge. Expired, declined and closed invites move to a "Past" tab. Pros who are not approved see their application status instead.
8. Given a pro opens an invite, then they see: trade and service, the scoping answers, the job description and the customer's notes (both with phone numbers, emails, addresses and links stripped), the job photos (signed links), the suburb, the preferred date and time window, how many pros were invited, and how many quotes are in. They never see the customer's name, phone, email or street address. Opening marks the invite `viewed` once.
9. Given an open invite, when the pro taps "Not for me" and picks a reason (too far, too busy, not my kind of work, other with text), then the invite becomes `declined` with the reason. It cannot be reopened.
10. A pro can only see invites addressed to them. Another pro's invite, a job they were not invited to, or a photo of such a job returns not found. Spec 005's job-page rules for customers stay unchanged.

**Admin**
11. Admin → Jobs shows each job's invites (pro business name, wave, status, invited/viewed/responded times, decline reason). Admins with the support or super role can **invite a pro manually** (only eligible pros who were not invited yet; logged) and **stop matching** for a job (no further waves; logged with a reason).
12. Admin → Settings → Matching lets super-admins change wave 1 size (5), later wave size (3), wave interval (12 h), invite expiry (24 h) and the "enough quotes to stop waves" threshold (2).

**Customer**
13. The customer's job page shows "Finding your pros" with how many pros were invited (no names) once matching ran, and "We're still looking" if none could be invited.

## Screens / UX
- `/pros/jobs` (mobile first, 360 px): tabs "New" and "Past", job cards (service, suburb, date/window, time left), empty state "No new jobs right now. We'll WhatsApp you when one fits."
- Invite page: details as AC8, photos in a grid, a sticky bottom bar with "Not for me" (sheet with reasons) and a disabled "Send a quote" button labelled "Quoting opens soon" until spec 010.
- `/pros/welcome` for an approved pro links to "Your jobs".
- Admin job view: an "Invites" table; header actions "Invite a pro" (searchable list of eligible, not-yet-invited pros) and "Stop matching" (reason).
- Errors: an expired or closed invite opened from an old message shows "This job is no longer available".

## Rules and edge cases
- Invite statuses: `invited → viewed → declined | expired | closed` (and `quoted` from spec 010). Each change is an action with the invite locked; job status still changes only through `ServiceJobStateMachine`.
- The wave scheduler runs every 5 minutes, is idempotent (unique job + pro, locked per job) and never invites to a job that is not `open`.
- A job with zero eligible pros at posting cannot happen (spec 006 guard). If every pro becomes ineligible before wave 1, the job stays open with no invites and the customer sees "We're still looking"; admin can invite manually.
- Messages to pros carry only the service and suburb and a link; never customer details.
- The weekly job cap counts accepted jobs (written by spec 010), not invites.

## Data changes
- New `service_job_invites`: service_job_id, pro_id, wave, status, invited_at, viewed_at, responded_at, expires_at, decline_reason, decline_note, invited_by (nullable, admin for manual invites); unique (service_job_id, pro_id); index (status, expires_at).
- `service_jobs`: add `matching_stopped_at`, `matching_stopped_reason`, `last_wave_at`.
- Settings group `matching`: wave_one_size 5, later_wave_size 3, wave_interval_hours 12, invite_expiry_hours 24, enough_quotes 2.
- Update `docs/architecture/data-model.md`.

## Security and privacy
- Policies: an invite is visible to its pro and to admins; the job detail for a pro is a separate, reduced view (never the customer model or property). Photo links re-check the invite.
- Contact details in the description and notes are stripped for pros with the same redactor as spec 007, at display time; stored text is unchanged.
- Rate limits on decline. Activity log for manual invites and stop-matching.
- Opening the `/pro` Filament panel is not needed (open question 1).

## Out of scope
- Quotes, the quote builder, the "3 quotes" stop and job expiry (spec 010).
- Ranking by rating, response rate or distance (needs reviews and response data).
- The pro's own availability, pause and weekly cap screens (journey P4).
- Real WhatsApp delivery (Q5, provider not chosen; the fake channel logs messages).

## Decisions (founder, 2026-10-04)
1. **Where pros work:** pros stay on the mobile pages under `/pros/...`; the separate Filament `/pro` panel is dropped.
2. **Ranking for v1:** fewest invites in the last 7 days first, random tie-break, until ratings exist.
3. **Customer notes for pros:** pros see the job description and the customer's notes, with phone numbers, emails, street addresses and links stripped (also settles the spec 007 follow-up).

## Progress
- 2026-10-04: Drafted after spec 008 merged in PR #29. Awaiting founder approval and the three decisions above.
- 2026-10-04: Founder approved the spec with all three recommended defaults.
- 2026-10-04: Founder approved the build plan.
- 2026-10-04: Build committed on `feat/009-matching`; privacy review also redacted free-text scoping answers on the pro invite page and revoked photo access when a pro is suspended. Local quality gate passed (507 tests, 2,245 assertions), npm audit found no vulnerabilities, and both spec 009 migrations rolled back and reapplied on `sortd_testing`. PR #32 opened for review.
- 2026-10-04: PR #32 merged with founder approval.
