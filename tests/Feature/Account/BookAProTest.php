<?php

declare(strict_types=1);

use App\Livewire\Account\Home;
use App\Livewire\Booking\Thread;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('links Book a pro on the account home to the booking thread (spec 017, AC1)', function (): void {
    $this->seed(CatalogueSeeder::class);
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route('account.home'))->assertSee(route('book'), false)->assertSee('What’s going on at home?');
    $this->actingAs($customer)->get(route('account.book'))->assertRedirect('/book');
});

it('hands the "What’s going on at home?" text or a chip to Siya as the first message (spec 017, AC1)', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Home::class)->set('problem', 'x')->call('describe')->assertHasErrors('problem');
    Livewire::test(Home::class)->set('problem', 'My DB board keeps tripping')->call('describe')->assertRedirect(route('book'));
    expect(session(Thread::START_KEY))->toBe('My DB board keeps tripping');

    Livewire::test(Home::class)->call('describe', 'No hot water')->assertRedirect(route('book'));
    expect(session(Thread::START_KEY))->toBe('No hot water');
});

it('sends guests to log in for the account booking page', function (): void {
    $this->get(route('account.book'))->assertRedirect();
});
