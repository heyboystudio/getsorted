<?php

declare(strict_types=1);

use App\Domain\ServiceJobs\Actions\SendJobMessage;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Livewire\Account\Messages;
use App\Models\Pro;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->customer = User::factory()->customer()->create();
    $property = Property::factory()->for($this->customer)->create();
    $this->job = ServiceJob::factory()->open()->forProperty($property)->create(['trade_id' => Trade::query()->where('key', 'plumbing')->value('id')]);
    $this->pro = Pro::factory()->approved()->create(['business_name' => 'Dlamini Plumbing']);
    ServiceJobInvite::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->pro->id, 'status' => 'viewed', 'invited_at' => now()]);
});

it('lists each person\'s conversations with the last message and unread count', function (): void {
    app(SendJobMessage::class)->handle($this->customer, $this->job, $this->pro, 'Can you come today?', []);
    $this->travel(1)->minutes();
    app(SendJobMessage::class)->handle($this->pro->user, $this->job, $this->pro, 'Yes, around 3pm.', []);

    $this->actingAs($this->customer);
    Livewire::test(Messages::class)->assertSee('Yes, around 3pm.')->assertSee('Plumbing')->assertSee('1 unread message');

    expect(JobChat::unreadTotal($this->customer))->toBe(1)->and(JobChat::unreadTotal($this->pro->user))->toBe(0);

    $this->actingAs($this->pro->user);
    Livewire::test(Messages::class)->assertSee('Yes, around 3pm.')->assertDontSee('unread message');
});

it('keeps other people\'s conversations out of the inbox', function (): void {
    app(SendJobMessage::class)->handle($this->customer, $this->job, $this->pro, 'Private question', []);

    $this->actingAs(User::factory()->customer()->create());
    Livewire::test(Messages::class)->assertDontSee('Private question')->assertSee('No conversations yet');
    expect(JobChat::unreadTotal(User::factory()->customer()->create()))->toBe(0);
});
