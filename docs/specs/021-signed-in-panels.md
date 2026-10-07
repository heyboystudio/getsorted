# Spec 021 · Customer and pro signed-in panels

Status: Built, awaiting founder review (all 5 parts); a few items wait on other specs, see Progress — approved by the founder 2026-10-07 · Phase: 2–3 · Owner: founder

## Goal
Replace the bare signed-in screens with two real panels. Customers get a home that says what needs them, a jobs area, a messages inbox and an account area with a profile. Pros get a "Today" home, a job pipeline and a profile that works as their shop front. Customers can see a pro's profile when comparing quotes. Both roles get persistent navigation that works one-handed at 360 px.

This builds on specs 008–010, 012 and 018. It changes presentation, navigation and a few small settings; it does not change the job state machine, matching, quoting or privacy rules.

## Decisions (founder, 2026-10-07: "go with your recommendations")
1. First round covers the shell, the customer panel, and the pro Today / Jobs / Profile screens. Earnings, performance stats and reviews are later specs.
2. The panels use the **Get Sorted** brand (per `PRODUCT.md`). Header wordmark and page titles change; legal pages and emails are out of scope.
3. Customers have **initials only**, no photo upload.
4. A vetted pro's edits to **services and registrations** go back to admin review. Bio, weekly cap, paused state and service suburbs inside the launch region apply immediately.
5. Pro availability is a simple **Available / Paused** toggle. Weekly working hours come later.
6. No Earnings tab in this spec. It arrives with payments (Phase 4).

## User stories
- As a customer, I want to see at a glance what needs my attention so that I never miss a quote or a payment step.
- As a customer, I want to see who a pro is before I accept their quote so that I can trust the choice.
- As a customer, I want to manage my profile, properties and notifications in one place.
- As a pro, I want a home screen that shows what to answer first so that I win jobs by replying in time.
- As a pro, I want to control my profile and pause new invites so that I can manage my workload.
- As a pro, I want to see exactly what customers see on my profile.

## Acceptance criteria

### Shell
1. Given a signed-in customer on any `/app` page, then a bottom tab bar shows Home, Jobs, Messages and Account on mobile and becomes a side rail from the `lg` breakpoint; the current tab is marked and keyboard and screen-reader accessible.
2. Given an approved pro on any `/pros` panel page, then the tab bar shows Today, Jobs and Profile with the same behaviour. Pros who are not yet approved keep the existing welcome / apply / status flow with no tab bar.
3. Given a user who is both customer and pro, then the header offers a switch between the two panels, replacing today's "Go to my customer account" link.
4. Existing routes keep working: `account.home`, `jobs.show`, `properties.*`, `pros.welcome`, `pros.jobs`, `pros.jobs.show`, `pros.status`. Old links from WhatsApp templates and emails still resolve.

### Customer Home
5. Given quotes waiting on a job, a pending final-amount increase, work awaiting the customer's check or payment, or an unfinished draft, then a "Needs you" list at the top names each item with one tap to resolve it; with nothing pending the list is absent, not empty.
6. Given an active job (open, awaiting deposit, scheduled, in progress, awaiting final payment), then a card shows the service, status label, suburb, and the pro's name and photo once a quote is accepted.
7. The Siya prompt box and quick chips stay on Home, below "Needs you".
8. Recent activity shows the last 5 customer-visible `service_job_events` across the customer's jobs, in plain language, newest first. Internal admin events never appear.

### Customer Jobs
9. Given the Jobs tab, then jobs are grouped Active / Completed / Cancelled and expired, each row showing service, suburb, status chip and the relevant date; drafts continue into the booking thread as today.
10. Given a job page, then it shows a stage tracker and a timeline built from `service_job_events` (customer-visible events only). Existing quotes, chat and final-amount sections are unchanged.
11. Given a completed job, then "Book this pro again" starts a booking with the same service pre-selected and no pro pre-selected (matching is unchanged). The customer picks the property at the Where step; pre-filling it needs a change inside the booking thread, which spec 019 is rewriting, so it waits for that.

