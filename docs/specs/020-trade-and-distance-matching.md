# Spec 020 · Trades, extracted job facts and distance matching (and the Siya agent engine)

Status: Approved by founder, in build (2026-10-06) · Phase: 2–3 · Owner: founder · Decision: 051
Supersedes the service/scoping model in specs 003, 005, 006, 009 (matching) and 016/017/019 where they conflict.
Absorbs the Siya engine redesign from the Siya audit (see "Siya" below).

## Goal
Remove services, scoping questions, suburbs and suburb coverage. A job is a **trade** + the customer's own words + **facts
Siya extracts from natural conversation** + photos + a time preference + a **geocoded address**. When posted, the job is
offered to up to **10** approved pros of that trade nearest to the job (within their radius). The **first 5 quotes** are accepted.
Pros see the extracted facts highlighted and decide for themselves whether they can help.

## Founder decisions (2026-10-06)
1. Radius: each pro's radius defaults to 15 km with a soft edge of 2 km (up to 17 km). Within 15 km always eligible;
   15–17 km eligible only to fill the 10 invites when fewer than 10 are found closer. Never beyond 17 km.
2. Registration: electricians (and any trade with a registration) may be **verified or unverified**. Unverified pros can still be matched.
   A verified registration shows as a badge; unverified is labelled to the customer. Registration never gates matching.
3. Invites: up to 10 at once; the job accepts the first 5 submitted quotes, then shows "job full" to later pros. Replaces invite waves and the 3-quote cap.
4. Build on a new worktree from `deploy/combined`. No quality gate for now (see "Deferred checks").

## Defaults I will use unless you object
- D-a "Today"/urgent time window is available for every trade (it was per-service `emergency_capable`).
- D-b Reviewed safety advice moves from services to the trade (short, static, human-reviewed text); Siya never writes safety instructions.
- D-c Customers are not blocked by area. A job posts with whatever eligible pros exist; if none are within 17 km the customer is offered the waitlist
  (stored by coordinates + trade, not suburb) and nothing is posted.
- D-d Pros see approximate distance ("about 6 km away") and the area name from Places, never the street address or exact coordinates, until their quote is accepted.
- D-e Siya decides when it has "enough" (trade known + at least one concrete problem fact); there are no mandatory questions.

## Data model (expand → switch → contract; never edit old migrations)
Expand (new migrations):
- `pros`: `base_location` geography(Point), `base_address` (encrypted), `base_place_id`, `base_suburb_label`, `service_radius_km` (default 15).
- `pro_trades` (pro_id, trade_id) replaces `pro_services`; backfilled from the trades of existing `pro_services`.
- `service_jobs`: `trade_id` (backfill from service), `problem_text` (scrubbed customer words), `facts` jsonb
  `[{id, text, turn, highlight: true}]`, `location` geography(Point) + `area_label` copied from the property at post time, `service_id` made nullable.
- `properties`: `area_label`; `suburb_id` made nullable.
- `waitlist_entries`: `location`, `trade_id`, `area_label`; suburb/service columns nullable.
- Settings: `MatchingSettings` gains `invite_count` (10), `soft_edge_km` (2), `default_radius_km` (15), `max_quotes` (5); `wave_*` retired.
Switch: code stops reading services, scoping answers, suburbs and `pro_service_areas`.
Contract (a later migration, after verification): drop `services`, `scoping_questions`, `pro_services`, `pro_service_areas`, `suburbs`
usage, `service_jobs.service_id`, `scoping_answers`. Existing demo data is backfilled, not lost; confirm there are no real customer jobs before dropping.

## Matching (`EligibleProsQuery`)
Approved, `approved_at` set, pro has the job's trade, not excluded by this customer, under weekly cap,
and `ST_DWithin(pros.base_location, job.location, radius + soft_edge)`. Rank: within-radius pros first, then fewest invites in 7 days, then nearest;
soft-edge pros only if still short of 10. Create all invites in one transaction (`RunInviteWave` becomes `InviteNearestPros`).
`QuoteFlow::MAX_QUOTES` reads the setting (5). `SubmitQuote` returns "job full" after 5. Invite expiry stays configurable.
PostGIS geography `ST_DWithin` with the existing spatial indexes; add one on `pros.base_location`.

