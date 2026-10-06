

## Spec 020 build — trades, facts, distance matching, Siya agent (2026-10-06, Claude)

Branch `feat/020-trade-distance-matching` in worktree `/Users/andymichaels/Documents/getsorted/sortd-020`, from `deploy/combined`. Spec: `docs/specs/020-trade-and-distance-matching.md`; decision 051. Founder approved; **quality gate deferred by founder instruction** (see below).

### Built (all phases of the spec, unverified)
- Schema + backfill + drops: `database/migrations/2026_10_07_000001_move_to_trades_and_distance_matching.php` (irreversible; drops services, scoping_questions, pro_services, pro_service_areas, suburbs) and `database/settings/2026_10_07_000001_update_matching_settings_for_distance.php`.
- Pros: trades + Google Places base address + radius (application steps `trades`, `base`); registrations are badges, never gates; admin views updated.
- Matching: `EligibleProsQuery` (trade + PostGIS distance, soft edge), `RunInviteWave` (up to 10 at once, tops up), `QuoteFlow::maxQuotes()` (5), pro job view shows highlighted facts, area and approximate distance.
- Customers: Places-only property form; `Booking\Thread` rewritten around trade + facts (stage derived); new date/time sheet (Alpine modal, no dependency); waitlist by trade + area + point; public home, trade page and copy updated.
- Siya: `BookingState`, `BookingToolbox` (the only way the model changes state), `SiyaAgent` (tool-using, plain-text reply), `ChatWithSiya` (reply guard, one regeneration, deterministic fallback), 8 tools in `app/Integrations/Anthropic/Tools`.
- Docs: data model, matching.md, decisions 051, spec index. Written but **not run**: `tests/Unit/BookingToolboxTest.php`.

### Checks run so far (only these)
PHP lint of changed files, Blade compile (`view:cache`), `route:list` boots. **Nothing else**: no migration was run, no tests, no Pint, no PHPStan, no live Gemini, no browser.

### Deferred checks (do before preview approval, merge or live use)
1. Run the migration on a throwaway local database (needs PostGIS) and confirm the backfill: trades registration/safety advice, pro_trades, job trade_id/location, properties area_label/location, waitlist.
2. `PGOPTIONS='-c timezone=UTC' COMPOSER_PROCESS_TIMEOUT=0 composer check`, `npm audit --audit-level=high`, `vendor/bin/pint`.
3. **Rewrite the tests that still describe services/suburbs/scoping** (they will fail or not load); do not delete them without approval, port them:
- tests/Feature/Assistant/AnthropicScopingAssistantTest.php
- tests/Feature/Assistant/ScopingAssistantTest.php
- tests/Feature/Assistant/SiyaChatTest.php
- tests/Feature/Assistant/SiyaConversationTest.php
- tests/Feature/Catalogue/CatalogueAdminTest.php
- tests/Feature/Catalogue/ImportCatalogueTest.php
- tests/Feature/Matching/InviteWavesTest.php
- tests/Feature/Matching/MatchingScreensTest.php
- tests/Feature/Places/AddressAutocompleteTest.php
- tests/Feature/Places/PropertiesTest.php
- tests/Feature/Places/SuburbsTest.php
- tests/Feature/Pros/ProApplicationTest.php
- tests/Feature/Pros/ProVettingScreensTest.php
- tests/Feature/Quotes/QuoteScreensTest.php
- tests/Feature/Quotes/QuotesTest.php
- tests/Feature/ServiceJobs/BookingFlowTest.php
- tests/Feature/ServiceJobs/CoverageWaitlistTest.php
- tests/Feature/ServiceJobs/JobChatTest.php
- tests/Feature/ServiceJobs/JobPhotosTest.php
- tests/Feature/ServiceJobs/OpenCoverageTest.php
- tests/Feature/ServiceJobs/ServiceJobDomainTest.php
- tests/Support/booking.php
   Also `tests/Support/booking.php` (helpers still use Service/Suburb) and `database/factories` users of removed models.
4. Live Siya evaluation on a configured preview with synthetic conversations (the 13 cases from the audit, esp. repeated-question, "bathroom light keeps tripping", dead-end, multi-job). Confirm Gemini accepts function calling with the installed SDK, set the model id/thinking, and check latency (a turn is now several model calls).
5. Check the unit-of-budget: `AssistantCalls` counts one call per turn; the SDK makes up to 6 HTTP calls per turn.
6. Privacy notice + POPIA: Google Places is now used for pro base addresses (stored encrypted); update the notice before launch (decision 049, 051).
7. Manual pass: pro application (trades, base address, radius), job post → 10 invites → 5 quotes → "job full", unverified electrician label, calendar sheet on a phone, emergency pause.

### Known gaps / follow-ups
- Class `AnthropicScopingAssistant` still has its legacy name (it is the provider-neutral Gemini/Bedrock/Anthropic adapter).
- `App\Domain\Assistant\Enums\AiPurpose::SuggestService` kept so old usage rows still read.
- Parked second jobs are remembered and mentioned after posting but there is no one-tap "start the next job" yet.
- Timing stated in chat ("tomorrow morning") is not captured (spec decision D1 default OFF).
- Node/Vite assets were not rebuilt; `vendor/` was copied from `sortd-gemini` (same composer.lock).
