# Legal drafts: how they were written and what a lawyer must check

Written 2026-10-08 for the terms (`/terms`), privacy notice (`/privacy`) and pro agreement (`/pros/agreement`). **Claude is not a lawyer.** These are complete working drafts based on public sources and on how comparable South African marketplaces do it. A South African attorney with POPIA and consumer-law experience must review them before the service goes live. The pages carry a "Draft for lawyer review" badge until then.

## Sources used
- **Kandua (kandua.com/terms-and-conditions), read in full on 2026-10-08.** The closest comparison: customers and pros contract directly, the platform is an intermediary and does not guarantee work, limited vetting with a verification badge, pros are POPIA operators for customer data, no sale of data, AFSA arbitration after good-faith negotiation, South African law, retention schedule (5 years customer and pro records, 7 years financial), breach notification to the Information Regulator, rights and complaint handling times.
- **SweepSouth** (help-centre placement terms and public statements): workers are independent contractors; the platform matches and does not employ; deactivation appeals.
- **Bark** (credit model, via reviews and guarantee pages): how pay-per-lead credits and refunds are explained, and the complaints they attract (credit expiry, refund discretion). We chose no expiry and published refund conditions to avoid that.
- **Snupit** (press releases): pay-per-lead for pros, free to join. Their own terms page could not be fetched.
- **Information Regulator's own privacy notice** (inforegulator.org.za/privacy-notice): the headings a POPIA notice should cover.
- **POPIA** sections 18 (notification at collection), 20 and 21 (operators), 72 (transfers outside South Africa), 55 (Information Officer) and Information Regulator guidance on registering Information Officers.
- **Electronic Communications and Transactions Act** section 43 (supplier details online) and sections 73 to 77 (intermediary liability and take-downs).
- **Consumer Protection Act** sections 48 (unfair terms), 49 (clauses that limit liability must be conspicuous) and 51 (gross negligence cannot be excluded).

Sources we could not read: Snupit and Bark South Africa terms pages (blocked), SweepSouth's main terms (blocked). Do not treat them as checked.

## Choices Claude made that the founder should confirm
- **Pro fee terms:** R99, first 10 free, 30 days' notice before a fee starts or changes, credit never expires, no VAT while not VAT-registered, credit returned when the introduction was our error or the client cancels before the visit for a reason not connected to the pro (ask within 14 days), unused credit refunded on leaving.
- **Liability:** a R10 000 cap for clients' claims and 12 months' fees for pros, each with the required carve-outs. The number is a placeholder.
- **Disputes:** South African law; for pros, AFSA arbitration after good-faith talks (as Kandua).
- **Retention:** chat 24 months, waiting list 12 months, rejected or abandoned vetting documents 12 months (all built); financial records at least 5 years (accountant to confirm).

## Things the lawyer must check or supply
1. Company name, registration number and street address (open question Q11). They are read from `GETSORTED_COMPANY_NAME`, `GETSORTED_COMPANY_REGISTRATION` and `GETSORTED_PHYSICAL_ADDRESS`, and the Information Officer from `GETSORTED_INFORMATION_OFFICER`. Fill them in on the server when the company exists.
2. Whether marketplace intermediary rules and the Consumer Protection Act treat GetSorted as a "supplier" to clients, and whether the clauses on liability, indemnity and the platform's role are enforceable as written.
3. Whether the arrangement with pros could be argued to be employment (the agreement and the product keep control with the pro, and no hours or exclusivity are imposed).
4. Cross-border transfers (Resend, Google, Cloudflare): that the contracts we have give "substantially similar" protection under section 72, or that consent wording is needed at sign-up.
5. Whether consent checkboxes at sign-up for the three documents are enough, and how to re-ask when a version changes (versions are recorded in `consents`).
6. Whether a PAIA manual is needed and its content.
7. The wording of the "we do not verify mobile numbers" and "we do not run criminal-record checks" statements.

## Work still to do in the product so the text stays true
- Register the Information Officer (and deputy) with the Information Regulator on the eServices portal; sign operator terms with Resend, Google, AWS, Cloudflare and PayFast (their standard data-processing addenda).
- Delete closed jobs and their photos 24 months after they end (chat and vetting pruning exist; job photos and job records are not pruned yet). Until then the privacy notice says job photos stay with the job record.
- A breach-response procedure and incident log (see `popia.md`).
- Add a clearly worded "Choose this pro" screen note linking to the terms (the confirmation already explains what is shared).

## Keeping the drafts and the product aligned
`tests/Feature/LegalPagesTest.php` checks that the pages still say the things the product does (providers named, mobile numbers not verified, no SMS or WhatsApp, fee terms, liability carve-outs, supplier details). If the product changes, change the pages and the test together.