## Pro side
Application: step "Services" → "Trades" (choose trades); step "Areas" → "Where are you based?" with the same Google Places autocomplete used for
customers (spec 015, `SearchesAddresses`, `GooglePlacesGeocoder`) plus radius (default 15 km, editable within limits). Admin application view shows
base address, radius and registrations. Job invite/page shows trade, `problem_text`, **highlighted facts**, photos, time window, distance and area label,
quote count and "job full". Registrations (electrical registered person, PIRB) remain documents with verified/unverified status → badge.

## Customer + Siya
Booking thread: trade → (Siya conversation) → sign in → address (Places, saved property) → when → photos → review → Confirm booking.
Removed: service picker, scoping question cards, answer chips, coverage/waitlist-by-suburb, service-change recheck.
Review shows the extracted facts, editable (remove/add), the trade, address, time and photos.

### Siya engine (from the audit)
Single tool-using agent (Laravel AI SDK `HasTools`, `MaxSteps` ≈ 6, plain-text final answer; do not mix function calling with JSON-schema output).
The application owns `BookingState` (trade, facts with ids, urgency, parked jobs, emergency pause, progress derived by a pure `BookingProgress`).
Tools (thin wrappers over Actions, per-item validation, results returned to the model):
`get_booking_state`, `set_trade(trade_key, confidence)`, `add_job_fact(text, evidence)`, `remove_job_fact(id)`, `set_urgency(level)`,
`park_job(summary)`, `offer_next_step(sign_in|location|when|photos|review)`, `flag_emergency(reason)`.
Because there are no enumerated questions, the "repeat the question / answer rejected as not an exact option" failures (P1, P2) disappear structurally.
Siya asks only what helps a pro quote (what, where in the home, how long, how bad, access) and stops when it judges there is enough.
Prompt: short (~250 words) role/voice/rules; compact trade list; reviewed product facts. Policy stays in code: `EmergencyGuidance` (deterministic, first),
`Redactor`, `SummaryRules`, rate/budget (`AssistantCalls`, counted per turn). Reply guard: regenerate once, then a deterministic reply from state.
No diagnosis, no prices, no promises; Siya never refers to controls that are not shown.
Keep behind `sortd.ai.siya_engine = legacy|agent` until the live eval passes; legacy needs services, so it is retired in the contract phase.
Fact extraction rules: facts are short, customer-supported phrases ("tap drips when closed", "DB trips when light is on"); each carries an evidence quote
that must appear in a scrubbed customer message; a failed quote is a tool error, never a failed turn. Corrections replace or remove facts.

## Privacy and safety (unchanged unless stated)
Model context excludes names, street addresses, contacts, IDs, payment data, coordinates. Pros never see street or contacts before acceptance (D-d).
Emergency routing stays deterministic. Only the customer's tap on Confirm booking posts a job.

## Acceptance criteria
1. No Service/ScopingQuestion/Suburb is required to create, post, match or quote a job.
2. A pro registers a Places address and radius; a customer saves a Places address; both store a geography point.
3. A posted job invites up to 10 eligible pros ordered per the ranking rules; none beyond radius + 2 km; none of the wrong trade.
4. The 6th quote attempt is refused with "job full"; the customer sees at most 5 quotes.
5. Unverified electricians can be invited and quote; their quotes show "registration not verified"; verified ones show the badge.
6. Pro job view shows extracted facts highlighted and approximate distance, never street address or exact coordinates.
7. Siya extracts facts from natural messages, never re-asks a stated fact, accepts corrections, offers the next step when it has enough, never dead-ends,
   and produces no "something went wrong" for a validation rejection.
8. No eligible pro within 17 km → waitlist by coordinates and trade; nothing is posted.
9. Existing emergency, privacy, ownership and posting behaviours are unchanged.
10. Admin can manage trades, pros, jobs; the Services/Questions/Suburbs admin screens are removed in the contract phase.

## Delivery phases
1. Foundation: spec + decision 051, worktree from `deploy/combined`, expand migrations + backfill, settings.
2. Pro side: trades, Places base address, radius, admin views, badges.
3. Matching: distance query, 10-at-once invites, 5-quote cap, job-full, pro job view with highlighted facts.
4. Customer + Siya: agent engine, thread simplification, address via Places, review with editable facts, waitlist by coordinates.
5. Contract: remove services/questions/suburbs code, UI and tables; update docs (data model, matching.md, decisions, user journeys, product facts, public pages).

## Deferred checks
No quality gate for now by founder instruction. Before launch: run `composer check`, the full Pest suite, `npm audit --audit-level=high`,
the Siya live eval on a preview with synthetic conversations, a manual pass of the pro application and matching, and the POPIA/Gemini checks (decision 049).
