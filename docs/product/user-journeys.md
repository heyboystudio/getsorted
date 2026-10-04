# User journeys

Each journey is a list of screens/steps. Feature specs in `docs/specs/` break these into buildable pieces.

## Customer (`/app`, Livewire, mobile first)

### C1 · Sign up / log in
1. Enter mobile number (SA format, normalised to E.164).
2. Receive 6-digit code (WhatsApp; "send by SMS instead" after 30 s).
3. Enter code → new users add first name, surname, email (optional) and accept terms + privacy notice (POPIA consent recorded with version and timestamp).

### C2 · Book a pro
1. Start from: home search box ("describe your problem"), a trade tile, or a service link on a trade/SEO page.
2. If free text: AI suggests trade + service; customer confirms or picks.
3. **Coverage check** (needs a suburb): if no eligible pros → waitlist form; stop.
4. Scoping questions (one per screen, tap answers, back button works).
5. Optional free text and photos (≤ 5, ≤ 10 MB each, images only).
6. Property: pick saved or add new (suburb autocomplete → map pin → street address, "we only share this with the pro you choose").
7. When: date picker (next 30 days) + window (morning / afternoon / flexible); or "urgent — today" if service is emergency-capable.
8. Summary with AI-written description → edit → **Post job**. Must be logged in with verified phone by this point (login can happen inline).
9. Confirmation page + WhatsApp "job posted" template.

### C3 · Compare and accept quotes
1. Job page shows quotes as they arrive (max 3): pro name, rating, reviews count, years on Sortd, registrations, total, labour vs materials, deposit, earliest date, validity, notes.
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
