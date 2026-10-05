# Spec 017 · One-thread booking with Siya (Kandua-style)

Status: Done (merged in PR #48, founder approved 2026-10-05) · Phase: 2 · Owner: founder · Replaces the booking wizard (005) and the Siya hand-off (016)

## Goal
Booking a pro happens in **one Siya conversation on one screen**, the way Kandua's Jess works (`docs/product/kandua-reference.md`). The customer picks a trade or describes the problem, answers 2–4 tap questions, picks where and when, adds photos, checks a summary and posts. Nothing is asked twice, there's no hand-off to a separate wizard, and a fixed progress bar always matches what's on screen. Matching and up to 3 quotes (009, 010) stay as they are.

## Why (found on the dev site, 2026-10-05)
1. "Continue booking" leaves the chat for an 8-step wizard.
2. Siya asks "Is this correct?" twice (bubble and card).
3. Details in the first message ("leaking from the bottom") are ignored and asked again.
4. The step counter jumps from 1 of 8 to 4 of 8.
5. "Anything else pros should know?" is asked again after the chat.
6. Location is asked twice (suburb, then property), followed by "This property is in Memorial Park. Check coverage there instead?"

## User stories
- As a customer, I want to tell Sortd what's wrong once and only be asked what it still needs.
- As a customer, I want to see where I am (3–4 steps) and never be sent to another page halfway through.
- As a customer, I want to know straight away, as soon as I give my address, whether Sortd has pros for my job there.
- As the founder, I want one booking flow to maintain instead of a chat plus a wizard.

## Acceptance criteria

**Entry points (all open the same thread)**
1. The home hero "Get help with a job", the account home, trade pages and service links all open `/book`. A trade link opens it with the trade already selected. A service link opens it with the service selected. The signed-in account home has a "What's going on at home?" box with 4 chips (Blocked drain, No power, No hot water, Leaking geyser) that opens the thread with that text as the first message.
2. With no trade chosen, the thread opens with Siya's greeting (it says it's an AI assistant), the active trades as tiles, and a free-text box.
3. The old wizard URLs (`/book/{trade}/{service}`) redirect to the thread with that service selected. Drafts in progress (spec 005 AC12) open in the thread at their next missing item.

**Progress bar**
4. A bar at the top shows four fixed steps for everyone: **Describe → Where & when → Photos → Confirm**. The current step is highlighted, and finished items collapse into green "✓" cards (e.g. "You selected: Plumbing", "Location confirmed: 12 Sample Rd, Morningside", "Date confirmed: Wed 7 Oct, morning") with a "Change" link.

**Describe**
5. After a trade tile, Siya asks "What's the {trade} problem?" with that trade's active services as chips, plus "Other" and the text box.
6. Free text (from the start, the home box, or "Other"): Siya suggests a service and the customer sees **one** confirmation card, "Sounds like **{service}** ({trade})", with Yes / Something else. Siya's bubble doesn't repeat the question.
7. As soon as the service is known, everything the customer has already said is matched against that service's questions (same validation as spec 016), and those questions are **skipped**. Only unanswered questions are asked, one at a time, in Siya's words, with the real options as chips. Typed answers are accepted.
8. When the service has stored safety advice, or an answer is in `urgent_if`, the stored advice is shown as a highlighted card (word for word, "This is guidance, not a guarantee"). The emergency card for gas, sparks, smoke or flooding stays as in spec 016.
9. When the required answers are in, Siya says it has what it needs and offers **Continue** / **Add more details**. Extra details go into the job notes. The customer's own chat messages are already saved to the notes, so nothing is asked again later.

