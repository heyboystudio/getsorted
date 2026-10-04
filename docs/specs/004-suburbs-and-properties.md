# Spec 004 · Suburbs and properties

Status: Done (merged in PR #21) · Phase: 1 · Owner: founder

## Goal
Sortd knows its launch suburbs (with map coordinates) and customers can save the properties where work will be done, each linked to a suburb. This completes Phase 1 ("properties with suburb lookup") and is what the coverage check, booking and matching build on in Phases 2–3.

## User stories
- As the founder, I want the launch suburbs loaded with their locations, and to switch suburbs on or off in the admin panel, so I control where Sortd operates.
- As a customer, I want to save my home (and other properties) once, so I don't retype the address for every job.
- As a customer, I want to know my street address is only shared with the pro I choose.

## Acceptance criteria
**Suburbs**
1. Given a fresh database, when the seeders run, then the suburbs in `docs/product/launch-area.md` exist with name, region, municipality (eThekwini) and a centroid point; boundaries are empty for now.
2. Given the founder's launch choice (open question 1), then only suburbs in the chosen regions are **active**; the others exist but are inactive.
3. Re-running the seeder adds new suburbs only and never changes edits made in the admin panel (same rule as the catalogue).
4. In `/admin` → **Places → Suburbs**, admins with catalogue view rights see suburbs (name, region, active, number of properties); editors (super-admin, support) can switch suburbs on/off, edit name/region, and add a suburb with its coordinates. Suburbs are never deleted.

**Properties (customer area)**
5. Given a logged-in customer, then `/app/properties` lists their saved properties (label, street address, suburb), with an empty state "No saved properties yet" and an "Add property" button.
6. When adding a property, the customer gives a **label** (e.g. "Home"), **street address**, picks a **suburb** by typing (suggestions from the suburbs table, active suburbs first, inactive ones shown as "Coming soon"), an optional **postal code** and a **property type** (open question 3). The form says "We only share your street address with the pro you choose."
7. When saved, the property is linked to the suburb, gets a `public_id`, and its **location** is set (open question 2). URLs use `public_id`, never the numeric id.
8. A customer can edit and delete their own properties; deleting is a soft delete (kept for past jobs).
9. A customer can never see, edit or delete another customer's property (policy-enforced; tested by trying another customer's `public_id` → 404).
10. Choosing an inactive suburb is allowed and the property is saved, with a note "Sortd isn't in {suburb} yet — we'll let you know when we are." (The waitlist itself is spec 006.)
11. Admins can see properties only through future job screens; there is no admin properties list in this spec.

**Privacy**
12. The street address is **encrypted** in the database, never logged, and never shown outside the owner's own pages in this spec.

## Screens / UX
Mobile first (360 px), same style as the account home.
- **`/app`** gains a "Saved properties" link.
- **`/app/properties`**: list of cards (label, street, suburb); "Add property".
- **Add / edit property**: label, street address, suburb (type-ahead), postal code (optional), property type, privacy note, Save. Loading and double-submit protection; friendly errors.
- **Delete**: confirmation "Delete Home? Past jobs keep the address."

## Rules and edge cases
- Suburb type-ahead matches from the start of words, case-insensitive ("morn" → Morningside).
- A customer may have up to 10 properties (prevents abuse; configurable in `config/sortd.php`).
- Pros and pro-only accounts don't have properties in v1.

## Data changes
- `suburbs`: name (unique within municipality), slug (URL key), region, municipality, centroid geography(Point,4326), boundary geography(MultiPolygon,4326) nullable, is_active, timestamps.
- `properties`: public_id (ULID), user_id, label, street_address (encrypted), suburb_id, location geography(Point,4326) nullable, postal_code, property_type, deleted_at, timestamps.
- `waitlist_entries` is left for spec 006.

## Security and privacy
- Property policy: only the owner (customer role) can view/create/update/delete; admins have no access in this spec.
- Street address encrypted at rest (security baseline §6); not in logs or audit-log properties (audit records "property created/updated/deleted" without the address).
- Suburb policy: as catalogue permissions.

## Out of scope
- Coverage check and waitlist (spec 006), booking (spec 005), pro service areas (spec 008).
- Address autocomplete from Google/OpenStreetMap and the map pin (open question 2).
- Suburb boundary polygons.

## Decisions (founder, 2026-10-04)
1. **Q9:** launch with **Berea/central** (Morningside, Musgrave, Berea, Glenwood) and **North** (Durban North, Umhlanga, La Lucia); West and South are seeded but inactive.
2. No map pin or street autocomplete yet: suburb from Sortd's list + typed street; location = suburb centre point. Revisit when a geocoding provider is chosen.
3. Property types: House, Flat/apartment, Townhouse/complex, Business premises, Other.
4. Seed approximate suburb centre points (≈1 km) for the 12 launch-area suburbs; admins can correct them.

## Progress
- 2026-10-04: Drafted for founder review.
- 2026-10-04: Approved as proposed.
- 2026-10-04: Built on `feat/004-suburbs-properties`; tests in `tests/Feature/Places/`. Review found no privacy holes; fixed: duplicate suburb names now give a form error, the seeder skips name clashes, suburb centre moves are audit-logged, tamper tests added, audit entries no longer include the free-text label.
- Deviations/extras: `EnsureCustomer` middleware now guards all of `/app` (pro-only accounts go to the pro area); the pro welcome heading became "Welcome to Sortd Pro" so the greeting isn't repeated; the launch area has 12 suburbs (not 14).
