# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
- **Customers:** Durban (eThekwini) homeowners and tenants who need a plumber, electrician, painter or tiler and don't know who to trust. Mostly on a phone, often mid-problem (a leak, a dead circuit) or planning a job.
- **Pros:** vetted Durban tradespeople and small trade businesses who want local work without losing 20% to commission or waiting days to be paid. They use mobile web.
- **Admins:** Sortd staff (support, vetting, finance). Not a home page audience.

## Product Purpose
Turns a household's description of a problem into a clear, scoped job, invites vetted Durban pros in waves to collect up to three quotes, and runs quote, deposit, work, final payment and review in one place with WhatsApp updates. Success on the home page: a Durban customer starts a booking, or a pro starts signing up.

## Positioning
Local to Durban where national platforms have thin or no pro coverage. One thread from problem to paid job, with vetted pros, itemised quotes, and contact details held back until a quote is accepted.

## Operating Context
- Test site at usesorted.co.za (Cape Town server, fake data only, not indexed). Not yet production.
- Launch area: a short list of eThekwini suburbs; requests outside it join a waitlist. Four demo trades: plumbing, electrical, painting, tiling.
- Booking is a chat-style thread with "Siya", an AI booking assistant. AI never invents prices or posts a job by itself.
- Sign-in with email or Google, then a verified South African mobile number.

## Capabilities and Constraints
- Stack: Laravel 13, Livewire, Blade, Vite; the home page is the `Welcome` Livewire component at `/`.
- Free for customers and for pros to join. No prices, customer counts or reviews may be claimed that don't exist.
- Terms, privacy and pro agreement are drafts. Contact details on the site are placeholders.
- Photos on the site are generated, illustrative only, and must be labelled or avoided as proof.
- Mobile first, 360 px.

## Brand Commitments
- The product name on the home page is **Get Sorted** (founder, 2026-10-05; replaces "Sortd" on this page). Domain: usesorted.co.za.
- Redesign scope is the home page only. The founder asked to start fresh: existing copy, logo and photos are not binding.

## Open Decisions
- Whether the rest of the site (header, footer, other pages) moves to "Get Sorted" after the home page is approved.
- Real logo and real photography for Get Sorted.