**Where & when**
10. **Sign in first.** A guest can describe the problem, but before Where & when the thread shows "Sign in to book" with Sign up / Sign in (spec 014) and phone verification (spec 001). After sign-in the thread reopens at Where & when with everything kept. Then a card lists the customer's saved properties plus "Add another property" (the spec 004/015 address search, inside the card). This is the **only** location question.
11. As soon as a property is picked, the card shows "Looking for pros near you…" and runs the coverage check (spec 006, 044 rules). If covered: "Good news, we have vetted pros for this in {suburb}." If not covered: "We don't have pros for {service} in {suburb} yet" with **Keep me updated** (joins the waitlist with the account's details), **Choose a different service**, or **No thanks**.
12. Then a date card: a calendar for the next 30 days, then chips **Morning (07–12) / Afternoon (12–17) / Flexible**. For an emergency-capable service, an **Urgent — today** chip shows above the calendar (as in spec 005 AC7).

**Photos**
13. A photo card: "Optional, but recommended. Up to 5 photos", with **Upload from gallery**, **Take a photo** (opens the camera on phones) and **Skip for now**. Rules are as in spec 012.

**Speed**
14. Nothing in the thread reloads the page: every tap updates the thread in place, the tapped chip shows as the customer's message straight away (before the server answers), and buttons show a spinner while waiting. Links between Sortd pages use Livewire's in-page navigation (`wire:navigate`, prefetched on hover) instead of full page loads, and built CSS/JS files are cached by the browser for a year.

**Confirm**
15. Siya says "Here's a summary of your booking" and shows a summary card: **Need help with** (service), **What's wrong** (answers in one line each), **Notes**, **Photos** (thumbnails), **Address**, **When**, and the AI-written job description for pros ("Written with AI help, please check it", editable). There's a "Change" on each section that jumps back to that card in the thread, then returns to the summary.
16. **Confirm booking** (Siya books the job when the customer taps it; never without the tap) posts through `PostServiceJob` with all spec 005 AC10 checks. Below it: "By confirming, you agree to the Terms and allow us to share your job (without your street address) with up to 3 vetted pros." Then the thread shows "Your job is posted" with a link to the job page, and matching starts (spec 009).

**Without AI**
17. When the AI is off, over budget or failing (spec 016 AC10–12), the thread still works fully using taps: trade tiles, service chips and question chips. The text box shows "Tap an option to continue" and free text is saved as notes. There is no separate form and no "Book without Siya" link.

**Housekeeping**
18. The thread is autosaved to the draft job at every step (signed in) or the session (signed out), so a refresh or sign-in resumes it. **Restart** clears it after a confirm.
19. The `Booking\Wizard` component, its view and its routes are removed. Its rules (validation, drafts, rate limits, posting) live in the shared Actions the thread calls. Its tests are moved to the thread.

## Screens / UX
- One column, max 640 px, full screen on mobile. A header with the Siya avatar, "Sortd's AI assistant · can make mistakes" and Restart. The progress bar sits under the header.
- Messages and cards scroll, and the newest is always in view. The input stays at the bottom.
- Card types: trade tiles, chips, service confirm, safety, emergency, property picker, coverage result, waitlist, calendar + window, photos, sign-in, summary, posted.
- States: typing indicator, streaming reply, "Looking for pros near you…", error with Retry, AI unavailable (taps only).
- Accessible: new messages are announced, all chips and cards work by keyboard, and focus moves to each new card.

## Rules and edge cases
- Changing the service from the summary drops answers for the old service (with a note, as in spec 016).
- Changing the property re-runs coverage. If it's no longer covered, the waitlist card replaces the summary.
- The limits in spec 016 AC10 (30 messages, hourly and daily budget) still apply. Tap answers don't count as AI calls.
- Up to 5 open drafts and 10 posts a day, as in spec 005.

## Data changes
- None required. The thread state lives on the existing `service_jobs` draft (answers, notes, property, date, window) plus the session for chat text (spec 016's decision).
- Update `docs/product/user-journeys.md` C2 and mark spec 005's wizard screens and spec 016's hand-off as replaced.

## Security and privacy
- The same as spec 016: scrubbed text to the model, server-side validation of every service, answer and property, the street address hidden from pros until a quote is accepted, and Siya never posts.
- Policies: a customer only sees and changes their own draft. A guest's chat and answers are held in the session and moved to a draft on sign-in.

## Out of scope
- **Chat with the quoting pros, extra photos and estimate → deposit → changed final amount that the customer approves**: spec 018 (founder, 2026-10-05). This brings in-app chat into v1, which the PRD had parked.
- Price guides or cost estimates from Siya (founder: no price list).
- Matching one pro instead of up to 3 (founder: keep up to 3 quotes).
- Kandua's "Explore" guides and "Home Hub".

## Open questions
1. Wording of the coverage line: "we have vetted pros for this in {suburb}" (we don't show ratings until there are reviews).

## Progress
- 2026-10-05: built on `feat/017-one-thread-booking`.
  - New `App\Livewire\Booking\Thread` (view `livewire/booking/thread.blade.php`) replaces `Booking\Wizard`, `Assistant\Chat` and `Account\Book`. Routes: `/book`, `/book/{trade}`, `/book/{trade}/{service}` (old links still work), `/app/jobs/{job}/continue`. `/help` and `/app/book` redirect to `/book`.
  - All business rules still run through the existing Actions (`SaveBookingDraft`, `PostServiceJob`, `SaveProperty`, `JoinWaitlist`, `StoreJobPhoto`, `ChatWithSiya`). No database changes.
  - Tap answers never call the AI. Typed text does: before a service is chosen Siya suggests one, and once it's chosen, earlier messages are checked for answers (Siya's instructions now say to use the whole conversation and never re-ask).
  - Waitlist from the thread uses the signed-in account's first name and number. The form checks (address-like suburb text, consent, phone, throttling) are now tested on `JoinWaitlist` directly.
  - Speed: the thread never reloads the page, a tapped chip shows straight away, 67 internal links use `wire:navigate.hover`, and the test-site Caddy config caches `/build/assets/*` for a year (`immutable`) and images for a week.
  - Not done: moving the test server closer to Durban. Each round trip to Stockholm is about 200 ms, so every tap costs at least that. AWS Cape Town (af-south-1) would be about 20–40 ms, and that's a hosting decision for the founder (decision 014).
- 2026-10-05: approved by the founder with changes: guests sign in before Where & when; Siya books the job only when the customer taps **Confirm booking** after the summary; the app must stop feeling like a full page load on every click (AC14). Measured on the test site: the server answers in a few milliseconds, but each round trip to Stockholm is about 200 ms, no link used `wire:navigate`, and built assets had no cache header.
- 2026-10-05: drafted after testing Kandua signed out and signed in (`docs/product/kandua-reference.md`) and walking the Sortd dev site.
