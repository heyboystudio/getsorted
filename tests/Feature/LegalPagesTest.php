<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The legal drafts say things about the product (fee, mobile numbers, providers). These tests keep the
 * wording and the product from drifting apart; a lawyer reviews the text itself before launch.
 */
uses(RefreshDatabase::class);

it('names every provider that receives personal information in the privacy notice', function (): void {
    $page = $this->get('/privacy')->assertOk();

    foreach (['Google', 'Gemini', 'Resend', 'PayFast', 'Amazon Web Services', 'Cloudflare', 'Information Regulator', 'Information Officer', 'POPIA'] as $name) {
        $page->assertSee($name);
    }
});

it('says mobile numbers are not verified and no SMS or WhatsApp is sent, in terms and privacy', function (): void {
    $this->get('/terms')->assertOk()->assertSee('do not verify your mobile number')->assertSee('do not send you SMS or WhatsApp');
    $this->get('/privacy')->assertOk()->assertSee('we do not verify it by code')->assertSee('do not send SMS or WhatsApp');
});

it('tells clients they never pay GetSorted and that the pro pays an introduction fee', function (): void {
    $this->get('/terms')->assertOk()->assertSee('free for clients')->assertSee('You never pay GetSorted')->assertSee('Choose this pro to visit');
});

it('sets out the introduction fee, free allowance and notice period in the pro agreement', function (): void {
    $this->get('/pros/agreement')->assertOk()->assertSee('R99 per introduction')->assertSee('first 10 introductions are free')
        ->assertSee('at least 30 days before any fee starts')->assertSee('include no VAT')->assertSee('does not collect or hold job payments');
});

it('flags the liability clauses and keeps what the law will not let us exclude', function (): void {
    foreach (['/terms', '/pros/agreement'] as $path) {
        $this->get($path)->assertOk()->assertSee('Important: please read this part carefully');
    }

    $this->get('/terms')->assertSee('gross negligence');
});

it('shows the supplier details the Electronic Communications and Transactions Act asks for', function (): void {
    config()->set('getsorted.legal.company_name', 'Example (Pty) Ltd');
    config()->set('getsorted.legal.company_registration', '2026/123456/07');

    $this->get('/terms')->assertOk()->assertSee('Supplier information')->assertSee('Example (Pty) Ltd')->assertSee('2026/123456/07')->assertSee('info@usesorted.co.za');
});
