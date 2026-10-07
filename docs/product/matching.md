# Matching

Goal: every posted job reaches up to **10** vetted, nearby pros of the right trade, and the customer gets up to **5 quotes** without reposting (spec 020).

## Eligibility (all must be true)

1. Pro status is `approved` and not `suspended` or `paused`.
2. Pro offers the job's **trade** (`pro_trades`).
3. The job is within the pro's travel radius of their base address: `service_radius_km` (default 15 km) plus a **soft edge** of 2 km (`matching.soft_edge_km`). Distance is PostGIS geography distance between the pro's `base_location` and the job's `location`, both from Google Places.
4. Pro is under their weekly job cap (optional, set by the pro).
5. Pro was not the subject of an upheld dispute with this customer.

**Registration is never a gate.** A pro with a verified PIRB or registered-electrician document gets a badge; an unverified pro can still be invited and quote, and the customer sees "registration not verified". There are no sub-services and no scoping questions: the job carries the facts Siya extracted (spec 020).

The **coverage check** in booking uses the same query and stops the customer early if no pro is near (waitlist instead) once `GETSORTED_REQUIRE_PROS` is on; before launch a job posts regardless (decision 044).

## Locations (spec 020)

- Customers and pros both pick an address through Google Places autocomplete; the resolved point and an approximate area name (e.g. "Musgrave") are stored. There is no manual address entry and no suburb list.
- Pros see the area name and an approximate distance ("about 6 km away"), never the street address or exact coordinates, until their quote is accepted.

## Ranking eligible pros

Pros inside their own radius rank first, then fewest invites in the last 7 days (random tie-break), then nearest. Soft-edge pros (up to 2 km beyond their radius) are used only to fill invites that nearer pros leave open. The richer signals below are future work, after ratings and response data exist.

Future score (weights and settings to be decided when these signals exist):

| Signal | Why |
|---|---|
| Average rating (Bayesian-smoothed) | Quality |
| Quote response rate and median response time (last 90 days) | Speed |
| Distance from pro base to property | Travel time |
| Invites received in the last 7 days (lower is better) | Fair rotation; new pros get work |
| Reliability strikes (cancellations, no-shows) | Trust |

## Invites

1. When a job is posted, invite up to **10** eligible pros at once (`matching.invite_count`).
2. Each invite expires after 24 h (`matching.invite_expiry_hours`), or after 4 h for an urgent job — flagged urgent by Siya or booked for "Urgent — today" (`matching.urgent_invite_expiry_hours`, added 2026-10-08).
3. The every-five-minutes run tops up missing invites for open jobs posted in the last 24 h, so a pro approved later can still be invited (never the same pro twice).
4. The job accepts the first **5** submitted quotes (`matching.max_quotes`). When it is full every other open invite is closed and late pros see "This job is full".
5. Admin can manually invite an eligible pro who has not been invited, or stop further matching. Existing open invites remain valid when matching is stopped.

## What pros see in an invite

Trade, the highlighted facts the customer reported, AI summary, photos, area name and approximate distance (not street), preferred dates and time window, how many pros were invited, how many quotes are in. **No** customer name, phone or street address until their quote is accepted.

## Anti-leakage

- WhatsApp contact between customer and pro is enabled only after acceptance.
- Messages and quote notes are scanned for phone numbers and bank details before acceptance and masked; repeated attempts create a flag for admin.
