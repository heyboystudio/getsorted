# Operator agreements (POPIA sections 20 and 21)

Every provider that handles personal information for GetSorted must be bound by a written agreement: use only on our instructions, keep it confidential and secure, tell us about a breach. Most providers publish a standard data processing addendum (DPA) and the account holder accepts it. **Only the account holder can accept these, and they bind the company, so Claude cannot sign them.** Status as of 2026-10-08: none confirmed signed.

| Provider | What it handles | Where | How the DPA is entered into (checked 2026-10-08) | Status |
|---|---|---|---|---|
| **Resend** (email) | Name, email address, the text of notices | United States | The DPA (resend.com/legal/dpa) is binding automatically when you accept the Terms of Service; transfers rely on EU standard contractual clauses and the EU-US Data Privacy Framework. Download a copy for the file. | Account exists; keep a copy |
| **Amazon Web Services** (hosting, storage, later RDS and S3) | Everything the app stores | Cape Town (af-south-1) | AWS's data processing terms form part of the customer agreement; confirm and download from **AWS Artifact** in the console. | To confirm in Artifact |
| **Google** (Gemini, Places address lookup, sign-in) | Job descriptions (phone numbers and emails stripped), addresses typed, Google account name and email | International | Gemini API: the DPA for products where Google is a processor applies to **paid** use (billing on). Free-tier use may be used to improve Google products. **Make sure the Gemini key belongs to a project with billing enabled.** Maps Platform and sign-in: check the data processing terms in the Google Cloud console. | **Check that the Gemini key is on a billing-enabled project** |
| **Cloudflare** (DNS, optional proxy) | Visitors' IP addresses, request data when proxied | International | The customer DPA (cloudflare.com/cloudflare-customer-dpa) must be agreed by the customer: check the dashboard (Account, Configurations, Legal or Agreements) and sign if offered. | To do |
| **PayFast** (payments from pros) | Pro's name, email, payment amount; PayFast sees card data, we never do | South Africa | The merchant agreement accepted when the account was opened. PayFast is also a responsible party for the payment data it holds. Keep a copy. | Account open; keep a copy |
| **Browser push services** (Google, Apple, Mozilla) | An anonymous device address and the notice text | International | No agreement: the user's own browser contacts them when notifications are switched on. Covered in the privacy notice. | Nothing to sign |

## Steps for the founder
1. Sign in to each account above and accept or download the DPA as described. Save a PDF of each, with the date, in the company records folder.
2. Gemini: open the Google Cloud project behind the Gemini key and confirm billing is enabled. If the key is a free AI Studio key, move to a billing-enabled project and replace `GEMINI_API_KEY` in the server environment (ask Claude to do the swap).
3. When the company is registered, repeat the acceptance in the company's name (the accounts may currently be in the founder's own name).
4. Tell Claude when each is done so this table and the privacy notice stay true.

## Why this matters for the privacy notice
The notice says providers process information "under written terms that require them to keep it confidential and secure". That is only true once the table above is complete.
