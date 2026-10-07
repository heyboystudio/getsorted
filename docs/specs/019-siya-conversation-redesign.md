# Spec 019 · Reinvent Siya's conversation and booking workflow

Status: Deployed to preview — conversational trial in progress (2026-10-06) · Phase: 2 · Owner: founder

## Goal
Siya responds to what a person actually says, including messages outside the trade catalogue, before trying to book a job. Ordinary conversation should not be treated as a failed service match. Replace the questionnaire-driven conversation with an intent-aware assistant that recognises possible emergencies, remembers supplied details, asks useful follow-ups and helps the customer reach a clear, reviewable booking.

This proposes behavioural changes to specs 016 and 017. Their existing posting, ownership, coverage, matching and payment safeguards continue to apply. The homepage visual treatment comes later.

## User stories
- As a customer in possible danger, I want appropriate emergency direction immediately instead of being offered plumbing or painting.
- As a customer, I want to explain my problem naturally, correct myself and ask questions without losing my booking.
- As a customer unsure what is wrong, I want Siya to clarify rather than guess a diagnosis or force a service choice.
- As the founder, I want a repeatable way to assess Siya's judgment across many conversations and providers.

## Proposed workflow
1. **Understand the request.** Start with a short invitation to explain the problem. Trade choices remain optional shortcuts. Classify the request as possible emergency, supported home problem, unclear home problem, unsupported request, ordinary conversation, or a Get Sorted product question. Answer product questions using approved product content, then resume the customer's task without forcing a booking.
2. **Handle safety first.** Explicit requests for a fire brigade or emergency help, and credible descriptions of immediate danger, take precedence at any booking stage. Show reviewed emergency guidance and contacts from application-owned content. Do not continue selling or collecting booking details. Ambiguous historical or figurative references get a brief clarification where appropriate; a credible immediate threat gets guidance without waiting for more answers.
3. **Understand the job.** Use the active catalogue and descriptions to identify a service; ask one relevant question at a time when uncertain. Extract all supported answers from what the customer has already said. Never invent a cause, diagnosis, answer or service.
4. **Collect only missing details.** Required scoping fields remain enforced by the server. Questions should serve the job, rather than repeat a form. Handle corrections and interruptions throughout the flow. Identify the service in the background, without a separate confirmation card; show it in the editable review. This is a founder-approved trial to evaluate before making it permanent. Low confidence still requires clarification.
5. **Prepare the booking.** Explain what is needed next; collect sign-in, property, coverage, date/window and optional photos with existing secure controls in the thread. Personal booking data stays outside the model. Remove the extra mandatory Continue / Add more details gate after scoping; customers can add details naturally.
6. **Review and confirm.** Show an editable summary based on customer-provided facts. Only the customer's explicit Confirm booking action posts the job. Explain that pros will quote and availability is subject to matching; never imply a dispatched pro or emergency response.

