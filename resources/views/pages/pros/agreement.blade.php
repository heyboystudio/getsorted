<x-layouts.app :title="__('Pro agreement')"><div class="getsorted-site">@include('pages.partials.header')
<main>
<section class="page-hero legal-hero">
<div class="site-container">
<p class="section-kicker">LEGAL INFORMATION FOR PROS</p>
<span class="reviewed-badge">Reviewed by a South African attorney on 8 October 2026</span>
<h1>Pro agreement.</h1>
<p>Version {{ config('getsorted.legal.pro_agreement_version') }}. This agreement covers tradespeople and businesses that receive jobs through GetSorted.</p>
</div>
</section>
<section class="section">
<div class="site-container legal-grid">
<nav class="legal-toc" aria-label="On this page">
<a href="#parties">Who this agreement is between</a>
<a href="#independent">You are independent</a>
<a href="#joining">Joining and vetting</a>
<a href="#jobs">How jobs and estimates work</a>
<a href="#fee">The introduction and our fee</a>
<a href="#work">Your work and your client</a>
<a href="#information">Client information</a>
<a href="#reviews">Reviews and ratings</a>
<a href="#conduct">Standards of conduct</a>
<a href="#suspend">Suspension and leaving</a>
<a href="#liability">Responsibility</a>
<a href="#changes">Changes and ending</a>
<a href="#law">Law and disputes</a>
</nav>
<article class="legal-content">
<section id="parties">
<h2>Who this agreement is between</h2>
<p>This agreement is between <strong>{{  config('getsorted.legal.company_name')  }}</strong> (registration {{  config('getsorted.legal.company_registration')  }}), {{  config('getsorted.legal.physical_address')  }} ("GetSorted", "we") and you, the tradesperson or business that applies to receive jobs through GetSorted ("you"). You accept it when you submit your application. If you apply for a business, you confirm that you may bind it.</p>
</section>
<section id="independent">
<h2>You are independent</h2>
<p>You are an independent business. You are not our employee, worker, partner or agent, and nothing in this agreement makes you one. You decide whether to take a job, how and when to do it, and what tools and helpers to use. You cannot make promises on GetSorted's behalf. You are responsible for your own tax, VAT (if you are registered), UIF and insurance.</p>
<p>Only you, or people you properly employ and supervise, may do jobs you win through GetSorted, and you are responsible for their work and conduct.</p>
</section>
<section id="joining">
<h2>Joining and vetting</h2>
<p>You must be 18 or older and allowed to work in South Africa, and everything you tell us must be true and kept up to date. We review your identity document, proof of address, profile photo, trade registrations you claim (such as PIRB or a registered electrician) and references, and we may phone your references. By applying you consent to that, and to us keeping copies for vetting (see our <a wire:navigate.hover href="{{ route('privacy') }}">Privacy notice</a>).</p>
<p>A registration badge is shown only while we hold a valid registration document. You must tell us when a registration expires or is withdrawn. We do not require a registration for every trade, but where the law requires one for the work (for example an electrical certificate of compliance), you must hold it and provide it to the client.</p>
<p>We decide, in our reasonable discretion, who is approved. We may ask for changes, refuse an application, or suspend or end your access if you give false information or break this agreement. We do not currently run criminal-record checks and we tell clients that.</p>
</section>
<section id="jobs">
<h2>How jobs and estimates work</h2>
<ul><li>We invite you to jobs that match your trade and work area. You choose whether to accept an invite and whether to send an estimate. Joining is free.</li><li>An estimate is an offer. It can be a price or a range, and may include a call-out or inspection fee. Honour your estimate for the days it says it is valid, and keep the final quote after your visit fair and consistent with it, explaining any difference.</li><li>Before the client chooses you, you see only the job details needed to estimate: the trade, the description, photos, the suburb and approximate distance. You do not receive the client's name, phone number, email or street address. We mask contact details in chat and estimates.</li><li><strong>Do not try to get around the introduction.</strong> Do not ask for, offer or exchange contact details, or move the conversation off GetSorted, before the client has chosen you. Do not post fake, copied or placeholder estimates, or ask others to review you.</li></ul>
</section>
<section id="fee">
<h2>The introduction and our fee</h2>
<p>When a client taps <strong>Choose this pro to visit</strong>, that is the <strong>introduction</strong>. We then give you the client's name, mobile number and street address, and show the client your business name. We record every introduction.</p>
<p><strong>Fee.</strong> At launch the introduction fee is <strong>switched off</strong>: introductions are free. We may switch it on. If we do, the fee is <strong>R99 per introduction</strong>, paid by you, and your <strong>first 10 introductions are free</strong>. We will tell you by email and in the app at least 30 days before any fee starts or changes, and you can leave first. The fee is charged only when a client chooses you. Clients never pay us.</p>
<p><strong>Credit.</strong> If the fee is on, you pay in advance by buying credit through PayFast in packs we show you. Each introduction takes the fee from your credit. If your credit is below one fee you cannot send new estimates until you add more. Credit does not expire. Payments are made on PayFast's secure page, and we never see your card details.</p>
<p><strong>VAT.</strong> While GetSorted is not registered for VAT, our fees and credit packs include no VAT. If that changes we will tell you in advance and show prices with VAT.</p>
<p><strong>When we give credit back.</strong> We do not refund a fee just because a job did not go ahead after the introduction. We will add the credit back if the introduction was our error, if the client cancels before you have visited and gave us no reason connected to you, or if the client proves to be a duplicate or fake account. Ask support within 14 days. Unused credit is refunded to you on request if you leave GetSorted, less any payment processor charge we cannot recover. Nothing here limits your rights under the Consumer Protection Act.</p>
</section>
<section id="work">
<h2>Your work and your client</h2>
<p>The work, the final price, the invoice, the payment and any warranty are a contract between you and the client. <strong>GetSorted does not collect or hold job payments, takes no deposit and is not a party to your contract.</strong> You must give the client a proper receipt or invoice, charge the price you agreed, and do the work with reasonable skill and care, safely, lawfully and on time. You are liable to the client for your work, and for anyone who works for you.</p>
<p>You should hold suitable public liability insurance for the work you do. Where we ask, you must show it.</p>
<p>You may cancel an accepted booking only for a good reason and must give it in the app. Cancelling often, not turning up or not responding may lead to a warning or suspension.</p>
</section>
<section id="information">
<h2>Client information</h2>
<p>After the introduction you are responsible, under the Protection of Personal Information Act, for the personal information you receive about the client. You must use it only to quote for, arrange and do that job and to meet your legal duties, keep it secure, not share it or use it to market to the client, and delete it when you no longer need it. Tell us at once if there is a security compromise involving it.</p>
<p>We process your own information as described in the <a wire:navigate.hover href="{{ route('privacy') }}">Privacy notice</a>. Your business name, photo, trades, area, badges, rating and reviews are shown to clients.</p>
</section>
<section id="reviews">
<h2>Reviews and ratings</h2>
<p>Clients can rate you after a job is done and you can reply once. Do not pressure or reward clients for reviews, retaliate against a reviewer, or reveal their details. We may hide a review that breaks our rules and keep a record of why. Ratings are shown only when you have at least one review.</p>
</section>
<section id="conduct">
<h2>Standards of conduct</h2>
<p>Treat clients and our staff with respect. Do not discriminate, harass, threaten or mislead anyone. Do not use GetSorted for anything unlawful. Do not copy or interfere with the platform. Do not share your account.</p>
</section>
<section id="suspend">
<h2>Suspension and leaving</h2>
<p>We may suspend or end your access for a serious breach, for false information, for client safety concerns, or if your registration lapses. For less serious problems we will warn you first. If we suspend or end your access, we will tell you why and you can ask us to review the decision within 14 days. You can pause your availability or close your account at any time. Jobs you have already accepted should still be completed or cancelled properly.</p>
</section>
<section id="liability">
<h2>Responsibility</h2>
<p class="legal-note"><strong>Important: please read this part carefully.</strong></p>
<p>You indemnify GetSorted against claims, losses and costs that arise from your work, your breach of this agreement or of the law, or your misuse of client information.</p>
<p>To the extent the law allows, GetSorted is not liable for lost income, lost jobs or other indirect loss, or for what a client does or fails to do. Our total liability to you for any claim is limited to the fees you paid us in the 12 months before the claim. <strong>This does not limit liability that cannot lawfully be limited</strong>, such as liability for our gross negligence or fraud.</p>
</section>
<section id="changes">
<h2>Changes and ending</h2>
<p>We may change this agreement or our fees by giving you at least 30 days' notice by email and in the app. If you do not agree, you can stop using GetSorted before the change takes effect.</p>
</section>
<section id="law">
<h2>Law and disputes</h2>
<p>South African law governs this agreement. Please first raise any problem with us at info@usesorted.co.za so that we can try to settle it in good faith. If we cannot, the dispute goes to arbitration under the rules of the Arbitration Foundation of Southern Africa (AFSA), unless either of us needs urgent relief from a court.</p>
<p>Our details: {{  config('getsorted.legal.company_name')  }}; registration {{  config('getsorted.legal.company_registration')  }}; {{  config('getsorted.legal.physical_address')  }}; info@usesorted.co.za; 031 007 0622.</p>
</section>
</article>
</div>
</section>
</main>@include('pages.partials.footer')</div>
</x-layouts.app>
