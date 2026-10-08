# Spec 024 · Finish a job without payments

Status: Approved (founder said "next steps" and to proceed, 2026-10-08) · Phase: L · Owner: founder

## Goal
Every booked job can end: done, or cancelled with a reason. Nothing waits for a deposit that GetSorted never collects (decision 058). Today a booked job just sits there.

## User stories
- As a client, I mark the job done when the work is finished, or cancel the booking if plans change.
- As a pro, I do the same for jobs I was chosen for, and cancel only with a reason the client sees.
- As the founder, I know no job is stuck, and I can see who cancelled and why.

## Acceptance criteria
1. The client or the chosen pro can mark a booked job done. It moves to `completed`; who did it and when is recorded.
2. Anyone else (another client, another pro, a pro who was not chosen) is refused. A job that is not booked, or already finished, is refused.
3. The other side is told (in-app, push; email when the pro marks it done). A job marked done by the client says only that the work is done.
4. Either side can cancel a booked job with a reason of 3 to 300 characters. The job moves to `cancelled`; who cancelled and why is recorded.
5. The other side is told. If the pro cancels, the client is told the reason and given a link to post the job again.
6. Introduction fees are not refunded automatically; support decides case by case (credit adjustment).
7. Accepting an estimate always books the job (`scheduled`). The deposit field is gone from the estimate form. A deposit can still be agreed in the estimate notes and is paid directly. Jobs left in `awaiting_deposit` become `scheduled` by migration.
8. Once a day at 08:00, a booked job whose start date was 3 or more days ago gets one "Is the job done?" notice to both sides (email too). No job is closed automatically.
9. The client's job page shows "Mark as done" and "Cancel this booking" while the job is booked, "This job is done" afterwards, and the timeline gains "The job was marked done" and "You cancelled this booking" / "Your pro cancelled this booking".
10. The pro's job page shows the same two actions on a job they were chosen for.
11. The client's five-step progress ends with "Done" (was "Paid").

## Rules and edge cases
- Done and cancelled go through `ServiceJobStateMachine`: `scheduled` and `in_progress` can reach `completed`; `in_progress` can also reach `cancelled`.
- Both actions run under the job lock, so two people tapping at once cannot both succeed.
- The existing "cancel before a pro is chosen" (`CancelJobByCustomer`) is unchanged.

## Data changes
`service_jobs.completed_at`, `completed_by` (customer/pro), `cancelled_by` (customer/pro), `finish_nudged_at`.

## Security and privacy
Only the job's client and the chosen pro can act (checked in the action, not just the screen). Notices carry the trade and the reason the other side typed; they never include contact details. Activity log: `job_done`, `booking_cancelled`.

## Out of scope
Reviews (spec 025), disputes, automatic refunds, a "work started" step, auto-closing old jobs (operations, spec 027).

## Progress
Built 2026-10-08.
