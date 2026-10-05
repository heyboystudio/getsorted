<?php

declare(strict_types=1);

use App\Models\Service;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps signed-in customers in their account when they book a pro', function (): void {
    $this->seed(CatalogueSeeder::class);
    $customer = User::factory()->customer()->create();
    $plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $leak = Service::query()->where('key', 'leak_repair')->sole();

    $this->actingAs($customer)->get(route('account.home'))->assertSee(route('account.book'), false);

    $this->actingAs($customer)->get(route('account.book'))->assertOk()
        ->assertSee(route('assistant'), false)
        ->assertSee(route('booking.start', [$plumbing, $leak->key]), false);
});

it('sends guests to log in for the account booking page', function (): void {
    $this->get(route('account.book'))->assertRedirect();
});
