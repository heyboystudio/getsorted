

## Spec 020 build — trades, facts, distance matching, Siya agent (2026-10-06, Claude)

Branch `feat/020-trade-distance-matching` in worktree `/Users/andymichaels/Documents/getsorted/sortd-020`, from `deploy/combined`. Spec: `docs/specs/020-trade-and-distance-matching.md`; decision 051. Founder approved; **quality gate deferred by founder instruction** (see below).

### Built (all phases of the spec, unverified)
- Schema + backfill + drops: `database/migrations/2026_10_07_000001_move_to_trades_and_distance_matching.php` (irreversible; drops services, scoping_questions, pro_services, pro_service_areas, suburbs) and `database/settings/2026_10_07_000001_update_matching_settings_for_distance.php`.
- Pros: trades + Google Places base address + radius (application steps `trades`, `base`); registrations are badges, never gates; admin views updated.
- Matching: `EligibleProsQuery` (trade + PostGIS distance, soft edge), `RunInviteWave` (up to 10 at once, tops up), `QuoteFlow::maxQuotes()` (5), pro job view shows highlighted facts, area and approximate distance.
- Customers: Places-only property form; `Booking\Thread` rewritten around trade + facts (stage derived); new date/time sheet (Alpine modal, no dependency); waitlist by trade + area + point; public home, trade page and copy updated.
- Siya: `BookingState`, `BookingToolbox` (the only way the model changes state), `SiyaAgent` (tool-using, plain-text reply), `ChatWithSiya` (reply guard, one regeneration, deterministic fallback), 8 tools in `app/Integrations/Anthropic/Tools`.
- Docs: data model, matching.md, decisions 051, spec index. Written but **not run**: `tests/Unit/BookingToolboxTest.php`.

### Quality gate results (2026-10-06, run on a throwaway local PostGIS database)
- Migration: run against seeded old-schema data; backfill verified (this found and fixed an ordering bug: job locations were empty). Old tables confirmed dropped.
- Pest: **681 passed, 0 failed** (full suite, before the last small additions). Since then each added/changed file was run on its own and passed: BookingFlowTest (52), SiyaChatTest (13), SiyaGeminiWireTest (2). The suite was not re-run in full after those additions.
- Pint passed; PHPStan: no errors (it caught a stale type reference); `composer audit`: clean; frontend build passed.
- `npm audit --audit-level=high`: **2 critical, dev-only and already present** (`concurrently` → `shell-quote`, lockfile unchanged by spec 020). Needs a dependency decision (`npm audit fix` bumps `concurrently`).
- Rector (`composer rector:check`) reports style rewrites in 35 files; it is not part of `composer check` and was not applied.
- Bugs found and fixed by running things: matching settings admin page still had wave fields; a new pro's application page crashed (null radius); removing a fact kicked the customer out of the editor; Continue-to-book did not save the draft; address partial `$label` collided with the form's `label`; "of 3" quotes hard-coded on the customer page.
- Obsolete tests removed: `tests/Feature/Places/SuburbsTest.php` (suburbs no longer exist). Old service/scoping tests were rewritten for trades, facts and distance, not deleted.

### Still not verified
- **Live Gemini**: evaluated on 2026-10-06 (see decision 051 addendum): `gemini-3.1-flash-lite`, thinking `minimal`, 7/8 cases passed in two runs; failures were provider timeouts. Set `SORTD_AI_MODEL` on the server (2.5 models are retired). Still do a human read of replies on a preview. Earlier note: `SiyaGeminiWireTest` proves the SDK/Gemini wire loop with HTTP faked (tool calls run, results returned, plain-text answer). Judgement and tone need `php artisan siya:eval --live` on a preview (8 synthetic cases from the audit; checks state, not wording). Also set the model id / thinking and check latency and the per-turn budget unit.
- No browser pass: pro application, job → 10 invites → 5 quotes → "job full", the calendar sheet on a phone, emergency pause.
- Privacy notice gained one sentence on Google Places and encrypted pro addresses; the legal text is still placeholder and needs lawyer review.

### Known gaps / follow-ups
- Class `AnthropicScopingAssistant` keeps its legacy name (provider-neutral adapter).
- `AiPurpose::SuggestService` kept so old usage rows still read.
- Timing stated in chat ("tomorrow morning") is not captured (spec decision D1 default OFF).
- "Book the next job" after posting exists (`startNextJob`); parked jobs are limited to 3.
- `vendor/` was copied from `sortd-gemini` and `node_modules` is a symlink to it; `.env` and `public/build` are local and git-ignored.

## Cancel, notifications, admin host (2026-10-07)

- **Why pros were never alerted:** the queue worker only read the `default` queue; matching and notification jobs sit on `matching` and `notifications`. The worker now runs `--queue=matching,notifications,default` (`deploy/preview/compose.yaml`). Redeploying the `queue` service processes the backlog.
- **Notifications:** `Notify::user()` → `UserNotice` (database always; email only for key events and only to verified emails). Inbox at `/notifications`, bell with unread count in the app layout. No contact details or street addresses in any notice. WhatsApp still goes as before.
- **Client cancel:** `CancelJobByCustomer` (open job, no accepted quote): invites closed, quotes declined, invited pros told. After acceptance the client is told to contact support.
- **Messages cannot be deleted** by clients or pros any more (`delete_within_minutes` removed). Older deleted rows still render as "Message deleted".
- **Admin host:** set `SORTD_ADMIN_DOMAIN=dashboard.usesorted.co.za` (panel moves to the host root, `/admin` on the main host redirects). Add the host to `SERVER_NAME` so Caddy issues its certificate. Test: `SORTD_ADMIN_DOMAIN=admin.sorted.test vendor/bin/pest tests/Feature/Admin`.
- **Cloudflare:** set `SORTD_BEHIND_CLOUDFLARE=true` before turning the proxy on (trusts Cloudflare ranges for real visitor IPs) and use SSL mode Full (strict). The pgsql connection now pins its session timezone to UTC.