## Acceptance criteria
1. Given a new conversation, when the customer starts, then Siya identifies itself as an AI assistant, invites a natural description and permits optional trade shortcuts without requiring them.
2. Given “hey siya, please help me. i need fire brigade.”, when it is submitted, then the response prioritises reviewed emergency direction, does not advertise available trades, and does not advance booking.
3. Given a credible immediate hazard at any stage, when the customer describes it, then the conversation enters an emergency pause before any subsequent booking progression; the application supplies the safety card independently of generated prose.
4. Given AI unavailability or invalid output, when an explicit emergency request is submitted, then deterministic checks still show the reviewed emergency response. Provider failures cannot turn this into a generic trade-selection fallback.
5. Given an ambiguous, negated, historical or figurative hazard reference, when assessed, then the flow distinguishes it from a clear current emergency; ambiguous cases ask a relevant clarification. Regression examples include “paint my fireplace”, “the fire was last year” and “there is smoke coming from the socket”.
6. Given an unclear home problem, when no service can be justified, then Siya asks one useful clarification rather than making a confident unsupported selection or presenting the whole catalogue again.
7. Given a clear supported problem, when interpreted, then only an active service belonging to an active trade can be selected in the background, without requiring a service-confirmation tap; the customer can correct it in review.
8. Given a message answering several scoping fields, when processed, then all valid customer-supported answers are retained and only unanswered required fields are asked. The server validates every proposed field and value.
9. Given a correction such as “actually it is the kitchen sink, not the toilet”, when submitted, then affected answers and any incompatible service state are corrected without dropping unrelated booking details; invalidated coverage is rechecked before posting.
10. Given “I don't know” for a field, when submitted, then Siya uses an allowed Not sure value where one exists; otherwise it explains what is needed without inventing an answer or looping over the same question. Changes to required fields or options require explicit catalogue approval.
11. Given a question or relevant new detail during location, date, photos or review, when typed, then Siya handles it in context rather than blindly appending it to pro notes. Only relevant job facts belong in notes; off-topic exchanges and product questions do not.
12. Given a request outside the supported catalogue without immediate danger, when submitted, then Siya responds to the actual need, explains the relevant limit and optionally identifies the kind of service needed; it does not suggest an unrelated trade, invent a referral or force booking. Greetings, thanks, casual chat and unrelated questions receive a brief natural response without changing job facts or asking unnecessary scoping questions.
13. Given collected scoping answers, when ready to proceed, then the thread offers the next necessary booking control without a redundant scoping-completion gate or repeated questions.
14. Given a guest, when personal booking details are needed, then sign-in and phone verification use existing controls and the conversation resumes afterward. Address selection, ownership checks and coverage remain application-controlled.
15. Given address, schedule or payment information in application state, when constructing a model request, then those values and account identifiers are excluded; typed input is redacted under the existing privacy rules before storage and transmission.
16. Given an unsupported key, invalid answer, prompt injection or malformed model response, when received, then the server rejects unsafe proposals, preserves valid prior state and offers a useful retry or manual continuation. Escaped output remains mandatory.
17. Given a booking summary, when displayed, then its facts match the validated draft and its description adds no unsupported claims. A message such as “yes, book it” cannot replace the explicit Confirm booking control.
18. Given a provider timeout, budget limit or disabled AI, when ordinary booking continues, then the manual flow remains usable and collected details persist. Retry does not duplicate customer messages, answers, drafts or posted jobs.
19. Given a refresh or sign-in, when resuming, then validated conversation state, pending questions and emergency pause survive within the existing session/draft persistence boundaries.
20. Given an evaluation fixture covering emergencies, vague requests, multiple answers, corrections, interruptions, unsupported services, injection and provider failure, when the automated suite runs, then expected intents, validated state and allowed actions are asserted without network calls or brittle exact generated-wording assertions.
21. Given a Get Sorted question, when submitted at any stage, then Siya answers from approved product facts, admits when the answer is unknown and retains the booking's pending state. It distinguishes published platform fees from prohibited invented job-price estimates.
22. Given the approved character direction, when Siya responds, then its voice is warm, grounded, encouraging and clear, with the founder's Siya Kolisi reference. It identifies itself as Get Sorted's AI assistant. The identity is explicitly AI, with inspiration only and no impersonation or endorsement.

## Character direction
The founder explicitly identifies Siya Kolisi as Siya's character reference (2026-10-06), superseding spec 016's earlier first-name-only rationale. Proposed voice: approachable, calm under pressure, respectful and encouraging; natural South African English without forced slang, rugby references in every reply or fabricated personal experiences. Those traits are a proposed interpretation for founder review, not claims about the person. Founder confirmed inspiration only on 2026-10-06. Siya introduces itself as Get Sorted’s AI assistant; it does not portray itself as the person, use his likeness or quotations, or claim an endorsement.

## Screens / UX
- Retain the existing chat shell for this phase; no homepage styling migration.
- Empty: short greeting, text input and optional shortcuts.
- Working: immediate customer bubble, typing/loading indicator and duplicate-submit protection.
- Clarification: one contextual question, with actual allowed answer chips where helpful.
- Emergency: prominent reviewed guidance; ordinary booking controls paused. Recovery behaviour needs founder approval below.
- Unsupported: clear explanation; no forced suggestion of an unrelated trade.
- Booking: show only the next relevant secure control, with editable completed details and no visible step counter or wizard progress bar.
- Review: editable facts, photos and description, followed by explicit confirmation.
- Failure: retain state, show retry/manual options and explain the limit without claiming to have understood new text.
- Existing keyboard access, screen-reader announcements and mobile usability remain acceptance requirements.

## Rules and edge cases
- Conversation interpretation proposes typed intents, facts and a next conversational action. Domain Actions validate and apply changes; the Livewire component renders them and handles user events.
- Safety routing runs before ordinary service selection and at every stage. Model classification can supplement deterministic detection; it cannot suppress an application-detected hazard.
- Emergency wording and contact details must be checked against authoritative South African/eThekwini sources before implementation. Do not reuse existing guidance unquestioningly or ask the model to generate medical, firefighting or electrical instructions.
- Distinguish marketplace urgent same-day requests from emergencies requiring emergency services. No response-time or rescue promises.
- One service per job remains the existing rule. Multiple unrelated problems need an agreed clarification/separate-booking behaviour, not invented multi-trade matching.
- Retain existing configurable message, rate and budget limits. Keep corrections idempotent and apply server validation before making them durable.
- Gemini is the founder's selected provider. Reconcile the implementation in `sortd-gemini` (`deploy/combined`, decision 049) into the eventual feature baseline; do not overwrite unrelated work in `feat/018-final-amount`.

