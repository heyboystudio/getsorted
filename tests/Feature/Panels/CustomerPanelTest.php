<?php

declare(strict_types=1);

use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Support\JobStages;
use App\Livewire\Account\Home;
use App\Livewire\Account\Jobs\Index as JobsIndex;
use App\Livewire\Account\Jobs\Show as JobShow;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function panelJob(User $customer, ServiceJobStatus $status = ServiceJobStatus::Open, array $attributes = []): ServiceJob
{
    $job = ServiceJob::factory()->open()->create(['customer_id' => $customer->id, ...$attributes]);

    if ($status !== ServiceJobStatus::Open) {
        $job->forceFill(['status' => $status])->save();
    }

    return $job->refresh();
}

function panelEvent(ServiceJob $job, string $type, ServiceJobStatus $to = ServiceJobStatus::Open): ServiceJobEvent
{
    $event = new ServiceJobEvent(['from_status' => null, 'to_status' => $to, 'event_type' => $type, 'actor_type' => ActorType::Customer, 'actor_id' => $job->customer_id, 'payload' => []]);
    $event->serviceJob()->associate($job);
    $event->save();

    return $event;
}

function panelBook(ServiceJob $job, string $proName = 'Dlamini Plumbing'): Quote
{
    $pro = Pro::factory()->approved()->create(['business_name' => $proName]);
    $quote = Quote::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, 'status' => 'accepted']);
    $job->forceFill(['accepted_quote_id' => $quote->id, 'scheduled_for' => now()->addDays(2)->toDateString(), 'status' => ServiceJobStatus::Scheduled])->save();

    return $quote;
}

beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create(['first_name' => 'Nomvula']);
    $this->actingAs($this->customer);
});

// --- Needs you (AC5) ---------------------------------------------------------------------

it('lists what needs the customer, most urgent first, and hides the strip when nothing does (spec 021, AC5)', function (): void {
    Livewire::test(Home::class)->assertDontSee('Needs you');

    $quoted = panelJob($this->customer);
    Quote::factory()->create(['service_job_id' => $quoted->id]);
    Quote::factory()->create(['service_job_id' => $quoted->id]);
    panelJob($this->customer, ServiceJobStatus::AwaitingFinalPayment);
    panelJob($this->customer, ServiceJobStatus::Draft);

    Livewire::test(Home::class)
        ->assertSee('Needs you')
        ->assertSeeInOrder(['Check the work, then pay', '2 quotes to compare', 'Finish your request']);
});

it('does not count an expired or withdrawn quote as one to compare (spec 021, AC5)', function (): void {
    $job = panelJob($this->customer);
    Quote::factory()->create(['service_job_id' => $job->id, 'valid_until' => now()->subDays(2)->toDateString()]);
    Quote::factory()->create(['service_job_id' => $job->id, 'status' => 'withdrawn']);

    Livewire::test(Home::class)->assertDontSee('to compare')->assertDontSee('Needs you');
});

it('flags unread chat messages and links to the chat (spec 021, AC5)', function (): void {
    $job = panelJob($this->customer);
    $pro = Pro::factory()->approved()->create();
    ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id]);
    $conversation = JobConversation::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id]);
    JobMessage::factory()->count(2)->create(['job_conversation_id' => $conversation->id, 'sender_type' => 'pro']);

    Livewire::test(Home::class)->assertSee('2 new messages')->assertSee(route('jobs.show', $job).'#chats', false);
});

it('offers to finish or remove an unfinished request from the strip (spec 021, AC5)', function (): void {
    $draft = panelJob($this->customer, ServiceJobStatus::Draft);

    Livewire::test(Home::class)->assertSee('Finish your request')->assertSee('Remove')
        ->call('removeDraft', $draft->public_id)->assertDontSee('Finish your request');
});

it('never shows another customer\'s jobs, quotes or messages (spec 021, AC5, AC31)', function (): void {
    $other = User::factory()->customer()->create();
    $theirs = panelJob($other, ServiceJobStatus::AwaitingFinalPayment);
    Quote::factory()->create(['service_job_id' => $theirs->id]);
    panelEvent($theirs, 'job_posted');

    Livewire::test(Home::class)->assertDontSee('Needs you')->assertDontSee('Recent activity')->assertDontSee($theirs->trade->name);
});