### Customer Messages
12. Given the Messages tab, then one inbox lists conversations across all of the customer's jobs, most recent first, with unread counts and a link into the job's chat. A customer sees only their own conversations.

### Customer Account
13. The Account tab has sections Profile, Properties, Notifications, Privacy and data, and Help. Properties reuses the existing screens inside the shell.
14. Profile shows initials, name, email and verified mobile. Name is editable. Changing email or mobile requires re-verification through the existing flows and is never applied before verification.
15. Notifications let the customer choose WhatsApp, SMS and email per event group (quotes, job updates, messages). Safety-critical and legal messages cannot be switched off. Preferences are read by the existing send jobs before sending.
16. Privacy and data hold the waitlist-request removal now on Home, a request to download my data, and delete my account. Download and delete create a request that admin fulfils; they do not run silently (POPIA retention, see `docs/security/popia.md`).

### Pro profile and customer view
17. Given a customer viewing a quote, then tapping the pro opens a read-only profile: photo, business name, bio, trades and services, service suburbs, verified registrations with valid or expired state, and "on Get Sorted since". It never shows contact details, ID documents, address proof, VAT number or bank details.
18. Given no real reviews or completed jobs exist, then the profile shows no rating, no counts and no review block. No number is shown that is not backed by data.
19. A pro's profile is visible to a customer only while the pro has a quote on one of that customer's jobs (policy-enforced and tested).

### Pro Today
20. Given an approved pro, then Today shows, in this order: open invites by least time left (service, suburb, urgency, countdown); quotes awaiting the customer; booked jobs in the next 7 days with full address and customer contact (only for accepted quotes); final-amount responses waiting on the pro. Sections with nothing in them are hidden.
21. Today shows an Available / Paused toggle. While Paused the pro receives no new invites (the matching query excludes them), existing invites and booked jobs continue, and Today shows a clear "You're paused" banner.
22. Today shows a profile-completeness prompt only when bio or any required profile item is missing.

### Pro Jobs
23. Jobs is a pipeline with tabs Invites, Quoted, Booked and Done. Replace the New/Past split, and every row in every tab opens its job. Past invites show a reason (quote not chosen, expired, declined, withdrawn).
24. The existing invite page, quote builder and final-amount flow keep working unchanged.

### Pro Profile
25. Profile shows photo, business name, bio, trades and services, service suburbs, weekly job cap and the document checklist with expiry state, in editable sections.
26. Editing bio, weekly cap or suburbs within the launch region saves immediately and is written to `pro_events`.
27. Adding a service, adding a registration or replacing a registration document sets that item to "pending review", keeps the existing approved state live until admin decides, and appears in the vetting queue. A rejected change leaves the previous approved state in force.
28. "Preview as customers see it" renders the exact view from criterion 17 for the signed-in pro.
29. A document nearing expiry (30 days) shows a warning on Profile and on Today. Expired registrations stop invites for that service, as vetting rules already require.

### Cross-cutting
30. All new screens work at 360 px, meet WCAG AA contrast, have visible focus states, and provide loading, empty and error states.
31. Every Livewire action authorises through a Policy. A customer cannot reach another customer's jobs, conversations or properties; a pro cannot reach another pro's invites, profile edits or documents. Each rule has a test.
32. `composer check` passes, including arch tests and Larastan.

## Screens / UX
- **Customer shell:** top bar with Get Sorted wordmark and a quiet panel switch if the user is also a pro. Bottom tabs: Home, Jobs, Messages (unread dot), Account.
- **Customer Home:** greeting; "Needs you" cards; Siya prompt; active-job cards; recent activity; saved-properties shortcut moves into Account.
- **Jobs / job page:** grouped list; job page with stage tracker, timeline, quotes, chat, final amount, documents, "Book this pro again".
- **Messages:** conversation list with job name, pro name, last message and unread count; empty state "Chats start once a pro quotes".
- **Account:** list of sections; each section a focused page; destructive actions behind confirmation.
- **Pro Today:** countdown cards; pause toggle; completeness prompt; empty state "No new jobs right now. We'll WhatsApp you when one fits."
- **Pro Jobs:** four-tab pipeline; each row tappable.
- **Pro Profile:** sections with inline edit; "Preview as customers see it"; clear "pending review" badges.
- **Public pro profile (customer view):** photo, name, bio, services, suburbs, registrations, member-since, "No reviews yet" only if that line is shown at all.
- Empty, loading and error states for every list; no spinner without a timeout message.

