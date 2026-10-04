# Spec 008 · Pro application and vetting

Status: Done (awaiting merge) · Phase: 3 · Owner: founder

## Goal
A tradesperson who signed up as a pro (spec 011) can complete an application, and a vetting admin can check it and approve or reject it. Approval is what makes a pro count for coverage (spec 006) and, later, receive invites (spec 009). Until now no real pro can become approved.

This spec covers the application and vetting only. Bank details move to the payments phase (open question 1), and the pro's own profile/pause/cap screens (journey P4) and invites stay in later specs.

## User stories
- As a tradesperson, I want to apply in one guided flow on my phone and come back to finish later.
- As a tradesperson, I want to see what Sortd is checking and what is still missing.
- As a vetting admin, I want one queue of submitted applications with every document and a checklist, so I can approve, ask for changes, or reject with a reason.
- As the founder, I want only checked pros to receive work, and sensitive documents seen only by people who vet.

## Acceptance criteria
**Application (pro)**
1. Given a signed-in user with the `pro` role and no submitted application, when they open `/pros/apply`, then a step-by-step form opens. It asks for:
   - business details (business name, trading as sole trader or company, optional VAT number);
   - trades and services (only active services);
   - service suburbs (active launch suburbs, grouped by region);
   - identity document;
   - proof of address;
   - registrations, required only for chosen services that need one (PIRB number for plumbing services that require it; electrical registered-person number for electrical services);
   - two references (name, mobile, relationship);
   - profile photo;
   - a short bio (max 500 characters);
   - consent to vetting checks.
   Each step saves a draft; they can leave and resume.
