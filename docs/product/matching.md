# Matching

Goal: every posted job gets up to **3 quotes** from vetted, nearby pros without the customer reposting.

## Eligibility (all must be true)

1. Pro status is `approved` and not `suspended` or `paused`.
2. Pro offers the job's service (`pro_services`).
3. Pro covers the property's suburb (`pro_service_areas`).
4. Pro holds any registration the service requires (e.g. Electrical CoC requires a registered-person record that is verified and not expired).
5. Pro is under their weekly job cap (optional, set by the pro).
6. Pro was not the subject of an upheld dispute with this customer.

The **coverage check** in booking runs the same eligibility query and stops the customer early if the count is 0 (waitlist instead).

## Service areas (v1)

- A `suburbs` table holds eThekwini suburbs with a PostGIS point (centroid) and optional polygon.
- Pros pick suburbs they serve (checkbox list grouped by region); admin can edit.
- A property is assigned to a suburb when saved (polygon contains point; else nearest centroid within 5 km; else "outside launch area").
- Later: radius-based service areas using PostGIS distance.

## Ranking eligible pros

V1 ranks approved eligible pros by fewest invites in the last 7 days, with random tie-breaking (spec 009). The richer signals below are future work, after ratings and response data exist.

Future score (weights and settings to be decided when these signals exist):

| Signal | Why |
|---|---|
| Average rating (Bayesian-smoothed) | Quality |
| Quote response rate and median response time (last 90 days) | Speed |
| Distance from pro base to property | Travel time |
| Invites received in the last 7 days (lower is better) | Fair rotation; new pros get work |
| Reliability strikes (cancellations, no-shows) | Trust |

## Invite waves

1. Wave 1: invite the top **5** eligible pros (configurable).
2. Each invite expires after 24 h.
3. If after **12 h** the job has fewer than 2 quotes, invite the next 3.
4. Stop later waves once the configurable "enough quotes" threshold is reached (2 by default). Spec 010 will add the three-submitted-quote cap and close remaining open invites when the job is full.
5. Admin can manually invite an eligible pro who has not been invited, or stop further matching waves. Existing open invites remain valid when matching is stopped.

## What pros see in an invite

Trade, service, scoping answers, AI summary, photos, suburb (not street), preferred dates and time window, how many pros were invited, how many quotes are in. **No** customer name, phone or street address until their quote is accepted.

## Anti-leakage

- WhatsApp contact between customer and pro is enabled only after acceptance.
- Messages and quote notes are scanned for phone numbers and bank details before acceptance and masked; repeated attempts create a flag for admin.
