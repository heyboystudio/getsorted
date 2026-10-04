# Spec 007 · AI scoping assistant

Status: Done (awaiting merge) · Phase: 2 · Owner: founder

## Goal
Let a customer start a booking by describing the problem in their own words, and give every posted job a short, neutral summary for pros. The assistant only suggests: the customer confirms the service and edits the summary, and booking works fully when the assistant is unavailable.

## User stories
- As a customer who doesn't know the trade or service name, I want to type what's wrong and be pointed to the right service.
- As a customer, I want a clear description of my job written for me, which I can correct before posting.
- As an admin, I want to see that a summary was AI-assisted and how the assistant is performing, without reading customers' raw text in logs.

## Acceptance criteria
**Describe your problem (home page)**
1. Given the home page, when a guest or customer types a description (10–500 characters) and taps "Find a pro", then the server sends the stripped description and the active catalogue keys to `ScopingAssistant::suggestService` and shows a loading state.
2. Given a suggestion whose trade and service keys exist, are active, and whose confidence meets the configured threshold, then the customer sees "Is this what you need?" with the service name and description, and can confirm it or choose another service. Nothing starts until they tap.
3. Given no suggestion, invalid keys, an inactive service, low confidence, a timeout or a provider error, then the customer sees the trade tiles with a short "Choose the closest service" message. No error page, and the description is kept.
4. Given the customer confirms a service, then booking starts at the coverage step (spec 006), and the description is offered as the starting text of the notes step, where they can edit or clear it. Prefilled scoping answers from the assistant are ignored in this spec. The customer always answers every question.

**Job summary (review step)**
5. Given a draft reaching the review step, when the service, answers or notes have changed since the last summary, then the server requests `ScopingAssistant::summarise` with the service key, the answers and the stripped notes, and shows a loading state on the summary only. The rest of the review step works meanwhile.
6. Given a valid summary, then the review step shows it as "Job description for pros" with an "Edit" control and a "Written with AI help, please check it" note. Given no valid summary, then the review step shows the current answers + notes description (spec 005 behaviour) and posting is not blocked.
7. Given the customer edits the summary (max 600 characters), then their text replaces the AI text and is not sent back to the assistant. Later changes to answers or notes regenerate a summary only if the customer has not edited it. Otherwise the customer is told the description may need updating.
8. When the job is posted, then `service_jobs.ai_summary` stores the shown summary (after the server re-validates it) and `ai_summary_source` records `ai`, `customer_edited` or `none`. Posting never waits on or calls the assistant.
9. Given a service with an electrical registration requirement or safety advice, then the summary area also shows the service's safety advice and "This is guidance, not a guarantee."

**Validation and privacy**
10. Before any call, phone numbers, email addresses, URLs, SA ID numbers and card-like numbers are replaced with placeholders. Tests prove the fake assistant never receives them. Names, street addresses and IDs from the account or property are never passed in.
11. Customer text is sent in a clearly delimited data field. Instructions inside it (for example "ignore previous instructions") cannot change the output format, which is parsed into a typed structure. Output that is not valid JSON, uses unknown keys, exceeds limits, contains a price/currency amount, a phone number, email or URL is discarded and the fallback is used.
12. Summary text is shown escaped. It is never rendered as HTML or Markdown.
13. Each assistant call writes an `ai_usage` row (purpose, provider, model, input/output tokens, latency ms, outcome: ok / invalid / timeout / error / throttled) with no customer text and no user or job identifiers beyond a nullable job ID for summaries. Rows are pruned after the configured period (default 90 days).
14. Suggestions are rate limited per visitor (default 10 per hour) and summaries per draft (default 5 per hour). A daily call budget (default 2,000 calls) switches the assistant off until midnight Durban time; throttled or over-budget requests use the fallback without calling the provider.
15. The assistant is enabled only when a provider is configured and the `ai.enabled` setting is on. In local and testing the fake is used. With the assistant off, the home box goes straight to the trade tiles and the review step shows the spec 005 description.

**Admin**
16. Admin → Jobs shows the stored summary and its source (AI, customer-edited, none) beside the customer's own notes. Admin → AI usage shows daily counts by purpose and outcome, average latency and token totals. No customer text. Only MFA-authenticated admins can view.

## Screens / UX
- Home (360 px): heading, one text box ("Describe the problem, e.g. kitchen sink is blocked"), "Find a pro" button, trade tiles below. The button shows busy while checking. Suggestion card: service name, one-line description, "Yes, book this" and "Choose something else".
- Fallback: trade tiles with "Choose the closest service". The typed description stays in the box.
- Notes step: prefilled with the home description when there is one, labelled "Anything else pros should know?".
- Review step: "Job description for pros" card with a skeleton loader, the AI note, "Edit" opening a textarea with a character count, Save/Cancel. If the summary fails, the card shows the answers + notes text with no error message.
- Safety-relevant services: safety advice block under the summary.

