# Spec 018 · Chat with your pros, estimate quotes and an approved final amount

Status: Approved (founder, 2026-10-05) · Phase: 3–4 (builds on specs 009, 010, 012, 013, 017) · Owner: founder

## Goal
After a job is booked, the customer can **chat in Sortd with each pro who's looking at the job**. They can answer questions and send more photos, and pros can then send a better **estimate quote**. The customer accepts one estimate and pays the deposit on it (spec 013). Once the pro has seen the job, they can **raise or lower the final amount**, but the customer must **approve the change** before the final invoice is issued. This is the founder's flow from 2026-10-05 (decision 045 "Next"). It brings in-app chat into v1, which the PRD had parked.

## User stories
- As a customer, I want to answer a pro's questions and send more photos without giving out my number, so I get accurate estimates.
- As a pro, I want to ask the customer questions before I quote, so my estimate is close to the real cost.
- As a customer, I want to see and approve any change to the price before I'm asked to pay it.
- As a pro, I want to adjust the final amount when the job turns out bigger or smaller than estimated, with a reason the customer can see.
- As the founder, I want all of this to stay on Sortd: no phone numbers swapped before a quote is accepted, and a record of every price change.

## Acceptance criteria

**Conversations**
1. Each pair of job and invited pro has one conversation, and **either side can start it** (decision 1). The customer's job page lists every invited pro, with "Message" next to each. The pro's invite page has "Message the customer". The conversation is created with the first message. Each one shows the pro's business name, rating (when reviews exist) and quote status. Pros whose invite was declined or expired can't be messaged.
2. Customer and pro can send text (1–1,000 characters) and **photos** (up to 5 per message, same rules and processing as spec 012). New messages appear in about 5 seconds without reloading (polling; open question 5). The newest message is in view, and unread counts show on the job page and the pro's job list.
3. **Before a quote is accepted**, every message passes through the existing scrubber (`Redactor`, spec 007 AC10): phone numbers, emails, links, ID numbers and street addresses become "[hidden until you book]". A banner reads: "Keep chats and payments on Sortd. Contact details are shared once you accept a quote." Photos go through the same metadata stripping as spec 012 (no GPS).
4. Notifications send a WhatsApp/SMS **template**, "You have a new message about your {service} job", with a link. The message text is never included. There's at most one notification per conversation every 15 minutes (setting), and none while the person has the page open.
5. When the customer accepts a quote, the chosen pro's conversation **stays open** for the rest of the job, now unscrubbed because contact details are shared from this point (spec 010). The other conversations close **read-only**, with "The customer chose another pro". Cancelling or expiring a job closes all of them.
6. Either side can **report** a message (reason list). Reported conversations show up for admins.

**Estimate quotes**
7. Quotes from spec 010 are shown to customers as **"Estimate"**, with the line "Your pro can adjust the final amount after seeing the job. You'll approve any change." A pro can send or revise their estimate from inside the conversation (it opens the spec 010 quote builder), and the new version appears in the chat as a card.
8. Accepting an estimate and paying its deposit work exactly as in specs 010 and 013. The deposit is based on the accepted estimate and never changes later.

**Changing the final amount**
9. When the job is `scheduled` or `in_progress`, the accepted pro can **propose a final amount**:
   - the estimate's lines prefilled, so they can edit, add or remove lines;
   - a required **reason** (10–500 characters);
   - optional photos.

   The server recalculates every total (integer cents, as spec 010). The proposal shows in the chat as a card: "Estimate R X → Proposed R Y (+R Z / −R Z)", with the reason, the changed lines and **Approve** / **Decline**.
10. Only one proposal can be pending at a time. The pro can withdraw it while it's pending, and a new proposal replaces the old one.
11. **Approve**, by the customer: the proposal becomes the **agreed final amount**. It's recorded with a timestamp in the job timeline (event `final_amount_approved`) and the pro is told. The final invoice (Phase 4, later spec) is for the agreed amount minus the deposit already paid. If the deposit is more than the agreed amount, the difference is refunded (refund spec).
12. **Decline**, by the customer, with an optional note: the agreed amount stays the estimate total, and the pro is told. The pro can then do the job at the estimate, send **one** more proposal (decision 3), or **cancel with the reason "price not agreed"**, with the customer's deposit **refunded in full** (decision 4; the refund itself is built in the refunds spec). Admins can see declined proposals.
13. A proposal **lower** than the agreed amount **applies straight away** (status `applied`) and the customer is told. No approval is needed (decision 2).
14. With no proposal, the agreed final amount is the accepted estimate's total. The pro can't issue a final invoice above the agreed amount; the server enforces this.