## Rules and edge cases
- Job statuses and transitions are untouched (`docs/product/job-lifecycle.md`). The panels read status and events; they never write them.
- Customer-visible timeline events are an explicit allow-list so a new internal event type is hidden by default.
- A pro who is suspended or loses approval loses the panel tab bar and sees the status page, as today. Paused is not suspended.
- Pausing never cancels or hides jobs already invited or booked.
- A pending profile change that admin rejects reverts to the last approved value and tells the pro why, using the existing vetting message channel.
- Notification preferences default to current behaviour so nobody loses messages on release.
- Duplicate submit and double-tap on toggles and saves are idempotent.

## Data changes
Update `docs/architecture/data-model.md` in the same PR. To be confirmed at build time against the current schema:
- `users`: notification preference column (JSON) or a small `notification_preferences` table.
- `pros`: `paused_at` (nullable timestamp). `pro_events` gains event types for pause, resume and profile edits.
- `pro_services` and registration changes: a pending-review marker (pending state plus the previously approved value) so approved state stays live while a change is reviewed.
- `data_requests`: customer download and deletion requests with status and handled-by.
- No change to `service_jobs`, `quotes` or invite tables.

## Security and privacy
- Policies: a customer-visible `ProProfilePolicy::view` (quote on that customer's job); extend `ProPolicy` for self-edit; keep `ServiceJobPolicy` and `ServiceJobInvitePolicy` as the gate for jobs and invites.
- The pro profile view model is an allow-list DTO. Contact details, documents, VAT and bank details are never loaded into it.
- Customer contact details and street address reach a pro only after acceptance, as today; Today shows them only for accepted jobs.
- Notification preferences cannot disable safety, security or legal messages.
- Data download and deletion are audited and admin-fulfilled; no automatic export of personal data.
- Rate limits on profile saves, email change and data requests. Activity log entries for profile edits, pause/resume, preference changes and data requests.

## Out of scope
Earnings, payouts and statements (Phase 4); reviews, ratings, performance stats and pro replies (Phase 5); weekly working hours; favourite pros; referral codes; customer photo upload; multi-person pro businesses; pro-to-customer messaging outside a job; native app; changing the Siya booking thread; the public home page; admin panel changes beyond the pending-review queue entries.

## Open questions
- Data download format and turnaround for customers (default: admin-fulfilled within 30 days, per POPIA).
- Exact allow-list of customer-visible timeline events (default: posted, quote received, quote accepted, scheduled, started, final amount changes, completed, cancelled).
- Whether "Book this pro again" should later pre-invite that pro; today it only pre-fills the job (matching is spec 009 / 020).

## Build order (each part is a separate PR)
1. **Shell:** layouts, tab bars, panel switch, Get Sorted header, route wiring. No new data.
2. **Customer Home and Jobs:** Needs you, active cards, activity, grouped Jobs, job-page timeline and tracker, book again.
3. **Customer Messages and Account:** inbox, profile, notification preferences, privacy and data (with migrations).
4. **Pro Today and Jobs:** Today, pause toggle (with matching change), pipeline.
5. **Pro Profile and public profile:** editing, pending-review flow, preview, customer view of a pro.

## Progress
- 2026-10-07: Spec drafted from a read of the current screens and the docs. Founder decisions 1–6 recorded above; spec **approved** the same day.
- 2026-10-07: **Part 1 (shell) built** on `feat/021-panels-shell` (worktree `sortd-panels`, branched from `main`; the original `sortd` checkout keeps its uncommitted spec 018 part 2 work untouched).
  - `App\Support\PanelNavigation` decides tabs, who sees them and the panel switch. `components/layouts/panel.blade.php` is the shell (bottom tab bar on phones, side rail from `lg`, Get Sorted header). Pages choose it with `#[Layout('components.layouts.panel', ['panel' => ...])]`.
  - Customer pages (Home, job page, properties) and approved-pro pages (Welcome, Jobs, job/quote page, Status) use it. The quote page hides the phone tab bar (`focused`) because it has its own fixed bottom bar; the desktop rail stays.
  - Not-yet-approved pros keep the existing welcome / apply / status flow with no tab bar. The application wizard, Become a pro, the booking thread and sign-in pages are unchanged.
  - **Transitional tabs:** only tabs that already have a real page are shown. Customer: Home, Properties. Pro: Today (the welcome page), Jobs, Application. They become Home / Jobs / Messages / Account and Today / Jobs / Profile as parts 2–5 land (AC1 and AC2 are fully met only then).
  - Tests: `tests/Feature/Panels/PanelShellTest.php` (AC1–AC4).
- Dependency to watch: the "final-amount response waiting" items (AC5, AC20) need spec 018 part 2, which is still uncommitted in the original checkout. Build those items after it merges.
- 2026-10-07: **Part 2 (customer Home and Jobs) built** (AC5–AC11).
  - Home: "Needs you" strip (pay, deposit, quotes to compare, unread chat, unfinished request; most urgent first, hidden when empty), Siya box, active-job cards (pro name once a quote is accepted), recent activity (5 allow-listed events). Saved properties and waitlist removal stay on Home until part 3 moves them into Account.
  - Jobs tab at `/app/jobs` (`jobs.index`): Active / Done / Cancelled, removed drafts left out. Job page: five-step progress tracker, timeline, "Book again" on finished jobs.
  - Customer-visible events are an allow-list in `JobTimeline` (`job_posted`, `quote_accepted`, `job_expired`, and the spec 018 final-amount events); anything else is hidden by default. `draft_cancelled` is deliberately not shown.
  - "Quote received" is not in the timeline because no event is recorded when a quote arrives; the quotes section of the job page shows them.
  - The "final amount waiting" Needs-you item is not built yet (needs spec 018 part 2).
  - Tests: `tests/Feature/Panels/CustomerPanelTest.php`.
- 2026-10-07: **Part 3 (customer Messages and Account) built** (AC12–AC16). Customer tabs are now Home, Jobs, Messages, Account.
  - Messages (`/app/messages`): one inbox across jobs with unread counts and an unread badge on the tab. It shows who and when, never message text, so masked contact details cannot appear in a preview.
  - Account (`/app/account`) lists Profile, Properties, Notifications, Privacy and data, and Help. Properties reuses the existing screens.
  - Profile: name edit; a new email waits in `users.pending_email` and only replaces the old one when its emailed signed link is opened (rate limited); mobile change reuses the existing code flow, which already keeps the old number until the new one is verified.
  - Notifications: choose which text messages to get (quotes, job updates, chat) and WhatsApp or SMS. Defaults are all on. The customer-bound send jobs (`SendQuoteMessage`, `SendJobPostedMessage`, `SendJobExpiredMessage`, `SendChatNotification`) read the choices; messages to pros and login codes are never affected.
  - Privacy and data: "ask for a copy" and "ask us to delete my account" create one open `data_requests` row each (idempotent, rate limited, logged). Nothing is exported or deleted automatically. Support and super admins see the queue in the admin panel under Customers → Data requests and mark requests done.
  - Migration `2026_10_07_090000`; `docs/architecture/data-model.md` updated.
  - **Deviations from the spec text:** (a) Notifications offers WhatsApp or SMS only, not email, because the app sends no customer emails other than verification; email can join when there are emails to choose. (b) The waitlist-removal block also stays on Home (an existing test relies on it) as well as appearing on the privacy page, and the Saved properties shortcut stays on Home; both can be dropped from Home later.
  - Tests: `tests/Feature/Panels/CustomerAccountTest.php`.
- 2026-10-07: **Part 4 (pro Today and Jobs) built** (AC20–AC21, AC23–AC24).
  - Today (the `pros.welcome` page for an approved pro; other statuses keep the application welcome): Available / Paused toggle with a clear banner; "Answer these first" (open invites, least time left first, urgent flagged); "Coming up" (won jobs in the next 7 days, with the customer's name, phone and street address, only through the existing `viewContact` rule); "Waiting for the customer" (sent quotes). Sections with nothing in them are hidden.
  - Jobs is a pipeline: Invites · Quoted · Booked · Done, all rows open their job (past ones to the existing "no longer available" explanation) and past jobs show a reason (Expired, You declined, You withdrew your quote, Quote not chosen, Cancelled, Closed, Completed). The old `new` and `past` tab names still work. `ProPipeline` sorts every invite once so Today and Jobs agree. The Jobs tab shows a badge of open invites.
  - Pause: `pros.paused_at` (migration `2026_10_07_100000`), `SetProAvailability` (approved pro, own account only, idempotent, logged as `pro paused` / `pro resumed`), and `EligibleProsQuery` skips paused pros, so waves and manual invites exclude them. Existing invites, quotes, booked jobs and approval are untouched.
  - **Deviations:** pause and resume are written to the activity log, not `pro_events` (that table only records status changes). The profile-completeness prompt (AC22) and the "final amount waiting" section move to part 5 and spec 018 part 2.
  - Tests: `tests/Feature/Panels/ProPanelTest.php`.
- **Environment finding:** this Mac's Postgres session timezone is Africa/Johannesburg while the app writes UTC, so stored times shift by 2 hours. This is the cause of the 9 test failures that already exist on `main` (and why panel tests that use times use margins over 2 hours). Fix is a founder decision: set the pgsql connection timezone to UTC in `config/database.php`, which is safe on a fresh test database but would shift already-stored times on any database that has data written under another setting.
- 2026-10-07: **Part 5 (pro Profile, review flow and the customer's view of a pro) built** (AC17–AC19, AC22, AC25–AC29). Pro tabs are now Today, Jobs, Profile.
  - **Customer's view of a pro** (`/app/quotes/{quote}/pro`, linked from "View profile" on each quote): photo or initial, business name, bio, registrations (valid or expired), services by trade, suburbs, "on Get Sorted since". It is built from the `ProPublicProfile` allow-list (seven fields, a test pins them), so contact details, ID, proof of address, VAT and bank details can never reach it. No rating, review or count is shown. It exists only while that pro has a live (sent or accepted) quote on the customer's job and the pro is approved; otherwise 404.
  - **Pro Profile** (`/pros/profile`): bio, weekly job cap (1–50 or none) and service suburbs save at once and are logged without storing the text (`UpdateProProfile`); services and documents are listed with expiry state; a warning shows on Profile and Today for a registration that expires within 30 days or has expired; "Finish your profile" shows on Today only when the bio is missing; "Preview as customers see it" (`/pros/profile/preview`) renders the exact customer view.
  - **Review flow** (`pro_change_requests`, migration `2026_10_07_110000`): an approved pro can ask to add a service, or add or renew a registration (number plus a photo or PDF, run through the same file checks as the application). The request waits; what the pro has now stays live. A service that needs a registration the pro does not hold must come with it. Vetting and super admins decide in **Admin → Pros → Profile changes**: approving adds the service and/or replaces the registration document (verified, with the expiry the admin enters), all or nothing; rejecting needs a reason the pro sees on their Profile. Each request is decided once, never for a suspended pro, never by the pro's own account, and files open only through short-lived signed links.
  - **Deviations / not built:** (a) the pro is not messaged when a request is decided; the answer shows on their Profile. (b) Changing the business name, ID or proof of address is not offered here (still handled by support). (c) The "final amount waiting" items on both homes wait for spec 018 part 2.
  - Tests: `tests/Feature/Panels/ProProfileTest.php`, `ProChangeTest.php`.
- **Not verified by a person yet:** I looked at the mobile (390 px) and desktop screens in a headless browser; AC30's contrast and focus checks have not had a screen-reader or keyboard pass.
