# Moving to a production host

Status: plan, 2026-10-08. Decision 014 chose "a South African provider"; the test site (decision 046) has run on AWS in Cape Town since 2026-10-05. This plan says what to decide, what to build, and how to cut over with a way back. Nothing here has been done yet.

## Why this matters now
- The AWS free credit behind the test server lasts to about **4 November 2026** (decision 046). The test box cannot be the live site: it has one server, one database container, no managed backups and an old Postgres volume that was copied from Stockholm.
- Real clients' names, phone numbers and street addresses will be stored, so POPIA applies in full (see `docs/security/legal-drafting-notes.md`).

## What production needs (from `tech-stack.md` and decision 014)
1. A South African region for latency and POPIA simplicity.
2. PostgreSQL with PostGIS, daily backups and **point-in-time recovery**, encrypted at rest.
3. Separate processes for web, queue worker and scheduler, restartable without downtime.
4. TLS, a staging environment separate from production, secrets outside the repo.
5. Monitoring and error tracking, and a tested restore.

## Options compared
| Option | What it is | For | Against |
|---|---|---|---|
| **A. Stay on AWS Cape Town (af-south-1): EC2 + RDS PostgreSQL** | The same Docker image on EC2, database moved to RDS | We already run there, same `deploy.sh` and image; RDS gives PostGIS, backups and point-in-time recovery; Cape Town is about 35 ms from Durban (decision 046); data stays in South Africa; Bedrock is in the region if we ever return to it | Hyperscaler prices in Cape Town are higher than Europe and a local VPS; costs grow with traffic; AWS billing is in dollars |
| **B. South African VPS (Johannesburg or Cape Town)** | A rented server from a local provider, often R500 to R2 500 a month | Cheapest; rand billing; local support; data in South Africa | We run Postgres ourselves (backups, PITR with pgBackRest, upgrades), no managed database; providers vary, and each needs checking for PostGIS support and snapshots |
| **C. Microsoft Azure South Africa North (Johannesburg)** | Managed Postgres with PostGIS in a South African region | Managed database, local region | A new cloud for us; extra deploy work; similar cost to A |

**Recommendation: A for launch**, because it is the least new work and the lowest risk: same cloud, same region, same deploy script, with RDS replacing the self-run database container. Revisit B once real traffic shows what we need, because the app is container-based and can move. The founder's decision 014 said "a South African provider", and AWS Cape Town **is** hosted in South Africa, but it is a hyperscaler, not a local company: **the founder should confirm A satisfies decision 014** before we build it.

Prices in rand are not in this document on purpose. Take current figures from the AWS Pricing Calculator for af-south-1 (EC2 and RDS) and from two local providers' sites before signing anything; third-party price lists found during research were not reliable enough to budget from.

## Target layout (option A)
- **Production account or at least a separate VPC and IAM role** from the test site. Never share a database or `.env`.
- **EC2** (start at a t3.small or t3.medium, resize on evidence) running the existing Docker image: web (Caddy, TLS), queue worker, scheduler. An Elastic IP and a security group allowing only 80, 443 and SSH from known addresses.
- **RDS PostgreSQL 17 with PostGIS**, Single-AZ at launch (Multi-AZ later), automated backups with a 14-day window, storage encryption on, deletion protection on, not publicly accessible; only the app's security group can connect.
- **S3 bucket (af-south-1)** for media instead of the container volume, versioned and private, with lifecycle rules. Needs a small config change and `league/flysystem-aws-s3-v3` (a dependency: needs a decisions-log entry when added).
- **Secrets** in AWS Systems Manager Parameter Store or Secrets Manager, copied into the server's `.env` on deploy; never in git.
- **Domain:** `usesorted.co.za` stays; admin on its own host (`GETSORTED_ADMIN_DOMAIN`); `GETSORTED_BEHIND_CLOUDFLARE` only if we put Cloudflare's proxy in front.
- **Email:** Resend with a verified domain (SPF, DKIM, DMARC), set up by the founder.
- **PayFast:** live merchant already configured; set `PAYFAST_SANDBOX=false` and check the notify URL is the production domain.
- **Environment mode:** production (`APP_ENV=production`), not `preview`: no fake integrations, no login codes on screen, real providers only (the app refuses silently falling back to fakes in production).

## Steps
1. **Decide** option A or B, and who owns the cloud account (the company, once registered; open question Q11).
2. **Build staging first**, identical to production, and deploy `main` to it. Run the full click-through (`npm run e2e` against staging) and a backup-and-restore drill (`deploy/backup`).
3. **Prepare production** the same way, with real secrets, an **empty database** and the seeded catalogue (trades), and super-admin created with `php artisan getsorted:create-super-admin`. **Never restore a test dump into production.** Before go-live run `php artisan getsorted:check-for-test-data`; it fails if it finds test accounts, fake payments or any jobs.
4. **Fill the legal details** in the environment: company name, registration, address, Information Officer. Register the Information Officer with the Information Regulator. Have the lawyer sign off the three legal pages.
5. **Rehearse the cutover** on staging: freeze writes, take a backup, restore it to a fresh database, point the app at it, check, then roll back.
6. **Cut over** at a quiet hour: put the test site in maintenance mode, final backup of the test database (if it holds anything to keep: normally nothing, since the test data is fake), switch DNS to the production IP (lower the TTL to 300 seconds a day before), smoke-test, watch logs.
7. **Keep the test environment.** It stays as the development and staging site (founder, 2026-10-08), with fake data and its own keys. Only the test server's AWS credit end date (about 4 November 2026) needs a decision: move it to the company account or a smaller instance. Delete the old rename backups on the server (`~/backup-sortd-before-rename-*`, `~/env-before-rename-*`) once nothing needs them.

## Rollback
Keep the test server running and its DNS record ready for the first week. If production fails the smoke test, point DNS back (the 300-second TTL makes that take minutes). Production data created in the meantime would be lost on rollback, so only roll back before real jobs are posted; after that, fix forward from the last backup.

## Backups and restore
`deploy/backup/` holds the scripts used on the test server (see its README): nightly compressed dump, 14 daily and 8 weekly copies, restore into a scratch database and a row-count check, run weekly. For RDS use automated backups plus a manual snapshot before every release, and restore a snapshot to a new instance once before launch to prove it works.

## Monitoring (small, before launch)
- Uptime check on `/up` from outside (a free monitor is enough), alerting the founder.
- Error tracking: Sentry (decision 015, EU region) was chosen but is not installed; the founder asked to skip it for now. Revisit before real customers.
- Disk, memory and database connection alarms in CloudWatch; a billing alarm.
- The admin dashboard's stalled-jobs list and success measures (spec 027) are the product health view.

## Cost control
- Set an AWS budget alarm at the expected monthly figure.
- No NAT gateway or load balancer at launch unless needed; they add a fixed monthly charge.
- Turn off or downsize staging when not in use.

## Decisions
1. **Decided 2026-10-08: option A, AWS Cape Town (decision 065).** The founder confirmed that it satisfies decision 014.
2. Which legal entity owns the account and pays.
3. Maintenance window and who is on call for the first week.
4. Whether to put Cloudflare's proxy in front (free tier gives DDoS protection and a firewall).