## Rules and edge cases
- The assistant never chooses for the customer, never posts, never changes job status, never sends messages, and never states prices (security baseline §7, PRD §7).
- Concurrency: a summary response for an older version of the draft (answers/notes changed since the request) is discarded.
- A suggestion is only a link to an existing booking route. Tampering with the suggested key cannot start an inactive service (spec 005 guards apply).
- A double tap on "Find a pro" sends one request per visitor at a time.
- The home description lives in the visitor's session until booking starts or 30 minutes pass; it is never written to logs or `ai_usage`. Once the customer saves notes, those notes are normal job data (spec 005 retention).
- Raw provider responses are not stored. Error logs record the outcome class only.

## Data changes
- `service_jobs`: add `ai_summary_source` (string, default `none`) and `ai_summary_generated_at` (nullable). `ai_summary` already exists, capped at 600 characters in the domain.
- New `ai_usage`: purpose (`suggest_service` / `summarise`), provider, model, input_tokens, output_tokens, latency_ms, outcome, nullable service_job_id (null on delete), created_at. Index on (created_at, purpose).
- Settings (`spatie/laravel-settings`): `ai.enabled`, `ai.suggestion_min_confidence` (0.6), `ai.daily_call_budget` (2000), `ai.usage_retention_days` (90). Rate limits in `config/sortd.php`.
- A real provider adapter under `app/Integrations/Anthropic`, implementing `ScopingAssistant` with the Laravel AI SDK (tech stack) after a compatibility check and decisions-log entry. Bound only when configured (decision 024). Default model: Claude Haiku 4.5, configurable, chosen for cost and latency.
- Update `docs/architecture/data-model.md` and the decisions log in the implementation PR.

## Security and privacy
- PII: the customer's free text may contain personal data. It is stripped before sending, never logged, never stored in `ai_usage`, and kept only as job notes the customer saved.
- Cross-border transfer: the AI provider processes data outside South Africa. POPIA requires telling users and having an operator agreement (popia.md lines 9–11). See open question 1.
- Policies: summaries are visible to the job's customer and admins in this spec, and to invited pros in spec 009 under existing job-visibility rules. AI usage is visible to admins only.
- Prompt injection: customer text is data. Output is schema-validated against an allow-list. A test feeds hostile text and checks that only valid catalogue keys can pass.
- No secrets in code. The API key comes from the environment only, and tests never hit the network (`Http::preventStrayRequests()`).

## Out of scope
- AI-prefilled scoping answers (the contract allows them; ignored for now), chat-style follow-up questions, photo analysis, isiZulu, price estimates.
- Showing summaries to pros (spec 009).
- Choosing a production host or turning the feature on in production. That follows the founder's decision below and the privacy notice update.

## Decisions (founder, 2026-10-04)
1. **Overseas AI provider (POPIA):** build now with the fake; ship the real adapter switched off (`ai.enabled` = false) until the privacy notice names the AI provider and the cross-border transfer, and the founder has accepted the provider's data processing terms.
2. **Spending cap:** at most 2,000 assistant calls per day by default, adjustable in admin.
3. **Final say on the description:** the customer's edited version wins; the AI never overwrites it (AC7).

## Progress
- 2026-10-04: Drafted after spec 006 merged in PR #25. Awaiting founder approval and the three decisions above.
- 2026-10-04: Founder approved the spec with all three recommended defaults.
- 2026-10-04: Founder approved the build plan (AI settings page limited to super-admins).
- 2026-10-04: Built on `feat/007-ai-scoping-assistant`; tests in `tests/Feature/Assistant/`. Installed `laravel/ai` 1.0.1 (decision 033). The real Anthropic adapter is bound only with an API key outside local/testing, and the `ai.enabled` setting ships off (decision 1).
- 2026-10-04: Code, security and spec reviews. Fixed:
  - free-text answers are now redacted;
  - street addresses, slashed or non-ASCII-digit phone numbers and spelled-out emails are redacted, and dates are no longer mistaken for phone numbers;
  - replies with unexpected keys are rejected;
  - throttled requests write at most one row per limit per hour, and the daily budget is reserved atomically per Durban day;
  - signed-in customers are limited per account;
  - expired home descriptions are dropped from the session;
  - failures log the exception class only;
  - the description now fills an existing draft without notes;
  - safety advice shows at review even without a summary;
  - the summary card is its own component, so a slow call never holds up the review step;
  - the architecture rules were narrowed.
- 2026-10-04: Checks:
  - `composer check` passed (410 tests, 1,779 assertions; 0 Larastan errors; no Composer advisories);
  - `npm audit --audit-level=high` found 0 vulnerabilities;
  - assets built;
  - home page checked at 360 px.
- Open follow-ups:
  - Trusted-proxy configuration for per-visitor limits (with hosting, decision 014).
  - `zend.exception_ignore_args=On` and error-tracker argument scrubbing in production.
  - Whether customer-edited descriptions should be checked for contact details before spec 009 shows them to pros.
- Awaiting PR review and founder merge approval.
