# Spec 025 · Reviews

Status: Approved (founder said "proceed", 2026-10-08) · Phase: L · Owner: founder

## Goal
After a job is done, the client rates the pro. Ratings give the next household a reason to choose one pro over another now that business names are hidden until the introduction (spec 023).

## Acceptance criteria
1. The client of a **done** job can leave one review: 1 to 5 stars and an optional comment (up to 1 000 characters). One review per job.
2. Only the job's client, only once the job is `completed`, and only within 60 days of it being marked done.
3. Phone numbers, email addresses and bank details in the comment are masked, the same as in estimates.
4. The pro is told (in-app, push, email): the stars only, with a link to the job. The comment is not in the notice.
5. The reviewed pro can reply once (3 to 500 characters, masked). The client is told. A reply cannot be edited or removed.
6. A hidden review cannot be replied to.
7. Reviews show on the pro's profile page for a client comparing estimates (latest 10: stars, first name of the reviewer, month, comment, reply).
8. A pro with no visible review shows "New on GetSorted"; otherwise "★ 4.5 (2 reviews)" on the estimate card and profile. Nothing is shown that is not backed by a review.
9. Support and super admins see all reviews under Pros → Reviews and can hide one with a reason, or show it again. A hidden review is kept, with who hid it and why, and no longer counts or shows.
10. The client sees their own review (and any reply) on their job page; the pro sees it on theirs.

## Rules and edge cases
- Reviews are never edited or deleted by the client in the MVP (admins can hide).
- The reviewer's first name is shown; surname never.
- Only the pro whose estimate was chosen for that job is reviewed.

## Data changes
New table `reviews` (see data-model.md).

## Security and privacy
Reviews are public to clients who have an estimate from that pro. Contact details are masked on write. Admin actions are logged (`review_hidden`, `review_unhidden`), as are `review_submitted` and `review_replied`.

## Out of scope
Photos in reviews, review requests by email beyond the "job done" notice, the pro reporting a review (they ask support), average ratings in matching order.

## Progress
Built 2026-10-08.
