<?php

declare(strict_types=1);

use App\Livewire\Account\Home;
use App\Livewire\Booking\Thread;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('makes the Siya box the way to book on the account home (spec 017 AC1, spec 028)', function (): void {
    $this->seed(CatalogueSeeder::class);
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route('account.home'))->assertSee('What’s going on at home?')->assertSee('Ask Siya')->assertDontSee('Book a pro');
    $this->actingAs($customer)->get(route('account.book'))->assertRedirect('/book');
});

it('hands the "What’s going on at home?" text to Siya as the first message (spec 017, AC1)', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Home::class)->set('problem', 'x')->call('describe')->assertHasErrors('problem');
    Livewire::test(Home::class)->set('problem', 'My DB board keeps tripping')->call('describe')->assertRedirect(route('book'));
    expect(session(Thread::START_KEY))->toBe('My DB board keeps tripping');

    Livewire::test(Home::class)->assertDontSee('No hot water')->assertSee('Ask Siya');
});

it('sends guests to log in for the account booking page', function (): void {
    $this->get(route('account.book'))->assertRedirect();
});