## Data changes
Prefer extending the validated session/draft conversation payload with intent, current question, safety pause and extracted facts; no new permanent transcript table is proposed. Confirm the minimal state shape during implementation planning. Apply the existing retention limits and document any actual schema changes in the data model.

## Security and privacy
Customer ownership and draft policies still apply to every action. Guests access only their own session. Street addresses, contacts, names, account IDs and payment data never enter model context. Emergency contacts are trusted application content, not customer contacts or generated model output.

Retain structured, validated output; delimited untrusted input; escaping; redaction; configurable rate/budget limits; and usage metadata without raw customer text. No autonomous posting, external messaging, payments or job-status transitions. Gemini processing and cross-border/privacy requirements follow decision 049 and the POPIA checklist; verify those requirements before live customer use.

## Out of scope
- Broader redesign of unrelated account, pro and admin pages. Homepage styling of Siya was initially deferred and is now authorised by the founder’s follow-up below.
- Emergency dispatch, contacting services for the customer, medical advice, repair instructions or guaranteed diagnosis.
- Invented prices, availability or new services; changes to payments, matching or quoting.
- Image understanding, voice, WhatsApp assistant, multilingual expansion or unrestricted general-purpose chat.
- New permanent conversation storage or admin transcript access.

## Open questions
1. **Decided 2026-10-06:** Siya also answers Get Sorted questions using approved product content.
2. **Decided 2026-10-06:** identify services quietly and allow correction in review, as a trial. This supersedes spec 017 AC6 for this trial.
3. **Decided 2026-10-06:** pause booking until the customer explicitly chooses to discuss a later repair. Acknowledgment does not establish that the situation is safe.
4. **Decided 2026-10-06:** ask which unrelated problem to book first and explain that each needs its own job.
5. Which further failed conversations should form the acceptance examples? The supplied fire-brigade screenshot is the first required regression case; more examples can refine the evaluation set.
6. **Decided 2026-10-06:** inspiration only. The founder explicitly declined impersonation and wants to avoid legal trouble; no likeness, quotations or endorsement claims are included.

## Implementation plan for review
1. Establish an isolated `feat/019-siya-conversation-redesign` checkout based on current main, then reconcile the existing Gemini integration and its decision 049. Preserve unfinished spec 018 work and the homepage checkout. Confirm the installed package APIs and safe local test database before running application checks.
2. Extend the existing `ChatRequest` / `ChatReply` contracts with validated intent, a pending-question reference, proposed answer corrections and an allowed conversational next action. Update `SiyaAgent`, the existing provider adapter and `FakeScopingAssistant` together. Add an application-owned Get Sorted fact source based on approved docs; do not send private account data.
3. Add Assistant domain Actions/support for emergency routing and applying conversation proposals. Verify emergency contact details and review the stored guidance before using it. Preserve safety independently of provider output; keep model output advisory.
4. Refactor `ChatWithSiya` and `Booking\\Thread` so natural text remains meaningful at every stage. Separate conversation from booking progression; apply service selection in the background, retain relevant facts, support corrections and interruptions, and remove the redundant scoping-completion gate. Update the existing Blade view only where the changed interaction requires it; homepage styling follows later.
5. Extend `SiyaChatTest`, `BookingFlowTest` and provider-contract coverage with regression fixtures mapped to AC1–22. Start with the screenshot case, ambiguous hazards, multiple facts in one message, correction, product question during booking, quiet service selection, hostile output and provider failure. Fakes cover all automated tests; optional live evaluation uses synthetic conversations separately.
6. Run affected tests, Pint and `composer check`; update spec progress, architecture documentation and the character/provider decisions in the feature change. Review and prepare a PR. No deployment or merge is included in this approval.

No new dependencies or database tables are planned. Main risks are incorrect emergency classification, stale answers after service changes, and product answers drawn from outdated facts. Focused regression cases and server validation address these; emergency recovery and multiple-job handling need the decisions above before their dependent implementation.

## Delivery slices
Approve the workflow first, then plan focused changes: (1) safety routing and conversation contracts/state, (2) contextual scoping and corrections, (3) booking integration and evaluation fixtures. Each slice must remain reviewable and keep the existing booking path working. UI rebranding is a later spec.

