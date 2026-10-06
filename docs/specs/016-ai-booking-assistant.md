# Spec 016 · AI booking assistant on the site (Claude on Amazon Bedrock)

Status: Done · Phase: 2–3 (builds on specs 005, 006, 007, 012, 015) · Owner: founder · **The separate chat page and the hand-off to the wizard are replaced by spec 017**

## Goal
A customer can open a chat on the website, describe the problem in their own words, and the assistant works out what's wrong. It asks the right follow-up questions, gives safety advice when needed, collects photos and the address, and hands over a ready-to-post job. From there the existing cycle runs unchanged: matching and invite waves (009), quotes and acceptance (010), and later payments, reviews and so on. It replaces the removed "Describe your problem" box. The website only, not WhatsApp.

## User stories
- As a customer, I want to explain my problem like I'd explain it to a person, and have Sortd figure out which pro I need.
- As a customer, I want to be told if something is dangerous, and what to do right now.
- As a customer, I want to check and correct the job before it's sent to pros.
- As the founder, I want the AI cost capped and its behaviour auditable, without reading customers' private text in logs.

## How it works (overview)
1. The customer taps **"Get help"** on the home page, a trade page or their account. A chat panel opens (full screen on mobile).
2. The customer describes the problem. The assistant (Claude Haiku 4.5 through Amazon Bedrock, EU region) replies conversationally. Behind the scenes it can only do a fixed set of **tools**:
   - `suggest_service(trade_key, service_key)`, only from our active catalogue;
   - `ask_question(question_key)`, only the scoping questions of that service (spec 005/007), shown with their normal answer buttons;
   - `record_answer(question_key, value)`, validated against the question's allowed answers;
   - `show_safety_advice()`, the service's stored safety advice (never AI-written safety advice);
   - `request_photos()`, which opens the existing photo upload (spec 012);
   - `request_address()`, which opens the address picker (spec 015) and runs the coverage check (spec 006);
   - `ready_for_review(summary)`.
3. When everything required is collected, the customer sees the **normal review step** (spec 005/007): service, answers, photos, property and the job description for pros. They edit and post it themselves. Posting, matching and everything after are unchanged.

## Acceptance criteria
**Conversation**
1. Given the assistant is enabled, when the customer opens "Get help" and sends a message (2–1,000 characters), then a reply streams into the chat within about 5 seconds, with a typing indicator meanwhile.
2. The assistant suggests a service only by calling `suggest_service` with keys that exist and are active. The customer sees "Sounds like **{service}** ({trade}). Is that right?" with Yes / Something else. Nothing proceeds until they confirm.
3. After confirmation, the assistant asks that service's scoping questions one at a time in its own words. The customer answers by button (the question's real options) or by typing, and typed answers are mapped with `record_answer`. Answers that don't validate are re-asked. Every required question must be answered, and the assistant cannot invent questions or answers.
4. Given the service has safety advice or is urgent/emergency-capable, then the stored safety advice is shown as a highlighted card, word for word, with "This is guidance, not a guarantee." For anything mentioning gas smell, sparking, smoke or flooding near electrics, the card says to switch off at the mains or call emergency services first.
5. The assistant asks for photos (optional) and the address. The address uses spec 015, and the coverage check runs. If not covered, the chat offers the waitlist (spec 006) and ends politely.
6. When `ready_for_review` is called with every required item present, the chat shows "Review your job" and opens the review step pre-filled: service, answers, photos, property and the AI summary (labelled "Written with AI help, please check it"). The customer edits and posts. The assistant never posts a job itself.
7. Off-topic, abusive or out-of-scope requests (quotes, prices, legal or medical advice, other companies) get a short refusal and a steer back to the job. The assistant never gives prices or price estimates, and never promises timing.
8. The customer can type "start over" or tap **Restart**, which clears the chat and the draft answers it made (photos already uploaded stay on the draft).
9. Guests can chat. Before photos or address, they're asked to sign in or sign up (spec 014), and the conversation continues after sign-in.

**Limits, cost and failure**
10. Max 30 customer messages per conversation, 10 conversations per visitor per day, and the existing daily call budget (spec 007 AC14, default 2,000 calls) shared with the other assistant uses. When it's exceeded, the chat says "Our assistant is busy, you can still book directly" and links to the normal booking flow.
11. Given Bedrock errors, times out (15 s) or returns an invalid tool call, then that turn shows "Sorry, something went wrong, try again" with a Retry button. After 2 failures in a row, the chat offers the normal booking flow with everything already collected kept on the draft.
12. With `ai.enabled` off or no provider configured, the "Get help" button goes straight to the normal booking flow.

