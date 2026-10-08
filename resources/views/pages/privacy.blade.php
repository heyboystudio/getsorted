<x-layouts.app :title="__('Privacy notice')"><div class="getsorted-site">@include('pages.partials.header')
<main>
<section class="page-hero legal-hero">
<div class="site-container">
<p class="section-kicker">LEGAL INFORMATION</p>
<h1>Privacy notice.</h1>
<p>Version {{ config('getsorted.legal.privacy_version') }}. This notice explains how we handle personal information under POPIA.</p>
</div>
</section>
<section class="section">
<div class="site-container legal-grid">
<nav class="legal-toc" aria-label="On this page">
<a href="#who">Who we are</a>
<a href="#collect">What we collect</a>
<a href="#why">Why we use it</a>
<a href="#share">Who receives your information</a>
<a href="#abroad">Sending information outside South Africa</a>
<a href="#keep">How long we keep it</a>
<a href="#security">How we protect it</a>
<a href="#rights">Your rights</a>
<a href="#cookies">Cookies and tracking</a>
<a href="#children">Children</a>
<a href="#changes">Changes and contact</a>
</nav>
<article class="legal-content">
<section id="who">
<h2>Who we are</h2>
<p>The responsible party for your personal information is <strong>{{  config('getsorted.legal.company_name')  }}</strong> (registration {{  config('getsorted.legal.company_registration')  }}), {{  config('getsorted.legal.physical_address')  }}. This notice explains how we handle it under the Protection of Personal Information Act 4 of 2013 (POPIA). It covers clients who book jobs, pros who apply and work through GetSorted, and visitors to usesorted.co.za.</p>
<p>Our <strong>Information Officer</strong> is {{  config('getsorted.legal.information_officer')  }}. Contact: info@usesorted.co.za, 031 007 0622. We are registering our Information Officer with the Information Regulator.</p>
</section>
<section id="collect">
<h2>What we collect</h2>
<ul><li><strong>Everyone:</strong> name, email address, South African mobile number (as you type it: we do not verify it by code), password (stored scrambled, never readable), and what you do on the site, including the device, browser and IP address, which we log for security.</li><li><strong>Clients:</strong> the job description you give Siya and the job summary; the address and a map point for the property; photos you upload; chat messages with pros; estimates you receive; the pro you choose; your reviews; your notification choices.</li><li><strong>Pros:</strong> business details and trades; ID document, proof of address and profile photo; trade registrations and their numbers (stored encrypted); the names and phone numbers of references you give us; your work area; the estimates you send, your chats, your credit balance and payments to us through PayFast; reviews you receive.</li><li><strong>From other sources:</strong> if you sign in with Google we receive your name, email address and Google account identifier. When you add an address, Google Places helps complete it. For card or EFT payments, PayFast tells us whether a payment succeeded; we never see card details.</li></ul>
<p>We do not collect more than we need. We do not ask clients for ID numbers. We do not currently run criminal-record checks, and we do not collect special personal information (such as health or race), so please do not put it in a job description or chat.</p>
</section>
<section id="why">
<h2>Why we use it</h2>
<ul><li><strong>To provide the service (contract):</strong> create accounts, match your job to nearby pros, show estimates, run chat, introduce you to the pro you choose, finish or cancel the booking, handle reviews and complaints, and give pros their credit.</li><li><strong>To keep people safe and the platform honest (legitimate interest, legal obligation):</strong> vet pros, hide contact details until the introduction, prevent fraud and abuse, keep security logs and respond to legal requests.</li><li><strong>To tell you what is happening:</strong> notices in the app, by email and (if you allow it) browser push notifications about estimates, messages, bookings and your account. Notices are our only way of reaching you because <strong>we do not send SMS or WhatsApp messages</strong>.</li><li><strong>To run and improve the business:</strong> counts of jobs, estimates and response times, without using your name. We do <strong>not</strong> sell personal information. We do not send marketing unless you opt in separately.</li></ul>
<p>Siya, our AI assistant, helps write the job description. We send it only the trade, your description and your answers (phone numbers and emails are stripped first), never your name, address or account details. Siya's suggestions do not decide anything about you on their own: matching uses the trade and distance, and you confirm the job before it is sent.</p>
</section>
<section id="share">
<h2>Who receives your information</h2>
<p><strong>Pros.</strong> Before you choose a pro, pros see your job (trade, description, photos, suburb, approximate distance, when you want it done). They do not see your name, phone number, email or street address. When you choose a pro, we give <strong>that pro only</strong> your name, mobile number and street address. That pro then becomes responsible for how they use it, and our Pro agreement requires them to use it only for your job and to keep it safe.</p>
<p><strong>Our service providers (operators).</strong> They process information for us under written terms that require them to keep it confidential and secure and to use it only for us:</p>
<ul><li><strong>Amazon Web Services:</strong> hosting and storage. Our test site is hosted in Cape Town, South Africa. We plan to host the live service in South Africa.</li><li><strong>Resend:</strong> sends our emails (United States).</li><li><strong>Google:</strong> Google sign-in, Google Places address lookup, and the Gemini AI model behind Siya (international data centres).</li><li><strong>PayFast (Network International):</strong> takes payments from pros for credit (South Africa). Clients' job payments to pros do not go through us.</li><li><strong>Cloudflare:</strong> domain name service (DNS) and, when switched on, traffic protection for our website (international).</li><li><strong>Browser push services</strong> run by Google, Apple or Mozilla deliver push notifications to a device you have turned them on for.</li></ul>
<p><strong>Other disclosures.</strong> We may share information with the police, courts, the Information Regulator or other authorities where the law requires it, or to protect someone's safety, and with professional advisers under confidentiality. If the business is sold or merged, information may move to the new owner on the same terms as this notice.</p>
</section>
<section id="abroad">
<h2>Sending information outside South Africa</h2>
<p>Some of the providers above process information outside South Africa (for example Resend, Google and Cloudflare). We transfer information only where POPIA section 72 allows: the recipient is bound by law or by a contract that gives a level of protection substantially similar to POPIA, or the transfer is needed to perform a contract with you or for your benefit, or you consent. By using the features that need these services (email, Google sign-in, address lookup, Siya), you understand the transfer. If you do not want a transfer, do not use that feature, or contact us.</p>
</section>
<section id="keep">
<h2>How long we keep it</h2>
<ul><li><strong>Account details:</strong> while your account is open, then deleted or anonymised after you close it, except records we must keep.</li><li><strong>Job photos, and everything written about a job</strong> (your description and notes, estimate notes, the map point of the address): deleted 24 months after the job ends. A job that never led to an introduction is then deleted completely.</li><li><strong>Introduction, credit and payment records, and the bare job record behind them</strong> (trade, suburb, dates and amounts): at least 5 years, because tax and accounting law requires it. Nothing in that record says what was wrong or who you are beyond your account.</li><li><strong>Chat messages and chat photos:</strong> 24 months after the job ends, then deleted.</li><li><strong>Reviews:</strong> stay on the pro's profile while the pro's account is open, shown with the reviewer's first name.</li><li><strong>What you tell Siya</strong> is kept only with your job and goes with it at 24 months.</li><li><strong>Waiting-list sign-ups:</strong> 12 months.</li><li><strong>Pro vetting documents</strong> (ID, proof of address, photo, registrations) and reference details: while the pro's application is open or the pro is active. For an application that is rejected or abandoned, they are deleted after 12 months.</li></ul>
<p>We may keep a record longer if we need it to deal with a complaint, a legal claim or a legal duty.</p>
</section>
<section id="security">
<h2>How we protect it</h2>
<p>We protect information with encryption in transit (HTTPS), encrypted storage of sensitive fields such as registration numbers and reference phone numbers, private file storage with short-lived links, access limited to staff who need it, login protection and rate limits, regular backups, and audit logs of staff actions. No system is perfectly secure. If there is a security compromise that affects your information, we will tell the Information Regulator and you as soon as reasonably possible, as POPIA requires.</p>
</section>
<section id="rights">
<h2>Your rights</h2>
<p>You may ask us to confirm what we hold about you and to give you a copy, to correct it, to delete it when we no longer need it, to stop using it for a purpose you object to, and to withdraw a consent you gave (this does not affect what we did before). Signed-in users can ask for a copy of their data or for deletion in account settings. You can also email info@usesorted.co.za. We may need to confirm who you are, and we may keep what the law requires us to keep.</p>
<p>If you are unhappy with how we handled a request or your information, please tell us first. You can also complain to the Information Regulator of South Africa (inforegulator.org.za).</p>
</section>
<section id="cookies">
<h2>Cookies and tracking</h2>
<p>We use only the cookies the site needs: to keep you signed in, protect forms from forgery, remember your session and choices, and balance traffic. We do not use advertising or analytics cookies and we do not build advertising profiles. If that changes, we will ask for your consent first.</p>
</section>
<section id="children">
<h2>Children</h2>
<p>GetSorted is for adults. You must be 18 or older to have an account. We do not knowingly collect information about children.</p>
</section>
<section id="changes">
<h2>Changes and contact</h2>
<p>We will update this notice when our practices change and tell you about important changes. Each version has a number. Questions or requests: info@usesorted.co.za or 031 007 0622.</p>
</section>
</article>
</div>
</section>
</main>@include('pages.partials.footer')</div>
</x-layouts.app>