2. Given a document upload, then only JPEG, PNG, WebP, HEIC (images) and PDF up to 10 MB are accepted. Images are processed as in spec 012 (re-encoded, metadata stripped); PDFs are checked by content type and stored unchanged. Every file goes to the private `media` disk.
3. Given a pro chooses a service that requires a registration, then the registration step requires that registration's number and a document. Without it they can still submit, but that service is marked "needs registration" and is never eligible (spec 006 query).
4. Given all required steps are complete, when they tap "Submit for review", then the application status becomes `submitted`, the submission time is recorded, the form becomes read-only, and they see the status page. A double tap submits once.
5. Given `/pros/status`, then the pro sees their status (in progress, under review, changes requested, approved, rejected, suspended) and a checklist per item (received / verified / needs attention, with the admin's message where one applies). They never see internal notes.
6. Given status `changes_requested`, then only the flagged items become editable, and the pro can resubmit.

**Vetting (admin)**
7. Given an admin with the `admin_vetting` or `admin_super` role, then **Admin → Applications** lists applications by status (default: submitted, oldest first) with business name, trades, suburbs count and days waiting. Other admin roles cannot see the list or any document.
8. Given an application view, then the admin sees every field and can open each document through a five-minute signed link (images inline, PDFs downloaded). Each document can be marked verified, with an expiry date where relevant (registrations, ID not required), or flagged with a message to the pro. Each reference can be marked "contacted — positive / negative / no answer" with a private note.
9. When the admin approves, then:
   - the guards hold: ID and proof of address verified, both references marked positive, at least one service whose registration (if needed) is verified;
   - the pro becomes `approved` with `approved_at` and the approving admin recorded;
   - a "you're approved" message is sent through `MessagingChannel` after commit;
   - the pro starts counting for coverage for their verified services and suburbs.
10. When the admin requests changes or rejects, a reason is required and is shown to the pro; a message is sent after commit. A rejected applicant cannot reapply for the configured period (default 90 days).
11. Given an approved pro, an admin can suspend them (reason required) and later reinstate them; suspended pros are never eligible. Every status change writes a `pro_events` row (who, when, from/to, reason) and an activity-log entry.
12. Given a registration whose expiry date passes, then that registration stops counting (existing query), and the pro's status page shows it as expired. (Reminders are a later spec.)

**Privacy and retention**
13. ID numbers are **not** typed or stored; the ID document image is the evidence. Registration numbers are stored encrypted. Reference phone numbers are stored encrypted and normalised to E.164; the pro confirms that the referees agreed to be contacted.
14. Documents, references and their notes of an application that was rejected or abandoned (no activity for 90 days) are deleted after the retention period (open question 3), by a scheduled prune that is tested. Approved pros' documents are kept while they are active.
15. No document, reference phone, registration number or bio appears in logs, URLs, the activity log payload or customer-facing views.

## Screens / UX
- `/pros/welcome` (spec 011) gains "Start your application" or "Continue your application".
- `/pros/apply`: one step per screen at 360 px, progress bar, Back and Save & continue, upload with preview and remove, busy states, inline validation, a summary step with "Change" links, then "Submit for review".
- `/pros/status`: status badge, checklist, messages from vetting, "Fix these items" when changes are requested.
- Admin → Applications: table with status filter; application view with sections (business, services & suburbs, documents with viewer and verify/flag actions, references, bio and photo, timeline), and Approve / Request changes / Reject actions with confirmation.
- Empty states: "No applications waiting". Error states: upload failure keeps the step; expired signed link re-opens from the view.

## Rules and edge cases
- Pro statuses: `draft → submitted → (approved | changes_requested | rejected)`; `changes_requested → submitted`; `approved ↔ suspended`. Transitions go through one `ProStatusMachine` with guards, in a transaction with `lockForUpdate()`, each writing a `pro_events` row (same pattern as jobs).
- An admin cannot vet their own application (an admin who is also a pro).
- Changing services or suburbs after approval is out of scope (journey P4); admins can edit them in the application view, logged.
- Two admins acting at once: the second action fails safely ("This application changed, reload").
- The pro panel `/pro` stays closed in this spec (opens with invites, spec 009).

## Data changes
- `pros`: add business_type (sole_trader/company), vat_number (nullable), bio, submitted_at, decided_by, decision_reason, suspended_at, reapply_after; status values above (existing `applied` rows migrate to `draft`).
- `pro_documents`: add `number` (encrypted, nullable), `media` relation (one file per document), verified_by, flag_message, notes (private); types: `id_document`, `proof_of_address`, `pirb`, `electrical_registered_person`, `profile_photo`.
- New `pro_references`: pro_id, name, phone (encrypted), relationship, outcome (pending/positive/negative/no_answer), note (private), checked_by, checked_at.
- New `pro_events`: pro_id, from_status, to_status, actor_id, reason, created_at (append-only).
- Settings: `vetting.reapply_after_days` (90), `vetting.abandoned_after_days` (90), retention per open question 3.
- Update `docs/architecture/data-model.md`, decisions log.

## Security and privacy
- Policies: a pro sees and edits only their own application while `draft`/`changes_requested`; vetting and super admins see applications and documents; support and finance admins do not. Document links are signed, five minutes, and re-check the policy.
- Special personal information: no criminal-record check in this spec (open question 2). The vetting consent covers the checks listed (ID, address, registrations, references).
- Rate limits on uploads and submission; files validated by content, not name.
- Audit: every vetting action and status change in the activity log without document contents.

## Out of scope
- Bank details and payouts (Phase 4, open question 1), invites and the pro panel (009), quotes (010), pro profile editing/pause/weekly cap by the pro (P4), document expiry reminders, criminal-record checks, automated ID verification.

## Decisions (founder, 2026-10-04)
1. **Bank details:** collected in the payments phase, once the payment provider (Q4) is chosen; not in this application.
2. **Criminal-record checks:** none in v1. Vetting is ID, proof of address, registrations and two phoned references; revisit before launch with a provider and legal advice.
3. **Retention:** documents, references and vetting notes of rejected or abandoned applications are deleted 12 months after the decision or last activity; the account itself stays.
4. **Reapplying:** a 90-day wait after rejection, adjustable in settings.

## Progress
- 2026-10-04: Drafted after spec 007 merged in PR #27. Awaiting founder approval and the four decisions above.
- 2026-10-04: Founder approved the spec with all four recommended defaults.
- 2026-10-04: Founder approved the build plan.
- 2026-10-04: Built on `feat/008-pro-vetting`; tests in `tests/Feature/Pros/`. Image re-encoding was extracted from job photos into a shared helper (job photo tests unchanged and passing).
- 2026-10-04: Code, security and spec reviews. Fixed:
  - an admin who is also a pro could open their own application (and its private notes) in the vetting screens;
  - a reapplication carried over earlier verifications, and a registration number could change under a "Verified" badge;
  - a flagged registration could not be fixed from the form;
  - upload and submission rate limits were missing;
  - pro-side status changes were not in the activity log;
  - a replacement referee's agreement was not asked;
  - the prune left vetting reasons behind and paged by offset;
  - inactive services could be chosen by admins;
  - PDFs with scripts or embedded files are now refused;
  - "Apply again" is shown after the wait;
  - review-step "Change" links added;
  - a policy now covers references;
  - stale status messages are skipped.
  - Deviations: `paused` left out until journey P4; `vetting.abandoned_after_days` dropped (decision 3 decides); the bio label no longer says customers see it (AC15).
- 2026-10-04: Checks:
  - `composer check` passed (477 tests, 2,112 assertions; 0 Larastan errors; no Composer advisories);
  - `npm audit --audit-level=high` found 0 vulnerabilities;
  - assets built;
  - welcome page and application steps checked at 360 px with a local test pro.
- Awaiting PR review and founder merge approval.