// --- Active jobs and activity (AC6, AC8) -------------------------------------------------

it('shows active jobs with the pro and photo placeholder once a quote is accepted (spec 021, AC6)', function (): void {
    $open = panelJob($this->customer);
    $booked = panelJob($this->customer);
    panelBook($booked, 'Naidoo Electrical');
    $done = panelJob($this->customer, ServiceJobStatus::Completed);

    Livewire::test(Home::class)
        ->assertSee('Active jobs')
        ->assertSee($open->trade->name)
        ->assertSee('Booked')->assertSee('Naidoo Electrical')
        ->assertDontSee(route('jobs.show', $done), false);
});

it('puts the Siya prompt first on Home, above the needs strip, because booking starts with Siya (spec 028)', function (): void {
    panelJob($this->customer, ServiceJobStatus::Draft);

    Livewire::test(Home::class)->assertSeeInOrder(['What’s going on at home?', 'Needs you'])->assertDontSee('Blocked drain');
});

it('shows only the customer\'s own allow-listed events as recent activity, newest first (spec 021, AC8)', function (): void {
    $job = panelJob($this->customer);
    panelEvent($job, 'job_posted');
    panelEvent($job, 'admin_force_close_note');
    panelEvent($job, 'quote_accepted', ServiceJobStatus::Scheduled);

    $other = panelJob(User::factory()->customer()->create());
    panelEvent($other, 'job_expired');

    Livewire::test(Home::class)
        ->assertSee('Recent activity')
        ->assertSeeInOrder(['You accepted a quote', 'You posted your request'])
        ->assertDontSee('admin force close note')->assertDontSee('No quote was accepted in time');
});

it('shows at most five activity entries (spec 021, AC8)', function (): void {
    $job = panelJob($this->customer);
    foreach (range(1, 7) as $i) {
        panelEvent($job, 'job_posted');
    }

    $html = Livewire::test(Home::class)->html();

    expect(substr_count($html, 'You posted your request'))->toBe(5);
});

// --- Jobs list (AC9) ---------------------------------------------------------------------

it('groups jobs into Active, Done and Cancelled, leaving out removed drafts (spec 021, AC9)', function (): void {
    $open = panelJob($this->customer);
    $draft = panelJob($this->customer, ServiceJobStatus::Draft);
    $done = panelJob($this->customer, ServiceJobStatus::Completed);
    $expired = panelJob($this->customer, ServiceJobStatus::Expired);
    $removed = panelJob($this->customer, ServiceJobStatus::Cancelled, ['posted_at' => null]);
    $cancelled = panelJob($this->customer, ServiceJobStatus::Cancelled);

    $active = Livewire::test(JobsIndex::class);
    $active->assertSee(route('jobs.show', $open), false)->assertSee(route('booking.continue', $draft), false)
        ->assertDontSee(route('jobs.show', $done), false);

    Livewire::test(JobsIndex::class)->set('tab', 'done')->assertSee(route('jobs.show', $done), false)->assertDontSee(route('jobs.show', $open), false);

    Livewire::test(JobsIndex::class)->set('tab', 'ended')
        ->assertSee(route('jobs.show', $expired), false)->assertSee(route('jobs.show', $cancelled), false)
        ->assertDontSee(route('jobs.show', $removed), false);
});

it('shows each tab\'s empty state and ignores an unknown tab (spec 021, AC9, AC30)', function (): void {
    Livewire::test(JobsIndex::class)->assertSee('No active jobs');
    Livewire::test(JobsIndex::class)->set('tab', 'done')->assertSee('Finished jobs will appear here');
    Livewire::test(JobsIndex::class)->set('tab', 'nonsense')->assertSee('No active jobs');
});

it('keeps other customers\' jobs out of the list and guests out of the page (spec 021, AC9, AC31)', function (): void {
    $theirs = panelJob(User::factory()->customer()->create());

    Livewire::test(JobsIndex::class)->assertDontSee(route('jobs.show', $theirs), false);

    auth()->logout();
    $this->get(route('jobs.index'))->assertRedirect(route('login'));
});

