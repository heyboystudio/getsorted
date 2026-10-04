# Spec 006 · Coverage check and waitlist

Status: In progress · Phase: 2 · Owner: founder

## Goal
Tell a customer whether Sortd can serve their chosen service and suburb before they answer booking questions. When coverage is unavailable, collect a small waitlist request so Sortd can follow up when service becomes available.

## User stories
- As a customer, I want to check my suburb early so I do not spend time describing a job that cannot be served.
- As a customer outside coverage, I want to leave my details once and know what will happen next.
- As an admin, I want to see demand by service and suburb so I can decide where to recruit pros.

## Acceptance criteria
1. After choosing an active service, a guest or signed-in customer chooses a suburb before the first scoping question. The searchable list includes active and inactive launch suburbs; a customer can also enter an unlisted suburb for the waitlist. The choice survives back navigation and sign-in.
2. For an active suburb and service, the server checks whether at least one pro is eligible under `docs/product/matching.md`. The check uses the same `EligibleProsQuery` that matching will use in spec 009. The UI says whether booking can continue without exposing pro identities or exact counts.
3. When the suburb is inactive, unlisted, or has zero eligible pros for that service, booking stops before scoping and offers a waitlist form. Existing booking answers and photos remain on the customer's draft if they return later.
4. The waitlist form asks for first name, South African mobile number, suburb, and service, explains that Sortd will contact the customer about availability, and records the applicable privacy notice consent. Signed-in customers may see prefilled details and can correct them for this request.
5. Submitting valid waitlist details creates one entry per normalized phone, suburb and service combination; retrying or double-tapping does not create duplicates. Show a confirmation that makes no promise about timing or availability. Do not send a booking-posted message or open a job.
6. The waitlist form validates every field on the server, normalizes the phone to E.164, rate limits submissions, and handles temporary failure with a retry message that preserves the form.
7. If coverage was shown and then disappears before posting, posting is refused, the job stays a draft, and the customer is offered the waitlist with the service and suburb filled in. If the selected property is in a different suburb, repeat the coverage check for the property's suburb before allowing the customer to continue or post.
8. Only admins authenticated through the admin login with MFA can view waitlist demand. The admin view groups demand by suburb and service and shows counts only; it does not show phone numbers, names, or street addresses. Any later contact workflow must have its own authorization and audit rules.
9. Waitlist entries are deleted after 12 months unless a shorter period or deletion request applies. Pruning is tested; the build plan must identify the privacy request path for earlier deletion.

## Screens / UX
- On a 360 px screen: service → “Where do you need help?” suburb search → either scoping or “We're not available there for this service yet” with a waitlist form.
- Show loading while coverage is checked. On a network error, let the customer retry without losing their suburb or booking progress. Do not treat an error as confirmed coverage.
- The waitlist form shows concise purpose/consent text, a separate link to the privacy notice, inline validation, a busy state on submit, and a confirmation screen. Returning customers may change service or suburb.
- Saved property selection later in booking displays its suburb and clearly asks the customer to confirm a changed suburb before rechecking coverage.

## Rules and edge cases
- An inactive service cannot be booked or waitlisted through this flow. A service made inactive after the initial check is blocked at posting as in spec 005.
- Eligibility requires every applicable condition in `docs/product/matching.md`: approved and available pro, service, suburb, current required registration, available weekly capacity if capped, and no upheld dispute involving this customer. For a guest, dispute-specific filtering becomes available after sign-in and must be applied before posting.
- An inactive or unknown suburb goes straight to waitlist. Never infer coverage solely from a suburb being active, or from the presence of an application that has not passed vetting.
- A waitlist request is not a job, does not change a job status, and does not reserve a pro. Any job status change still goes through `ServiceJobStateMachine`.
- Use the selected property's actual suburb at posting, not the suburb text previously entered in the coverage step. Concurrent changes to pro eligibility may cause a safe refusal; no false promise of a quote is made.
- The waitlist and coverage check may be used without an account; enforce server-side throttling without sharing whether a phone already exists on the waitlist.

## Data changes
- Add `waitlist_entries` with normalized phone, first name, suburb text, nullable `suburb_id`, `service_id`, privacy consent version/time, and timestamps. Add a unique key suitable for retry-safe submissions. Specify exact indexes and deletion approach in the approved build plan and update `docs/architecture/data-model.md` in the implementation PR.
- Build the reusable eligibility query from the pro/service/area/registration data it needs. The current repository has no pro tables; the build plan must explicitly define the minimal schema needed now and how spec 008 pro onboarding will populate it. Do not represent unvetted applicants as covered.
- The minimal schema also records rolling weekly pro job allocations and upheld pro/customer dispute exclusions; later invite and dispute workflows must write those records. The query uses them now, including the customer's identity after sign-in.
- Add a scheduled 12-month waitlist prune and its test.

## Security and privacy
- Waitlist phone and name are personal data. Do not put them in URLs, analytics, logs, admin lists, or pro views. Store only fields needed to contact the customer about the requested service and suburb. Follow `docs/security/popia.md` for purpose, consent, deletion, and 12-month retention.
- Admin demand screens display aggregate counts and suburb/service only. Any individual-entry access requires an explicit permission and purpose in a future contact workflow.
- Rate limit coverage checks and waitlist submissions, validate selected service/suburb IDs, and prevent duplicate and cross-customer draft access.

## Out of scope
- Pro application and vetting UI (spec 008), invite waves (009), automated waitlist notifications, marketing opt-in, and AI service selection (007).
- Promising a response time or a quote; showing pro names or counts to customers.

## Decisions (founder, 2026-10-04)
1. Keep the strict eligibility rule for live bookings. Until a pro is vetted, customers reach the waitlist. Local-only approved-pro fixtures can support phone walkthroughs.
2. The admin view shows aggregate demand. Individual contact and notifications belong to a later workflow with explicit permissions.

## Progress
- 2026-10-04: Drafted after job photos merged in PR #24. Founder approved the spec and its recommended rollout and admin scope.
- 2026-10-04: Founder approved the build plan. Tests were written first; implementation adds the coverage step, eligibility query, waitlist, posting recheck, retention, aggregate admin demand, and a verified-customer removal path. Local-only demo coverage is seeded separately. Final checks and review are in progress.
- 2026-10-04: Code and security review found missing capacity/dispute filters, no coverage throttle, property suburb confirmation, and possible personal data in unlisted suburb aggregates. Fixed and added regression tests; repeated waitlist submissions now remain idempotent when throttled.
- 2026-10-04: Final review found that a throttled visitor could tell whether a phone was already waitlisted (duplicates skipped the throttle). Every submission is now throttled first and duplicates are absorbed by the unique key; a regression test covers it. `composer check` passed (330 tests, 1,460 assertions; 0 Larastan errors; no Composer advisories); `npm audit --audit-level=high` found 0 vulnerabilities; assets built and the coverage and waitlist steps were checked at 360 px. Awaiting PR review and founder merge approval.
