<?php

declare(strict_types=1);

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Jobs\SendInviteMessage;
use App\Livewire\Account\Inbox;
use App\Livewire\Account\Jobs\Show as CustomerJob;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\Trade;
use App\Models\User;
use App\Notifications\UserNotice;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->customer = User::factory()->customer()->create();
    $property = Property::factory()->for($this->customer)->create();
    $this->job = ServiceJob::factory()->open()->forProperty($property)->create(['trade_id' => Trade::query()->where('key', 'plumbing')->value('id')]);
    $this->pro = Pro::factory()->approved()->create();
    $this->invite = ServiceJobInvite::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->pro->id, 'status' => 'invited', 'invited_at' => now()]);
});

it('lets the client cancel an open job, closing invites and telling the pros', function (): void {
    Notification::fake();
    $quote = Quote::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->pro->id, 'status' => QuoteStatus::Submitted]);

    $this->actingAs($this->customer);
    Livewire::test(CustomerJob::class, ['job' => $this->job])
        ->assertSee('Cancel this job')
        ->call('confirmCancel')->set('cancelReason', 'Fixed it myself')
        ->call('cancelJob')->assertHasNoErrors()->assertSee('This job was cancelled');

    expect($this->job->fresh())->status->toBe(ServiceJobStatus::Cancelled)->cancel_reason->toBe('Fixed it myself')
        ->and($this->invite->fresh()->status)->toBe(InviteStatus::Closed)
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Declined);

    Notification::assertSentTo($this->pro->user, UserNotice::class, fn (UserNotice $notice): bool => $notice->kind === 'job_cancelled');
});

it('offers no cancel once a quote is accepted, and refuses it server-side', function (): void {
    $quote = Quote::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->pro->id, 'status' => QuoteStatus::Accepted]);
    $this->job->forceFill(['status' => ServiceJobStatus::Scheduled, 'accepted_quote_id' => $quote->id])->save();

    $this->actingAs($this->customer);
    Livewire::test(CustomerJob::class, ['job' => $this->job])
        ->assertDontSee('Cancel this job')->assertSee('contact support')
        ->call('cancelJob')->assertHasErrors('cancel');

    expect($this->job->fresh()->status)->toBe(ServiceJobStatus::Scheduled);
});

it('keeps other clients from cancelling someone else\'s job', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(CustomerJob::class, ['job' => $this->job])->assertNotFound();
    expect($this->job->fresh()->status)->toBe(ServiceJobStatus::Open);
});

it('shows each person only their own notifications and marks them read when opened', function (): void {
    $this->customer->notify(new UserNotice('job_posted', 'Your job is posted', 'Body', route('jobs.show', $this->job)));
    $this->pro->user->notify(new UserNotice('job_invite', 'Pro only', 'Body', route('pros.jobs')));

    $this->actingAs($this->customer);
    $notice = $this->customer->notifications()->sole();
    Livewire::test(Inbox::class)->assertSee('Your job is posted')->assertDontSee('Pro only')
        ->call('open', $notice->id)->assertRedirect(route('jobs.show', $this->job));

    expect($this->customer->unreadNotifications()->count())->toBe(0);
});

it('only emails verified people, and only when the event asks for it', function (): void {
    $verified = User::factory()->customer()->create(['email_verified_at' => now()]);
    $unverified = User::factory()->customer()->create(['email_verified_at' => null]);

    expect((new UserNotice('k', 't', 'b', '/x', email: true))->via($verified))->toBe(['database', 'mail'])
        ->and((new UserNotice('k', 't', 'b', '/x', email: true))->via($unverified))->toBe(['database'])
        ->and((new UserNotice('k', 't', 'b', '/x'))->via($verified))->toBe(['database']);
});

it('tells an invited pro in the app and by email when a job is sent to them', function (): void {
    Notification::fake();

    (new SendInviteMessage($this->invite->id))->handle();

    Notification::assertSentTo($this->pro->user, UserNotice::class, fn (UserNotice $notice): bool => $notice->kind === 'job_invite' && $notice->email);
});

it('makes a pro accept the job before they can send an estimate', function (): void {
    $this->actingAs($this->pro->user);
    Livewire::test(ProJob::class, ['invite' => $this->invite])->call('startQuote')->assertForbidden();

    $page = Livewire::test(ProJob::class, ['invite' => $this->invite]);
    $page->assertSee('Accept job')->assertDontSee('Send an estimate')->call('acceptJob')->assertHasNoErrors()->assertSee('Send an estimate')->assertDontSee('Accept job');

    expect($this->invite->fresh()->status)->toBe(InviteStatus::Accepted)
        ->and($this->job->fresh()->status)->toBe(ServiceJobStatus::Open)
        ->and($this->job->fresh()->accepted_quote_id)->toBeNull();
});

it('refuses to accept a job that is already full', function (): void {
    $this->job->forceFill(['quotes_count' => 5])->save();

    $this->actingAs($this->pro->user);
    Livewire::test(ProJob::class, ['invite' => $this->invite])->call('acceptJob');

    expect($this->invite->fresh()->status)->not->toBe(InviteStatus::Accepted);
});