// --- Job page (AC10, AC11) ---------------------------------------------------------------

it('maps each job status to its progress steps (spec 021, AC10)', function (ServiceJobStatus $status, ?string $current): void {
    $stages = JobStages::for($status);

    if ($current === null) {
        expect($stages)->toBeNull();

        return;
    }

    $currentLabels = array_column(array_filter($stages, fn (array $stage): bool => $stage['state'] === 'current'), 'label');
    expect($currentLabels === [] ? null : $currentLabels[0])->toBe($current === 'all done' ? null : $current);

    if ($current === 'all done') {
        expect(array_unique(array_column($stages, 'state')))->toBe(['done']);
    }
})->with([
    'draft has none' => [ServiceJobStatus::Draft, null],
    'open' => [ServiceJobStatus::Open, 'Quotes'],
    'awaiting deposit' => [ServiceJobStatus::AwaitingDeposit, 'Booked'],
    'scheduled' => [ServiceJobStatus::Scheduled, 'Booked'],
    'in progress' => [ServiceJobStatus::InProgress, 'Work'],
    'final payment' => [ServiceJobStatus::AwaitingFinalPayment, 'Done'],
    'completed' => [ServiceJobStatus::Completed, 'all done'],
    'disputed has none' => [ServiceJobStatus::Disputed, null],
    'cancelled has none' => [ServiceJobStatus::Cancelled, null],
    'expired has none' => [ServiceJobStatus::Expired, null],
]);

it('shows the stage tracker and a customer-only timeline on the job page (spec 021, AC10)', function (): void {
    $job = panelJob($this->customer);
    panelEvent($job, 'job_posted');
    panelEvent($job, 'internal_matching_wave');

    Livewire::test(JobShow::class, ['job' => $job])
        ->assertSee('Job progress')->assertSee('Quotes')
        ->assertSee('Timeline')->assertSee('You posted your request')
        ->assertDontSee('internal matching wave')
        ->assertSee(route('jobs.index'), false);
});

it('hides the tracker and timeline when there is nothing to show (spec 021, AC10, AC30)', function (): void {
    $job = panelJob($this->customer, ServiceJobStatus::Draft);

    Livewire::test(JobShow::class, ['job' => $job])->assertDontSee('Job progress')->assertDontSee('Timeline');
});

it('offers to book the same trade again only on a finished job (spec 021, AC11)', function (): void {
    $done = panelJob($this->customer, ServiceJobStatus::Completed);
    $open = panelJob($this->customer);
    $link = route('book.trade', $done->trade);

    Livewire::test(JobShow::class, ['job' => $done])->assertSee('again')->assertSee($link, false);
    Livewire::test(JobShow::class, ['job' => $open])->assertDontSee('again');

    $this->get($link)->assertOk();
});

it('does not offer booking again when the trade is no longer offered (spec 021, AC11)', function (): void {
    $done = panelJob($this->customer, ServiceJobStatus::Closed);
    $done->trade->forceFill(['is_active' => false])->save();

    Livewire::test(JobShow::class, ['job' => $done])->assertDontSee('again');
});

it('still hides another customer\'s job page (spec 021, AC31)', function (): void {
    $theirs = panelJob(User::factory()->customer()->create());

    $this->get(route('jobs.show', $theirs))->assertNotFound();
});

it('marks Jobs as the current tab on the jobs list and on a job (spec 021, AC1)', function (): void {
    $job = panelJob($this->customer);

    // The Flux workspace (spec 028) marks the sidebar item with data-current and the phone tab with aria-current.
    foreach ([route('jobs.index'), route('jobs.show', $job)] as $url) {
        $html = $this->get($url)->getContent();
        preg_match('/<a[^>]*href="[^"]*\/app\/jobs"[^>]*>/', $html, $link);
        expect($link[0] ?? '')->toContain('data-current')
            ->and($html)->toMatch('/aria-current="page"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Jobs/s');
    }
});
