# POPIA and privacy checklist

> Not legal advice. Have a South African privacy lawyer review this list, the privacy notice and the pro agreement before launch.

## Organisational

- [ ] Register an **Information Officer** (and deputy) with the Information Regulator.
- [ ] Publish a **PAIA manual**.
- [ ] Privacy notice (customer and pro versions) explaining what is collected, why, who receives it (pros, payment provider, messaging provider, AI provider, hosting), retention, and rights.
- [ ] Written operator agreements with every processor (hosting, payments, AI model, email, error tracking).
- [ ] If any data is processed outside South Africa (e.g. AI model, error tracking, hosting region), document the lawful basis for cross-border transfer and tell users in the privacy notice.
- [ ] Breach procedure: notify the Information Regulator and affected people as soon as reasonably possible after discovering a security compromise; keep an incident log.

## Product requirements derived from POPIA

| Requirement | How GetSorted meets it |
|---|---|
| Minimality | Collect only what a booking or vetting needs. No ID numbers from customers. |
| Purpose specification | Each field has a stated purpose in the privacy notice. |
| Consent records | `consents` table stores type (terms, privacy, marketing, pro agreement), version, timestamp, IP and browser. |
| Direct marketing | Opt-in only (unticked box), separate from terms; one-tap unsubscribe in every marketing message. |
| Special personal information | V1 does not perform criminal-record checks (decision 034). Reassess consent, access and retention requirements before adding any such checks. |
| Data-subject access | "Download my data" in account settings (JSON + PDFs). |
| Correction and deletion | Users edit their profile; "Delete my account" anonymises personal data while keeping financial records required by law. |
| Retention | Financial records: 5 years (confirm with accountant). Job photos: 2 years after completion. AI raw text: 30 days. OTP records: 90 days. Waitlist: 12 months. Some prune jobs are in place; complete and verify the remaining retention schedules before launch. |
| Security safeguards | `security-baseline.md`. |
| Sharing with pros | Pros receive suburb-level location and job details; address and contact only after their quote is accepted. |