## Progress
- 2026-10-06: founder requested a complete reinvention of Siya and the workflow, confirmed migration from AWS to Gemini, and deferred UI alignment with the homepage. Draft prepared; no application changes made.
- Inspection: this checkout is `feat/018-final-amount` with unrelated unfinished changes. Gemini support exists in the clean `sortd-gemini` checkout on `deploy/combined` with decision 049.
- Current defects identified: the emergency pattern omits fire/fire brigade; later-stage free text is always treated as notes; generated conversational replies can be discarded in favour of service cards; dialogue progression remains questionnaire-driven.
- At draft stage, founder decisions and approval were pending; subsequent approvals and implementation are recorded below.
- 2026-10-06: founder approved natural texting, answering Get Sorted questions, and quiet service identification as a trial; identified Siya Kolisi as the character reference. Updated direction and concrete implementation plan. Remaining product decisions are listed above; implementation has not started.

- 2026-10-06: founder approved the implementation plan and the proposed emergency recovery and separate-job behaviour; confirmed character inspiration only. Work is isolated in `/Users/andymichaels/Documents/getsorted/sortd-siya`, branch `feat/019-siya-conversation-redesign`, based on `origin/main` plus existing Gemini commit `030f88d` (cherry-picked as `0ae3e30`).
- Implemented typed conversation intents, catalogue question context, approved product facts, server-validated customer note excerpts, quiet service selection, contextual questions/corrections at later stages, emergency pause independent of the provider and retry without duplicate messages. Corrections update the same authorised draft and retain photos.
- Verification in progress: new regression tests initially failed, then passed for emergency handling and contextual chat. Expanded coverage caught duplicate homepage notes, now fixed; the five focused draft/privacy/homepage regressions passed (18 assertions). Full quality gate and independent reviews remain in progress. Live Gemini evaluation and deployment have not run.

- Independent code, security and spec reviews completed with no remaining material findings after regression fixes. Focused assistant/provider coverage passed: 76 tests, 312 assertions. Blade compilation and production Vite build passed. Live Gemini quality evaluation and founder preview remain outstanding.
- Full quality gate passed: `PGOPTIONS='-c timezone=UTC' COMPOSER_PROCESS_TIMEOUT=0 composer check` (701 tests, 3,037 assertions; formatter, static analysis and dependency audit passed). The local PostgreSQL session otherwise used a timezone that made existing phone OTP tests expire two hours early; the per-run UTC setting resolved all 34 phone tests without changing application code. The first full run also exceeded Composer’s default 300-second subprocess timeout; the completed run took 588 seconds for tests. No live provider request or deployment was made.

- Founder clarification 2026-10-06: the screenshot illustrated poor responses beyond supported trades generally, not a fire-brigade-specific feature. Added an explicit ordinary-conversation intent and broader response instructions; removed the visible wizard/progress header and unused progress mapping. Booking controls still appear only when needed. Homepage styling remains deferred.

- Follow-up verification passed: full `composer check` with the same local UTC/timeout settings (707 tests, 3,070 assertions; formatting, static analysis and audit clean), focused conversation/provider tests (69 tests, 290 assertions) and production asset build. Follow-up reviewed directly; live Gemini evaluation and deployment remain outstanding.

- Founder explicitly authorised commit and deployment. Deployed app revision `7901bfa` from `deploy/combined`, preserving the homepage redesign. The integration entry tests passed (19 tests, 73 assertions); asset/container build and deployment completed. Public `/` and `/book` returned HTTP 200; `/book` has the new AI header and no wizard progress bar. Live synthetic chat checks for a greeting, lawn mowing and Get Sorted information returned contextual replies with no selected service or retry failure. Lawn mowing was acknowledged as unavailable with a garden-service suggestion, without unrelated trade promotion. Broader multi-turn quality evaluation remains part of the trial.

