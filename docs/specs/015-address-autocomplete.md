# Spec 015 · Address autocomplete and suburb from address

Status: Draft · Phase: 2 (improves specs 004 and 006) · Owner: founder

## Goal
Customers type their street address and pick it from Google Places suggestions instead of typing an address and choosing a suburb separately. Sortd works out the suburb from the chosen address and says straight away whether that suburb is covered. Fewer wrong suburbs, real map locations for matching, and one less step.

## User stories
- As a customer, I want to start typing my address and pick it from a list, so I don't have to know my official suburb name.
- As a customer, I want to know immediately whether Sortd works at my address.
- As the founder, I want each property to have an accurate map location, so matching distance is right.

## Acceptance criteria
**Autocomplete**
1. Given the add/edit property form (and the booking coverage step), when the customer types 3 or more characters into "Street address", then after a short pause (about 300 ms) up to 5 suggestions from `Geocoder::autocomplete` are shown. Suggestions are limited to South Africa and biased to the eThekwini area.
2. All requests in one address search share a Places session token, so Google bills the search as one session. A new token starts after an address is chosen or the form is reopened.
3. When the customer picks a suggestion, then the server calls `Geocoder::resolve` and fills: street address (number and street), suburb, postal code and location (latitude/longitude). The customer can still edit the street line (for example, to add a unit number).

**Suburb from address**
4. The resolved suburb is matched to the `suburbs` table by Google's sublocality/locality name (case-insensitive, with an admin-editable alias list, for example "Durban North" ↔ "Durban North"). If there's no name match, the suburb with the nearest centroid within 5 km is used.
5. Given the matched suburb is active and has an eligible pro for the chosen service (spec 006 check), then the form shows "Good news, we cover {suburb}."
6. Given the suburb is inactive, unknown, or has no eligible pros, then the form shows "We're not in {suburb} yet" and offers the waitlist (spec 006) with the suburb and service filled in. The property can still be saved (spec 004 AC10).
7. Given no suburb can be matched, then the customer picks the suburb manually from the existing list (spec 004 behaviour).

**Fallbacks**
8. Given Places is unavailable, times out (3 s), is over budget, or no API key is set, then the form falls back to the spec 004 manual fields (street, suburb picker, postal code) with no error page. The location is then the suburb centroid, as today.
9. In local and testing, the Fake geocoder returns a fixed set of Durban addresses. The preview site uses Google Places when `GOOGLE_MAPS_API_KEY` is set, otherwise the Fake.

**Budget and limits**
10. Autocomplete is rate limited to 30 requests per minute per visitor, and resolves to 10 per minute. A daily cap (default 1,000 sessions, editable in admin settings) switches to the manual fallback until midnight Durban time.
11. Each Places call writes a usage row (purpose: autocomplete/resolve, outcome, latency). The row stores no address text.

## Screens / UX
- Street address field with a suggestions dropdown (keyboard and touch friendly, screen-reader announced). States: typing, loading spinner, "No matches, enter your address manually", and fallback to the manual fields.
- After a pick: the address is shown with the suburb and a coverage badge (green "We cover {suburb}" / amber "Not in {suburb} yet · join the waitlist").
- A "Enter address manually" link is always available.
- Google attribution ("powered by Google") is shown under the suggestions, as Google's terms require.

## Rules and edge cases
- The street address stays encrypted (spec 004 AC12). The Google place ID is stored so the address can be re-resolved, and is not considered PII on its own.
- Changing the suburb manually after a pick is allowed. The coverage check reruns.
- Existing properties are unchanged. They gain a location only if edited and re-picked.

## Data changes
- `properties`: add `google_place_id` (nullable string), `location_source` (enum: `places`, `suburb_centroid`).
- `suburbs`: add `aliases` (json, default `[]`).
- `geocoder_usage` table (purpose, outcome, latency_ms, created_at), pruned after 90 days.
- Update `docs/architecture/data-model.md`.

## Security and privacy
- **Key handling:** the API key is server-side only (`GOOGLE_MAPS_API_KEY`). The browser never calls Google directly; all calls go through our server. Restrict the key to Places API (New) and set a quota cap in Google Cloud.
- **What Google receives:** only the typed address text. Never names, phone numbers or account IDs. The privacy notice must name Google as a processor for address lookup (cross-border transfer, POPIA).
- **Logging:** addresses are never logged.
- **Ownership:** the existing policies apply. Only the owner sees their property.

## Out of scope
- Showing a map or dropping a pin.
- Drawing suburb boundaries (polygons). Centroid and name matching are enough for launch.
- Pros' service-area editing.

## Open questions
1. **Privacy notice wording:** naming Google as a processor (cross-border transfer). Needed before the live launch, not before the preview.
2. **Daily cap:** is 1,000 sessions a day OK? At about $0.017 per session with free monthly credit, that's worst case about $17/day.

## Progress
- 2026-10-05: drafted. Places API (New) activated by the founder; the key is on the preview server and local `.env`.
