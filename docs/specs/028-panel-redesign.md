# Spec 028 · Client and pro workspaces

Status: Approved (founder: "use Flux, stay on the free plan", 2026-10-08) · Phase: L · Owner: founder

## Goal
The signed-in client and pro screens are mobile pages stretched onto desktop: one narrow column, every section stacked, new content out of sight after an action, and the marketing site's styling. Replace them with a neutral, desktop-first workspace that still works well on a phone, built on **Flux UI's free components** (no paid licence).

## Principles
1. **Dark, calm, one accent.** (Founder, 2026-10-08, after seeing a cPanel-style dark dashboard as reference.) Charcoal panels as on the home page, soft borders, the logo's lime (#C6FD50) as the one accent. Dark by default with a light switch in the top bar, remembered per device. A single stylesheet (`resources/css/workspace.css`) flips the colour scales under `.ws-dark`, so views are written once and Flux stays in its light mode. The legal pages and the marketing home keep their own styling. Admin stays on Filament.
2. **A job is a workspace.** On desktop: a main column (status, estimates, details) and a side column (next step, chat, timeline). On a phone it is one column with the next step first.
3. **Show what changed.** After an action: a short toast, and the section that changed scrolls into view and is highlighted (`ShowsFeedback`, `focus-section`).
4. **Logic stays.** Actions, policies, notices and tests are untouched; only the presentation layer changes.
5. **Build once, share.** Pieces that appear in both panels are shared partials or components (first one: `livewire/shared/booked-actions`).

## Free Flux components in use
Sidebar, header, main, breadcrumbs, card, callout, badge, button, field, label, input, error, heading, icon, toast. Not used (paid): tabs, date picker, calendar, autocomplete, file upload, charts. Where a paid component would help (tabs on a phone) we build the small piece ourselves.

## Acceptance criteria
1. A new layout `components.layouts.workspace` (Flux sidebar from `lg`, top bar and bottom tabs on phones, notification bell, switch between client and pro areas, log out).
2. The client's job page is a two-column workspace on desktop and a single column on phones, with the next step first on phones.
3. After accepting an estimate, cancelling, marking done, cancelling a booking or reviewing, a toast appears and the changed section is scrolled into view and highlighted.
4. "Mark as done" and "Cancel this booking" are one shared partial used by both panels.
5. Every existing behaviour and test of the client job page still works (text, buttons, errors).
6. Roll-out order: client job page (done), client home and jobs list, pro job page, pro Today and jobs list, profile and credit pages, account settings, then retire the old shell (`layouts/panel.blade.php`, `home/panel.css`).

## Out of scope
Dark mode, a new colour brand, replacing the Siya booking thread's look (it keeps its conversational design), the admin panel.

## Siya first (founder, 2026-10-08)
On the client home the Siya box is the dominant element, at the top: a large dark card, a large text box, one "Ask Siya" button. The quick-pick chips (Blocked drain, No power and so on) and the separate "Book a pro" button on the home page are gone: describing the problem to Siya is the way to book. The only other way into booking is the trade tiles that appear inside Siya's thread when Siya is unavailable; they remain so a Gemini outage cannot stop all booking.

## Progress
- 2026-10-08: dark theme, account menu, collapsible sidebar and stat cards built; client home rebuilt in that style.
- 2026-10-08: layout and client job page built and merged. Client home (Siya first) and jobs list rebuilt. Next: pro job page, pro Today and jobs list, profile and credit pages, account settings, then retire the old shell.
