<?php

declare(strict_types=1);

use App\Livewire\Welcome;
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
