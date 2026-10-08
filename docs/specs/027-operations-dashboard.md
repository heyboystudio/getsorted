# Spec 027 · Operations: stalled jobs and success measures

Status: Approved (founder said "proceed", 2026-10-08) · Phase: L · Owner: founder

## Goal
At soft launch an admin can see at a glance which jobs are stuck and whether the product is meeting the PRD's targets, without querying the database.

## Acceptance criteria
1. The admin dashboard lists jobs that need a nudge, oldest first, each with trade, area, why and how long, linked to the job.
2. An **open** job with no estimate is stalled 4 hours after posting; an urgent one after 1 hour.
3. A **booked** job whose start date was 3 or more days ago and has not been marked done is stalled. Marked-done, cancelled and expired jobs are never listed.
4. The dashboard shows the PRD's measures for jobs posted in the last 90 days, each with its target and the number of jobs behind it: median time to first estimate (under 4 h), jobs with 2 or more estimates (over 60%), jobs with an estimate that chose a pro (over 35%), booked jobs marked done (over 70%), done jobs reviewed (over 40%).
5. A measure with nothing to measure shows "—", never zero or a guess. A measure that meets its target is green, one that misses is amber.
6. The old "Awaiting deposit" tile is replaced by "Stalled jobs" (deposits do not exist in the MVP, decision 058).
7. Thresholds are configuration (`getsorted.ops.*`), not code.

## Data changes
None. Everything is computed from jobs, estimates, introductions and reviews.

## Security and privacy
Admin panel only (support and super admins). Shows trade, suburb and times; no customer names or contact details.

## Out of scope
Charts over time, email alerts to admins, per-suburb or per-trade breakdowns, a "reassign" button.

## Progress
Built 2026-10-08.
