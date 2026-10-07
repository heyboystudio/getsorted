# Spec 022 · Real notifications: browser and phone pop-ups, and a live inbox

Status: Draft — founder gave direction 2026-10-07, awaiting approval of this spec · Phase: 3 · Owner: founder

## Goal
Clients and pros today get notices only inside the app: the Notifications page fills in on refresh, and nothing pops up on a phone or desktop or asks the browser for permission. Make every existing notice also arrive as a real system notification (web push) on phones and desktops, and make the in-app inbox update without a refresh.

## Founder decisions (2026-10-07)
1. Use the `laravel-notification-channels/webpush` package (13.x, supports Laravel 13; brings `minishlink/web-push`). Dependency logged in `docs/architecture/decisions.md`.
2. **Everything that already notifies** also pushes: new quote, chat message, new job near a pro, quote accepted or not chosen, job posted or expired, and so on. Customers can switch groups off (quotes, job updates, messages) in Account → Notifications; those switches apply to push as well as texts.

## User stories
- As a pro, I want a pop-up on my phone the moment a job near me arrives so that I can quote before other pros.
- As a customer, I want a pop-up when a quote or a message arrives so that I do not have to keep checking the site.
- As anyone, I want to choose to turn this on, on this device, and turn it off again.
- As anyone with the site open, I want my notifications to update by themselves.

## Acceptance criteria
1. Given a signed-in client or pro on a browser that supports push, when they have not decided yet, then a "Turn on notifications" card shows on their home screen (Home / Today). The browser's permission dialog opens only after they tap it, never on page load.
2. Given they allow it, then this device is subscribed and the card is replaced by a confirmation; given they block it, then the card explains how to unblock it in the browser settings and does not ask again; given they dismiss the card, then it stays away for 14 days.
3. Given a subscribed device, when any notice is created through `Notify::user`, then a system notification is sent to each of that person's subscribed devices with the notice's title, body and link. Tapping it opens the link (or focuses the open tab and navigates it).
4. Given the notice is for a customer in a group they switched off, then no push is sent; the in-app notice and any email follow today's rules.
5. Given the push service reports a subscription is gone (404 or 410), then that subscription is deleted and nothing else is affected. A failing push never blocks or fails the in-app notice or the email.
6. The push text carries the same safe text as the in-app notice: trade, area, quote or message wording and a link. Never phone numbers, email addresses, street addresses or message text (security baseline §3).
7. Given a signed-out device, then no push arrives for the previous account: signing out removes this device's subscription for that account.
8. Given the same person has several devices, then each gets the push; one device being switched off does not affect the others.
9. Given several notices of the same kind for the same job arrive close together, then they replace one another on the device (a tag), so the lock screen is not flooded.
10. Account → Notifications has a per-device switch "Pop-up notifications on this device" showing the real state: on, off, blocked by the browser, not supported, or needs the app installed (iPhone).
11. Given an iPhone or iPad in Safari that has not been added to the Home Screen, then the card explains how to add the site to the Home Screen first (Apple only allows web push for installed sites, iOS 16.4 or later). The site gets a web app manifest and icons so installing works and opens full screen.
12. A header bell on every signed-in screen shows the unread count and links to Notifications. The count and the Notifications page update on their own (polling about every 15 seconds while the tab is visible) without a refresh.
13. Given a push arrives while the site is open and visible in a tab, then the in-app bell updates immediately and no duplicate system pop-up is shown for that tab.
14. Without VAPID keys configured (local development, tests), push sending is skipped quietly and everything else works.

## Screens / UX
- **Card** (home screens): "Get a pop-up when a quote or message arrives" with a Turn on button and a small "Not now". States: ask, install-first (iPhone), blocked (how to unblock), enabled (brief "Notifications are on", then hidden).
- **Account → Notifications** (customers) and a small "Notifications" link in the pro Profile: the per-device switch with the same states.
- **Bell**: in the shell header next to Log out, with an unread badge; accessible name includes the count.
- **System notification**: title, body, site icon, tag; click opens the link.
- Empty, error and unsupported states are written out, never silent.

## Rules and edge cases
- Subscriptions belong to a user and a device (endpoint); re-subscribing the same device updates, never duplicates.
- Push is sent by the existing queue (`notifications`), after commit, like the email.
- The service worker is served from the site root (`/sw.js`) so it can control the whole site; it has no offline caching in this spec.
- Payload is encrypted by the library; links must stay on this site (same check as the inbox).
- Quiet failure: log only counts and status codes, never endpoints or payloads.

## Data changes
- New table `push_subscriptions` from the package (user, endpoint, public key, auth token, content encoding), with its model attached to `User` via the package trait. Update `docs/architecture/data-model.md`.
- Config: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` in each environment's `.env` (never committed). A command prints a fresh pair for the operator to store.

## Security and privacy
- Endpoints and keys are credentials: hidden from serialisation, never logged, deleted on sign-out, on account deletion (cascade) and when the push service says they are gone.
- Only the signed-in user can register or remove their own devices (policy-checked, CSRF protected, rate limited).
- Push text follows the same privacy rules as email and inbox text.
- Activity log entries for turning push on or off.

## Out of scope
Native mobile apps; offline support or caching; scheduled reminders; marketing messages; notification sounds and rich images; choosing different channels per kind beyond today's three groups; admin push.

## Open questions
- Which Home Screen icon and name to use for the installed site (default: the Get Sorted mark and name).
- Preview server needs VAPID keys added to `deploy/preview/.env` before push works there; the founder or I set them (a secret, so the founder decides who).

## Build order (one PR each)
1. **Server side:** package, migration, VAPID config and key command, `webpush` channel on `UserNotice` with the preference rule, subscription endpoints, cleanup on sign-out, decision log.
2. **Browser side:** `/sw.js`, manifest and icons, the card, the per-device switch and all states, the bell with polling inbox.
3. **Verification on a real phone and desktop** against the preview, including iPhone Home Screen install.

## Progress
- 2026-10-07: Drafted after the founder reported that clients and pros get in-app notices but no browser or system notifications. Findings: all notices go through `Notify::user` → `UserNotice` (database and optional mail only); there is no service worker, manifest, push library or permission prompt. Library check: `laravel-notification-channels/webpush` 13.0.1 installs cleanly on Laravel 13 (dry run). Local PHP has `openssl` and `bcmath`.
- Next: founder approves this spec; then part 1.
