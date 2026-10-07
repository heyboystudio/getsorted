# User journeys

> Written for PRD v1. Since then booking is one Siya conversation (decisions 045, 051), sign-in is email or Google (039), and payment steps are Phase M: at launch customers pay pros directly (058). Where this file and `prd.md` (v2) disagree, `prd.md` wins.

Each journey is a list of screens/steps. Feature specs in `docs/specs/` break these into buildable pieces.

## Customer (`/app`, Livewire, mobile first)

### C1 · Sign up / log in
1. Enter mobile number (SA format, normalised to E.164).
2. Receive 6-digit code (WhatsApp; "send by SMS instead" after 30 s).
3. Enter code → new users add first name, surname, email (optional) and accept terms + privacy notice (POPIA consent recorded with version and timestamp).

### C2 · Book a pro (one Siya thread, specs 017 and 019)
Everything happens in one conversation at `/book`, Kandua-style (`docs/product/kandua-reference.md`). A progress bar shows **Describe → Where & when → Photos → Confirm**, and finished items collapse into green ✓ cards.
1. Start from: "Get help with a job" on the home page, "What's going on at home?" on the account home (text or quick chips), a trade page (trade pre-selected) or a service link (service pre-selected).
2. **Describe:** explain the problem naturally, or use optional trade/service shortcuts. Siya identifies the service in the background (spec 019 trial), uses details already supplied and asks only missing required questions. GetSorted questions and corrections work throughout the conversation. Emergency requests pause booking and show reviewed emergency guidance; only an explicit choice to discuss a later repair resumes it. When scoping is complete, proceed directly to sign-in or location without the Continue / Add more details gate.
3. **Sign in:** guests sign in or sign up here (spec 014, phone verified per spec 001) and come straight back to the thread with everything kept.
4. **Where:** pick a saved property or add one inside the thread (address search, spec 015). Coverage is checked at once: covered → "Good news…", not covered → Keep me updated (waitlist with the account's details) / Choose a different service / No thanks.
5. **When:** calendar (next 30 days), then Morning / Afternoon / Flexible; "Urgent — today" for emergency-capable services.
6. **Photos:** optional, up to 5, from the gallery or the camera, added as soon as they're chosen; or Skip for now.
7. **Confirm:** summary (service, answers, notes, address, when, photos, AI job description) with Change on each section. **Confirm booking** books the job. Siya never books without that tap.
8. "Your job is booked" in the thread, with a link to the job page, plus the WhatsApp "job posted" template.

### C3 · Compare and accept quotes
1. Job page shows quotes as they arrive (max 5): pro name, rating, reviews count, years on GetSorted, registrations, total, labour vs materials, deposit, earliest date, validity, notes.
2. Accept one → if deposit > 0, pay now via provider checkout; else booked.
3. Other quoting pros are told politely.

### C4 · During and after the job
1. Job page: stage tracker, date, pro contact (WhatsApp + call, after acceptance), documents (quote, invoices, receipts), timeline.
2. Pro marks complete → customer reviews work → pays final invoice (or raises an issue).
3. Auto-confirm after 72 h if no action and no issue.
4. Review prompt (1–5 stars + text, optional photos).

### C5 · Issues
1. "Something's wrong" on the job page → reason list + description + photos.
2. Payout frozen; admin contacts both sides; outcome shown on the job page.

### C6 · Account
Profile, saved properties (add/edit/delete), job history, notification preferences, download my data, delete my account (soft-delete + anonymise per retention policy).

## Pro (`/pros/...`, mobile web pages)

### P1 · Apply and get vetted
1. Public "Join as a pro" page → phone verification → application wizard: business details, trades & services, service suburbs, ID upload, registrations (PIRB number / electrical registration), proof of address, 2 references, bank details, profile photo, short bio.
2. Status page: "Under review" with checklist of what's verified.
3. Admin approves → WhatsApp + email "you're live".

### P2 · Receive and quote
1. Invite arrives (WhatsApp template with a link to the pro job page).
2. Invite page: job details (no street address), quotes received so far, time left.
3. Decline (reason) or build quote: line items (labour / materials / call-out), deposit %, earliest start date, validity, notes. Preview as customer sees it → Submit.
4. Edit/withdraw while job still open.

### P3 · Do the job and get paid
1. Accepted → full address and customer contact appear; add to calendar.
2. Start job → deposit released (if any).
3. Mark complete → final invoice (pre-filled from quote, adjustable with reason) → submit.
4. Payout status, statements, commission breakdown, downloadable documents.

### P4 · Profile and performance
Services, suburbs, availability/pause, weekly job cap, documents with expiry reminders, reviews (reply once), stats (response rate, win rate, rating).

## Admin (`/admin`, Filament panel)

- **Dashboard**: jobs by status, jobs without quotes > 12 h, disputes open, payouts due, failed webhooks.
- **Vetting queue**: applications with document viewer, checklist, approve/reject with reason, notes.
- **Pros**: profile, status (approve, suspend, pause), strikes, documents, service areas, payouts.
- **Customers**: profile, jobs, notes; no edit of verified phone without re-verification.
- **Jobs**: full timeline, manual invite, force-close, cancel with reason, impersonate-view (read only).
- **Disputes**: evidence from both sides, resolution (refund full/partial, rework, release), notes.
- **Payments & payouts**: provider references, retry failed payouts, refunds (two-person approval above a configurable amount), reconciliation report.
- **Catalogue**: trades, services, scoping questions (edit the YAML-seeded data), launch suburbs.
- **Settings**: timers, commission rate, deposit cap, feature flags.
- **Audit log**: every admin action, filterable.
- **Roles**: super admin, support, vetting, finance (permissions per resource).