## Emergency content sources
Reviewed 2026-10-06: [eThekwini Public Safety and Emergency Services](https://www.durban.gov.za/page/public-safety-emergency-services) lists 031 361 0000 and fire evacuation guidance; [South African Government emergency guidance](https://www.gov.za/news/media-statements/western-cape-weather-warning-23-jun-2015) lists 112 from a cellphone. These are application-owned contacts and guidance, never generated model output. Existing 080 131 3111 wording is removed from Siya's emergency response; it is an electricity fault line, not the fire response number.


## Homepage UI follow-up — approved direction, 2026-10-06

Founder requests that Siya match the deployed homepage very closely, using the original Refero reference (https://refero.design/). Reuse the homepage's exact charcoal palette, Geist/Caslon fonts, collapsible navigation, Phosphor icons, white controls, warm accents and panel geometry. The conversation remains continuous without wizard progress.

Plan: share the existing homepage sidebar, add a scoped chat stylesheet, redesign the header/conversation/composer, and apply the same dark presentation to every booking card and its embedded controls. Check desktop and 360–390px mobile rendering, navigation/sidebar behaviour, typing/loading and send controls; run booking regressions, asset build and quality gate. No changes to scoping, ownership, confirmation, provider contracts or privacy boundaries.

Acceptance: (1) matching shell and typography; (2) dark cards, readable customer/assistant distinction and safety/error contrast; (3) composer usable on small screens with no horizontal overflow; (4) booking controls, sign-in, photos, review/edit and explicit confirmation still work; (5) accessible labels, focus states, keyboard operation and reduced-motion support; (6) homepage retains its existing appearance.


### Text-first refinement — 6 October 2026 (local draft)

Founder requested natural conversation before booking controls, and explicitly paused full quality gates, commits and pushes for this iteration. Trade cards are opt-in; scoping answer buttons are hidden until requested, with automatic manual fallback when AI is unavailable or a turn fails. Required facts still validate against the catalogue. Complete facts alone do not start sign-in: Gemini proposes booking readiness only for a request to book, arrange a pro or get quotes. The validated home-problem intent can retain that request across turns; manual service selection starts the existing booking path. Explicit confirmation remains required to post.

Verification exclusions for this refinement: full `composer check`, complete test suite, static analysis, dependency audit, independent review and live Gemini evaluation are intentionally not repeated. The earlier UI gate (714 tests / 3,092 assertions) completed before this refinement and does not validate these new flow changes. Focused assistant tests, formatter and frontend build are checked separately. Account quota is not exposed to the agent, so an approaching-limit alert cannot be promised. The subsequent founder instruction “commit and deploy” authorises committing, pushing and deploying this draft; full quality gates remain paused.

Focused verification: 76 assistant/conversation/provider tests passed (316 assertions); Pint, Vite production build, Blade compilation and whitespace checks passed. The session-restore compatibility adjustment and final prompt wording were reviewed directly; live model evaluation remains skipped.

- 2026-10-06: founder authorised commit and deployment of the homepage UI and text-first refinement. Target verified as the existing usesorted.co.za preview environment. Deployment result is recorded after execution.

- Deployment completed 2026-10-06 from app commit `b55ad96` on `feat/019-siya-home-ui`. Existing preview deploy script completed asset/container builds, migrations/seeding and cache refresh. Public `/` and `/book` returned HTTP 200; `/book` serves the new chat shell/stylesheet and composer, with no forced trade-selection buttons on initial load. Full gates remain paused; live Gemini multi-turn quality evaluation was not run for this revision.


### Contact page UI continuation — 6 October 2026
Founder authorised updating Contact to the homepage UI, followed immediately by commit/deployment, without Playwright. Reuse the shared sidebar, homepage CSS tokens, fonts, mobile header, cards and buttons; retain the existing email, phone and customer/pro destinations. Styling only: no backend or schema changes. Full quality gates remain paused. Verify Blade compilation, asset build and deployed HTTP/content; no Playwright, full suite, static analysis, audit or independent review for this iteration.

Contact deployment completed from `1a059bd`: asset/container build and preview migration/seed/cache steps passed. Public `/contact`, `/` and `/book` returned HTTP 200; new contact shell, sidebar, email link and pro destination verified. Blade compilation and whitespace check passed. Playwright and full quality gates were not run, as requested.


### Admin login UI continuation — 6 October 2026
Founder requested updating `https://usesorted.co.za/admin/login`. Matched the Get Sorted homepage palette, type and wordmark using a panel-specific Filament theme and login render hook. Preserve password and mandatory MFA behavior. Do not use Playwright. Based on the immediately prior explicit commit/deploy instruction for site UI updates, deploy this styling after the asset build and verify the public login route. Full gates remain paused.


Admin login simplification, 6 October 2026: founder asked to remove the duplicate Filament “Get Sorted” logo and “Sign in” heading. The only brand heading is now a large orange “Get Sorted” wordmark in the existing display type. Password and MFA controls remain.

Admin MFA screen refinement: centered the authenticator prompt, six-digit input and submit action, with tighter card spacing. Styling deployed in `5ad2307`; public login returned HTTP 200 and deployed theme CSS includes the MFA layout rules. No Playwright or full quality gates run.
