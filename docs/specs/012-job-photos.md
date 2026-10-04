# Spec 012 · Job photos

Status: Done (awaiting merge) · Phase: 2 · Owner: founder

## Goal
Customers can add photos to a booking so a pro can understand the problem before quoting. Photos stay private and have location metadata removed.

## User stories
- As a customer, I want to add a few photos while booking, so I can show the problem clearly.
- As a customer, I want to review or remove a photo before posting, so I control what I share.
- As an admin, I want to see job photos alongside the answers, so I can understand a posted job.

## Acceptance criteria
1. The booking flow offers an optional photo step after notes and before property selection. A customer can continue without photos.
2. A customer can add up to 5 photos per job. Each uploaded file is at most 10 MB. JPEG, PNG, WebP and iPhone HEIC are allowed. The server rejects a sixth photo, an oversized file, or a file whose actual content is not an allowed image, with a clear message.
3. A customer can preview and remove photos from their own draft, and the review screen shows the remaining photos before posting.
4. Photos on a draft remain attached when the customer resumes it. Removing a draft removes its photos. A guest must sign in before uploading, and only the job owner can add, view, or remove photos.
5. Every accepted image is decoded and re-encoded into a safe display format before storage. The stored image has no EXIF location data or other source metadata. An image that cannot be decoded safely is rejected.
6. Original uploads and processed photos are never publicly accessible. Stored photos use the private `media` disk; access is through short-lived signed URLs with job authorization. Expired, unsigned, and cross-customer requests fail.
7. The customer's job page and admin job view show the processed photos. Admin views continue to hide the customer's street address and phone number.
8. Posting a job preserves its photos. Upload, removal, and posting failures do not create duplicate photos or leave a partially attached image.

## Screens / UX
- Mobile first: an optional “Add photos” step with the current count (0–5), previews, remove controls, and a clear 10 MB per photo hint.
- Show an upload progress or busy state, then either a preview or a specific validation error. The customer can retry without losing prior photos or booking answers.
- Show photos on the review and customer job pages. Admins see them on the job detail page.

## Rules and edge cases
- The server enforces count, size, content type, ownership, and authorization; browser hints are supplementary.
- Count existing photos and uploads in progress against the five-photo limit. Repeated submissions must not attach the same upload twice.
- Process images before they become viewable. Do not retain the original uploaded bytes after successful processing.
- HEIC decoding requires Imagick with a working HEIC codec. The build plan must verify local and deployment support, convert HEIC to a standard display format, and make this a deployment prerequisite; uploads must fail clearly if the codec is unavailable.
- Photos do not change job status; all status changes remain in `ServiceJobStateMachine`.

## Data changes
- Use the existing Spatie `media` table and private `media` disk. Attach job photos to `ServiceJob` in a dedicated collection, with ordering for display. No new job status or migration is expected; confirm this in the build plan.
- Update `docs/architecture/data-model.md` with the collection and retention behavior in the implementation PR.

## Security and privacy
- Follow `docs/security/security-baseline.md` §3: allow-list actual image types, re-encode, strip EXIF location, private storage, short-lived signed URLs.
- Customer access is limited to their own jobs. Admin access follows the admin job policy and its own login and MFA requirements. Later pro access must follow the job visibility rules in `docs/product/matching.md`.
- Do not include addresses, phone numbers, names, or original image metadata in URLs or logs.

## Out of scope
- Photos for reviews, disputes, pro applications, and profile pictures.
- Image editing, captions, AI image analysis, and pro-facing photo screens (spec 009).

## Decision
- 2026-10-04: Accept iPhone HEIC alongside JPEG, PNG and WebP. Customers should be able to upload directly from their phones. Convert all accepted images to a safe standard display format and remove metadata. Verify Imagick and the HEIC codec on the eventual host before deployment.

## Progress
- 2026-10-04: Drafted after spec 005 merged, per founder decision 3 in that spec. HEIC decision recorded at the founder's request.
- 2026-10-04: Founder approved the build plan. Implemented the optional photo step, private processed media, signed and authorized image access, customer/admin display, and upload/removal tests. Local PHP has GD but no Imagick/HEIC codec, so a real HEIC upload cannot be exercised here; hosting must supply both before deployment. Review findings on removal races, repeat uploads and navigation during upload were fixed. `COMPOSER_PROCESS_TIMEOUT=0 composer check` passed (316 tests, 1,392 assertions); `npm audit --audit-level=high` found 0 vulnerabilities; assets built and the photo step was checked at 360 px. Awaiting PR review and founder merge approval.