**Admin**
15. Admin → Jobs shows conversations **read-only**, behind MFA, with every view recorded in the access log. It also shows reported messages, final-amount proposals with their decisions, and the agreed amount. Admins can close a conversation (abuse) but can't edit messages.

## Screens / UX
- **Customer job page:** a tab or section per pro, "Chat with {pro} · Estimate R X". It uses the same bubble style as the Siya thread (spec 017), with photo and estimate cards inline, and a sticky input with a photo button.
- **Pro job page:** the same conversation with "Send estimate" / "Revise estimate". After acceptance there's also a "Propose final amount" button.
- **Proposal card:** old and new totals, the difference coloured (red up, green down), changed lines highlighted, the reason, photos, and Approve / Decline buttons (customer only).
- 360 px first. Empty state: "No messages yet. {Pro} may ask you a question before quoting."

## Rules and edge cases
- Conversations belong to a job and a pro. Only that job's customer and that pro (plus admins, read-only) can open them, and URLs use `public_id`.
- Scrubbing happens when the message is saved. The original text is never stored before acceptance.
- Messages and photos can't be edited. A sender can delete their own message within 5 minutes; it shows as "Message deleted".
- Rate limits: 30 messages per hour per sender per conversation, and 20 photos per day.
- A proposal on a `disputed` or `cancelled` job is refused.
- The money rules in `docs/product/money-flow.md` apply. The proposal-and-approval history is append-only.

## Data changes
- `job_conversations`: public_id, service_job_id, pro_id, status (`open`, `closed`, `read_only`), opened_at, closed_at, closed_reason, last_message_at; unique (service_job_id, pro_id).
- `job_messages`: public_id, conversation_id, sender_type (`customer`, `pro`, `system`), sender_id, body (scrubbed), kind (`text`, `photos`, `quote_card`, `proposal_card`), reference id, read_at, deleted_at, reported_at, report_reason; photos through media library (`message_photos`).
- `final_amount_proposals`: public_id, service_job_id, quote_id, version, lines json, labour/materials/callout/vat/total cents, reason, status (`pending`, `approved`, `declined`, `withdrawn`, `applied`), decided_at, decided_by, customer_note.
- `service_jobs`: add `agreed_final_cents`, set when a quote is accepted (its total) and changed only by an approved or applied proposal.
- Settings: `chat.notify_every_minutes` (15), `chat.messages_per_hour` (30).
- Update `data-model.md`, `job-lifecycle.md` (events `final_amount_proposed/approved/declined`) and `money-flow.md` (the agreed final amount).

## Security and privacy
- **Policies:** conversation view and send are limited to the job's customer and that pro, and only while the conversation is open. Proposals can only be made by the accepted pro and decided by the customer.
- **POPIA:** messages are personal data. Retention is 24 months after the job closes, then deleted (open question 6). Messages are included in "download my data" and removed on account deletion, keeping the other party's copy of their own messages only.
- Contact details stay scrubbed until acceptance (domain rule: pros see no contact details before their quote is accepted).
- Admin access is logged, as in spec 016 AC15.

## Out of scope
- Real-time push (WebSockets): polling first, open question 5.
- Voice notes and video, and chat between pros.
- Final invoices and payment, refunds, and disputes. These are later Phase 4 specs; this spec only sets the agreed amount they will use.
- Siya in the pro chat.

## Decisions (founder, 2026-10-05)
1. **Either side** can start a conversation.
2. **Lower final amounts** apply straight away; the customer is told.
3. After a declined increase, the pro gets **one** more proposal.
4. If the price isn't agreed, the pro may **cancel and the deposit is refunded in full**.
5. **Polling every 5 seconds**; WebSockets (Reverb) can come later.
6. Chats are kept **24 months** after the job closes.

## Progress
- 2026-10-05: approved with the decisions above.
- 2026-10-05: drafted from the founder's description of the post-booking flow (decision 045). Spec 013 (deposit payments, draft PR #37) is still awaiting its own three decisions.
