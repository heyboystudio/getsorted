# Spec 022 · Real notifications: browser and phone pop-ups, and a live inbox

Status: Approved by the founder 2026-10-07; in progress · Phase: 3 · Owner: founder

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
13. Given a push arrives while the site is open and visible, then the bell and inbox update immediately **and** the system pop-up still shows (changed 2026-10-07 after the founder tested with the site open and saw no pop-up). A newer notice with the same tag replaces the older and alerts again.
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
- Home Screen icon and name: the Get Sorted mark and name (founder, 2026-10-07).
- Preview server needs VAPID keys added to `deploy/preview/.env` before push works there; the founder or I set them (a secret, so the founder decides who).

## Build order (one PR each)
1. **Server side:** package, migration, VAPID config and key command, `webpush` channel on `UserNotice` with the preference rule, subscription endpoints, cleanup on sign-out, decision log.
2. **Browser side:** `/sw.js`, manifest and icons, the card, the per-device switch and all states, the bell with polling inbox.
3. **Verification on a real phone and desktop** against the preview, including iPhone Home Screen install.

## Progress
- 2026-10-07: Drafted after the founder reported that clients and pros get in-app notices but no browser or system notifications. Findings: all notices go through `Notify::user` → `UserNotice` (database and optional mail only); there is no service worker, manifest, push library or permission prompt. Library check: `laravel-notification-channels/webpush` 13.0.1 installs cleanly on Laravel 13 (dry run). Local PHP has `openssl` and `bcmath`.
- 2026-10-07: Founder **approved** the spec, the icon, and asked me to add the keys to the preview server myself.
- 2026-10-07: **Part 1 (server side) built** on `feat/022-web-push`: package installed (decision 054), `push_subscriptions` migrations, `HasPushSubscriptions` on `User`, a `webpush` channel on `UserNotice` that is skipped without VAPID keys, without a subscribed device, or when a customer switched that group off (call sites pass the group: quotes, messages, job updates; pro notices have none), a safe wrapper so a push problem never stops the in-app notice or email, subscribe and unsubscribe endpoints (`POST/DELETE /push/subscriptions`, auth, throttled, https only), cleanup on account deletion. Tests: `tests/Feature/Notifications/WebPushTest.php`.
- 2026-10-07: **Part 2 (browser side) built.** `public/sw.js` (shows the pop-up, opens only pages on this site, refreshes open tabs, skips the duplicate pop-up only outside Safari), `manifest.webmanifest` and Get Sorted icons on a light background, `resources/js/push.js` (one Alpine component used as the card, the per-device switch and a silent copy in every signed-in page; permission is asked only from the button; this device is removed before signing out; concurrent registrations share one request), the card on customer Home and pro Today, the switch in Account → Notifications and on the pro Profile, a live bell (`NotificationBell`, polls every 15 seconds while visible and refreshes on a push) in the shell header, and the inbox moved into the shell and made live.
  - **Fixed along the way:** spec 020's layout floated its own Messages and bell cluster in the top-right corner, which now overlapped the panel header; the shell hides it and uses the live bell, and pages outside the shell keep the cluster with the live bell.
  - **Verified in a real browser engine (headless Chrome, push APIs stubbed):** tapping the button asks for permission, registers `/sw.js`, subscribes with a valid 65-byte key and tells the server; a blocked browser and an iPhone outside the Home Screen show the right explanation and make no server calls; an already-allowed browser registers quietly once. Tests: `tests/Feature/Notifications/PushBrowserTest.php`.
  - **Not verified yet (needs a real device and the keys on the preview server):** an actual push arriving on a phone and a desktop, tapping it, and the iPhone Home Screen install.
- 2026-10-07: **VAPID keys added to the preview server and the branch deployed** (keys generated locally and appended over SSH, never printed). The founder tried it on a phone and a desktop: both registered (two `push_subscriptions` rows), the bell updated almost at once, but no system pop-up appeared, and the desktop showed "Reconnect notifications ... That did not work".
  - **Cause of the missing pop-up:** my service worker skipped the pop-up while the site was open and visible (the bell already updates), so a test with the site open showed nothing. Fixed: the worker now always shows the pop-up (AC13 changed) and re-alerts for a repeated tag.
  - **Desktop error:** the page could not (re)connect this device and showed only a generic message. Fixed: the real reason is shown (push service unreachable, with the Brave setting to switch on; permission refused; server refused with its status), a leftover subscription made with other keys is replaced automatically, and the server now logs each push result (accepted, status, device gone), never endpoints or text. Verified in headless Chrome with simulated failures.
- Next: redeploy, then the founder re-tests with the site closed or in another tab; I send a test with `sortd:send-test-notification` and read the logged result.
