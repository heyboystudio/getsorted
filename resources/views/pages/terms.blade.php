<x-layouts.app :title="__('Terms of service')"><div class="getsorted-site">@include('pages.partials.header')
<main>
<section class="page-hero legal-hero">
<div class="site-container">
<p class="section-kicker">LEGAL INFORMATION</p>
<span class="reviewed-badge">Reviewed by a South African attorney on 8 October 2026</span>
<h1>Terms of service.</h1>
<p>Version {{ config('getsorted.legal.terms_version') }}. These terms cover clients who use GetSorted to find and book tradespeople.</p>
</div>
</section>
<section class="section">
<div class="site-container legal-grid">
<nav class="legal-toc" aria-label="On this page">
<a href="#about">About GetSorted and these terms</a>
<a href="#what-we-are">What GetSorted is, and is not</a>
<a href="#account">Your account and your details</a>
<a href="#posting">Posting a job and Siya, our AI assistant</a>
<a href="#estimates">Estimates and choosing a pro</a>
<a href="#money">Money</a>
<a href="#cancel">Changing your mind and cancelling</a>
<a href="#reviews">Reviews</a>
<a href="#rules">Rules of use</a>
<a href="#problems">Problems and complaints</a>
<a href="#liability">Our responsibility</a>
<a href="#privacy">Your personal information</a>
<a href="#law">Law and contact</a>
</nav>
<article class="legal-content">
<section id="about">
<h2>About GetSorted and these terms</h2>
<p>GetSorted is operated by <strong>{{  config('getsorted.legal.company_name')  }}</strong> (registration: {{  config('getsorted.legal.company_registration')  }}), {{  config('getsorted.legal.physical_address')  }}. You can reach us on info@usesorted.co.za or 031 007 0622 (Mon to Fri 08:00 to 17:00, Sat 08:00 to 13:00, closed Sundays and public holidays). We answer messages within 4 business hours (1 hour for urgent jobs) and complaints within 2 business days.</p>
<p>These terms are an agreement between you and us. By creating an account or posting a job you accept them. You must be 18 or older and able to enter a contract. If you do not agree, please do not use GetSorted.</p>
<p>We may change these terms. For changes that matter we will tell you by email or in the app before they apply, and you can close your account if you do not agree. Each version has a number, and we keep a record of the version you accepted.</p>
</section>
<section id="what-we-are">
<h2>What GetSorted is, and is not</h2>
<p>GetSorted is an online platform that introduces households to independent tradespeople ("pros") in Durban and surrounds. You describe the job, we invite suitable pros nearby, they send estimates, and you choose one to come and look at the job.</p>
<ul><li><strong>The work is a contract between you and the pro.</strong> GetSorted is not your contractor, the pro's employer or agent, and is not a party to that contract. The pro decides how to do the work and is responsible for it.</li><li><strong>We check, but we cannot guarantee.</strong> Before a pro receives jobs we review their identity document, proof of address, photo, trade registrations they claim (for example PIRB or an electrician's registration) and references. A "registration verified" badge means we saw a valid registration document on the date we checked and it has not since expired. We do not currently run criminal-record checks. We do not check the quality of any pro's work, and ratings and reviews are opinions of other clients.</li><li><strong>Take normal care.</strong> Ask for the pro's final quote before work starts, ask for a receipt, and ask for any compliance certificate the law requires (for example an electrical certificate of compliance).</li></ul>
</section>
<section id="account">
<h2>Your account and your details</h2>
<p>You can sign up with an email address and password, or with your Google account. We send you a link to confirm your email. We also ask for a South African mobile number so the pro you choose can reach you. <strong>We do not send you SMS or WhatsApp messages and we do not verify your mobile number with a code.</strong> Please enter a number you answer, because the pro will use it.</p>
<p>Keep your login private and your details accurate. You are responsible for what happens on your account. Tell us at once if you think someone else is using it. We may suspend an account that breaks these terms or that we reasonably think is being misused.</p>
<p>You can ask for a copy of your information or for your account to be deleted in your account settings (see the <a wire:navigate.hover href="{{ route('privacy') }}">Privacy notice</a>).</p>
</section>
<section id="posting">
<h2>Posting a job and Siya, our AI assistant</h2>
<p>You describe the problem to Siya, our AI assistant. Siya asks questions, writes a short job summary and suggests the trade. <strong>Siya can make mistakes.</strong> You see the summary before you confirm, and you are responsible for checking it. Siya does not give safety, legal or technical advice and cannot replace a professional's inspection.</p>
<p>Please do not put phone numbers, street addresses or other people's details into the chat. Your street address is entered through the address field and is shown only to the pro you choose.</p>
<p>Your job is shared, without your street address, name or phone number, with up to 10 approved pros near you. Up to 5 of them can send you an estimate.</p>
</section>
<section id="estimates">
<h2>Estimates and choosing a pro</h2>
<p>An estimate is an offer from a pro of what they expect the work to cost. It may be a single price or a range, and it may include a call-out or inspection fee. <strong>It is not a final quote.</strong> The pro confirms the final quote and the price after they have seen the job, and you agree it with them directly.</p>
<p>Until you choose a pro, you see them by first name, photo, area, badges and rating. When you tap <strong>Choose this pro to visit</strong>, that is the <strong>introduction</strong>: we share your name, mobile number and street address with that pro, we show you their business name, and we tell the other pros that you chose someone else. You can keep chatting with other pros only until you choose.</p>
<p>You are free to ask the pro questions in the in-app chat first. Please keep phone numbers, email addresses and payment details out of chat before you choose: we hide them automatically so that both sides stay protected until the introduction.</p>
</section>
<section id="money">
<h2>Money</h2>
<p><strong>GetSorted is free for clients.</strong> You never pay GetSorted. We earn a fee from the pro when you choose them (see the <a wire:navigate.hover href="{{ route('pros.agreement') }}">Pro agreement</a>). The fee does not change your price.</p>
<p><strong>You pay the pro directly, for the work, under the price you agree with them.</strong> GetSorted does not collect, hold or refund money for jobs, takes no deposit and is not responsible for the pro's price, invoices or receipts. A pro may ask for a deposit; agree this with them and ask for a receipt. Payment through GetSorted may be added later, and we will update these terms and tell you before that happens.</p>
<p>If you pay a pro and the work is not done or not done properly, your remedies are against the pro and under the law, including the Consumer Protection Act where it applies. We will help you raise the problem (see Problems and complaints) but we cannot refund the pro's price.</p>
</section>
<section id="cancel">
<h2>Changing your mind and cancelling</h2>
<p>Before you choose a pro you can cancel your job for free. After you choose, you or the pro can cancel the booking in the app. We ask for a short reason and we tell the other side. GetSorted does not charge you for cancelling.</p>
<p>Any cancellation charge a pro asks for is between you and the pro and must be reasonable and agreed with you in advance. Nothing in these terms limits any right you have under the Consumer Protection Act or other law to cancel or to a fair outcome.</p>
<p>If a pro cancels, you can post the job again and choose someone else.</p>
</section>
<section id="reviews">
<h2>Reviews</h2>
<p>Once a job is marked done you can rate the pro from 1 to 5 stars and add a comment. Reviews must be honest and based on your own experience, and must not include personal details, abuse, or false statements of fact. Your first name is shown with your review. The pro can reply once.</p>
<p>We may hide a review that breaks these rules, and we keep a record of why. We do not remove reviews only because they are critical.</p>
</section>
<section id="rules">
<h2>Rules of use</h2>
<ul><li>Use GetSorted only for genuine home-service jobs for yourself or with the owner's permission, and give correct information.</li><li>Do not harass, threaten or discriminate against pros or our staff, post unlawful or misleading content, upload photos you have no right to share, or try to interfere with or copy the service.</li><li>You give us permission to use the job description, photos and chat messages you post so that we can show them to the pros and run the service. You keep your rights in them.</li></ul>
</section>
<section id="problems">
<h2>Problems and complaints</h2>
<p>If something goes wrong with a job, first talk to the pro. If that does not work, contact us at info@usesorted.co.za. We will answer within 2 business days and try to help both sides reach a fair outcome, for example by contacting the pro, correcting information or suspending a pro who breaks our rules. We do not decide who is right in a dispute about the work and the price.</p>
<p>You may also approach the National Consumer Commission, your provincial consumer protector or a court, at any time. Nothing in these terms takes away those rights.</p>
</section>
<section id="liability">
<h2>Our responsibility</h2>
<p class="legal-note"><strong>Important: please read this part carefully.</strong> It limits what you can claim from us.</p>
<p>We promise to run the platform with reasonable skill and care and to do the checks described above. We do not promise that the service will always be available or free of errors, or that any pro will quote, arrive, do good work or be insured.</p>
<p>To the extent the law allows, GetSorted is not liable for the pro's work, advice, prices or conduct, or for loss that results from a contract between you and a pro, or for indirect loss such as lost income. Where we are liable to you, our liability for any one claim is limited to R10 000, or the amount you paid us (which is nothing for clients), whichever is greater.</p>
<p><strong>Nothing in these terms limits or excludes liability that the law does not allow to be limited</strong>, including liability for death or personal injury caused by our negligence, for gross negligence, for our fraud or dishonesty, or any right you have under the Consumer Protection Act.</p>
</section>
<section id="privacy">
<h2>Your personal information</h2>
<p>How we collect, use, share and protect your information is explained in the <a wire:navigate.hover href="{{ route('privacy') }}">Privacy notice</a>. By using GetSorted you confirm that you have read it.</p>
</section>
<section id="law">
<h2>Law and contact</h2>
<p>These terms are governed by the laws of the Republic of South Africa. If we cannot resolve a dispute informally, either of us may go to a competent South African court, and you may also use the other routes mentioned above.</p>
<p><strong>Supplier information (Electronic Communications and Transactions Act, section 43):</strong> {{  config('getsorted.legal.company_name')  }}; registration {{  config('getsorted.legal.company_registration')  }}; physical address {{  config('getsorted.legal.physical_address')  }}; email info@usesorted.co.za; telephone 031 007 0622; website https://usesorted.co.za. You can print or save these terms from your browser.</p>
</section>
</article>
</div>
</section>
</main>@include('pages.partials.footer')</div>
</x-layouts.app>