**Privacy and safety**
13. Customer messages pass through the existing scrubber (spec 007 AC10) before going to the model: phone numbers, emails, URLs, ID and card numbers become placeholders. Names, addresses and account IDs from the database are never sent. The address is collected by the address picker, not typed into the chat.
14. Customer text is sent as delimited data. Tool calls are validated server-side against the catalogue and question schema, so instructions hidden in customer text can't trigger anything outside the tool list (spec 007 AC11). Model text is shown escaped and never rendered as HTML.
15. Conversations are stored with the draft job (scrubbed text only) so the customer can resume after a refresh or sign-in. They're deleted 30 days after the job is posted or abandoned. Admins see the conversation only from the job page, MFA-protected, with access logged.
16. Each model call writes an `ai_usage` row (purpose `chat`, provider `bedrock`, model, tokens, latency, outcome) with no customer text, as spec 007 AC13.

**Provider**
17. Integration: a `BedrockBookingAssistant` behind a new `BookingAssistant` contract, using the Laravel AI SDK Bedrock driver, model `eu.anthropic.claude-haiku-4-5-20251001-v1:0`, region `eu-north-1`. Credentials come from the server's IAM role (no keys in `.env`). The existing `ScopingAssistant` (service suggestion, summary) moves to Bedrock too, so there's one provider. A `FakeBookingAssistant` with scripted replies is used in local and testing.

## Screens / UX
- **Entry points:** home hero button "Get help with a job", trade pages, account home.
- **Chat panel:** message list, input with send button, chips for quick answers, a photo-upload card, an address card, a safety card, and a "Review your job" card. Restart in the header, plus a "Book without the assistant" link always visible.
- **States:** empty ("Tell us what's wrong, for example: my geyser is leaking"), typing indicator, streaming reply, error with retry, busy/over-budget, not covered → waitlist.
- Mobile first. Accessible: messages are announced by screen readers and every chip works by keyboard.

## Rules and edge cases
- One open conversation per draft. Reopening resumes it.
- If the customer changes the service mid-chat, answers for the old service are dropped (with a note).
- The review step and posting are the existing code. The assistant only fills in a draft.

## Data changes
- `assistant_conversations` (draft job id, user id nullable, visitor id, status, message count, timestamps).
- `assistant_messages` (conversation id, role, scrubbed text, tool name and arguments json, created_at). Pruned per AC15.
- Update `docs/architecture/data-model.md`. New decision record: "043 · Amazon Bedrock (EU) as the AI provider".

## Security and privacy
- **POPIA:** Bedrock processes in the EU (cross-region profile `eu.`), and AWS and Anthropic don't train on the data. The privacy notice must name AWS/Anthropic and the cross-border transfer before the live launch (spec 007 open question 1).
- **Access:** the server IAM role allows only `bedrock:InvokeModel*` on Anthropic models (created 2026-10-05).
- **Rate limits:** as in AC10. There's a $20/month AWS budget alert.

## Out of scope
- WhatsApp or SMS chat (spec 016 is website only).
- Voice or image understanding (the assistant doesn't look at photos).
- Price estimates, booking times, or the assistant talking to pros.

## Open questions
1. **The $100 AWS credit:** confirm after the first day of use that Bedrock charges come off the credit (Billing → Credits).

**Decided 2026-10-05:**
- The assistant is called **Siya**. **Updated 2026-10-06 (spec 019, decision 050):** the founder chose Siya Kolisi as character inspiration only. Siya remains Get Sorted’s AI assistant, with a warm, grounded voice; no impersonation, likeness, quotations, rugby branding or endorsement claims.
- Guests can chat before signing in (AC9).
- Build after spec 015.

## Progress
- 2026-10-05: drafted. Bedrock access tested from the preview server: Claude Haiku 4.5 (EU profile) replies. Server IAM role and $20 budget alert in place.
- Note for build: Docker containers need the EC2 metadata hop limit set to 2 to use the instance role (`aws ec2 modify-instance-metadata-options --http-put-response-hop-limit 2`), or the web container must run with host networking.
- 2026-10-05: built (first version). How it differs from the draft, deliberately:
  - **Structured replies instead of free tool calls:** each Siya turn returns JSON (reply, suggested trade/service keys, answers), which the server validates. Same safety, simpler and cheaper.
  - **Chat kept in the session, not the database:** scrubbed text only, gone when the session ends. No admin view of conversations yet (AC15's tables are not built). Simpler and more private.
  - **Address, photos, review and posting reuse the booking wizard:** "Continue booking" hands over the service, answers and the customer's own words; with spec 015's address search, the wizard checks coverage and jumps straight to the notes step.
  - **Limits:** 30 messages per chat, 60 messages per visitor per hour, plus the shared daily AI budget (no separate 10-chats-a-day cap).

