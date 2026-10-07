<?php

declare(strict_types=1);

use App\Livewire\Welcome;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

it('hands the home page description to the booking thread', function (): void {
    Livewire::test(Welcome::class)
        ->set('description', 'The kitchen tap will not stop dripping')
        ->call('start')
        ->assertHasNoErrors()
        ->assertRedirect(route('book'));

    expect(session(Welcome::DESCRIPTION_KEY)['text'])->toBe('The kitchen tap will not stop dripping');
});

it('goes straight to the chosen trade', function (): void {
    Livewire::test(Welcome::class)
        ->set('tradeKey', 'plumbing')
        ->call('start')
        ->assertRedirect(route('book.trade', 'plumbing'));
});

it('starts a booking with no description at all', function (): void {
    Livewire::test(Welcome::class)->call('start')->assertHasNoErrors()->assertRedirect(route('book'));

    expect(session()->has(Welcome::DESCRIPTION_KEY))->toBeFalse();
});

it('rejects a description that is too short or too long', function (string $description): void {
    Livewire::test(Welcome::class)->set('description', $description)->call('start')->assertHasErrors(['description']);

    expect(session()->has(Welcome::DESCRIPTION_KEY))->toBeFalse();
})->with(['too short' => 'Leak', 'too long' => str_repeat('a', 501)]);

it('ignores an unknown trade', function (): void {
    Livewire::test(Welcome::class)->set('tradeKey', 'astrology')->call('start')->assertRedirect(route('book'));
});

it('names the product Get Sorted in the page title', function (): void {
    $this->get('/')->assertOk()->assertSee('<title>Get Sorted</title>', false);
});

it('loads every home page asset from the site itself, as the security headers require (decision 055)', function (): void {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<(?:script|link)\b[^>]*(?:src|href)="(https?:\/\/[^"]+)"/i', (string) $html, $matches);
    $external = array_filter($matches[1], fn (string $url): bool => ! str_starts_with($url, (string) config('app.url')));

    expect($external)->toBe([]);
});

it('does not list pros on the public home page (founder 2026-10-07)', function (): void {
    $this->get('/')->assertOk()->assertDontSee('Browse all pros')->assertDontSee('Pros People Rate');
});

it('offers a signed-in visitor their account instead of sign-up', function (): void {
    $this->actingAs(User::factory()->customer()->create())
        ->get('/')->assertOk()->assertSee(route('account.home'), false)->assertDontSee('>Sign up <', false);
});
