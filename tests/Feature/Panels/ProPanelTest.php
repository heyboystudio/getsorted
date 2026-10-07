<?php

declare(strict_types=1);

use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Support\ProPipeline;
use App\Domain\Pros\Actions\SetProAvailability;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Livewire\Pros\Jobs\Index as ProJobs;
use App\Livewire\Pros\Welcome;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\Suburb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function todayInvite(Pro $pro, array $invite = [], ?ServiceJob $job = null): ServiceJobInvite
{
    $job ??= ServiceJob::factory()->open()->create();

    return ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, ...$invite]);
}

function todayQuote(ServiceJobInvite $invite, string $status = 'submitted', array $quote = []): Quote
{
    return Quote::factory()->create(['service_job_id' => $invite->service_job_id, 'pro_id' => $invite->pro_id, 'status' => $status, ...$quote]);
}

function todayWin(ServiceJobInvite $invite, ServiceJobStatus $status = ServiceJobStatus::Scheduled, ?string $date = null): ServiceJob
{
    $quote = todayQuote($invite, 'accepted');
    $job = $invite->serviceJob;
    $job->forceFill(['accepted_quote_id' => $quote->id, 'status' => $status, 'scheduled_for' => $date ?? now()->addDays(2)->toDateString()])->save();

    return $job->refresh();
}

beforeEach(function (): void {
    $this->user = User::factory()->pro()->create(['first_name' => 'Thabo']);
    $this->pro = Pro::factory()->approved()->create(['user_id' => $this->user->id, 'business_name' => 'Thabo Plumbing']);
    $this->actingAs($this->user);
});

// --- Today (AC20) ------------------------------------------------------------------------

it('lists open invites with the least time left first (spec 021, AC20)', function (): void {
    $later = todayInvite($this->pro, ['expires_at' => now()->addHours(30)], ServiceJob::factory()->open()->for(Service::factory()->state(['name' => 'Zebra geyser job']), 'service')->create());
    $sooner = todayInvite($this->pro, ['expires_at' => now()->addHours(10)], ServiceJob::factory()->open()->for(Service::factory()->state(['name' => 'Aardvark drain job']), 'service')->create());

    // Alphabetical order would put the later invite first, so this proves it is sorted by time left.
    Livewire::test(Welcome::class)->assertSee('Answer these first')
        ->assertSeeInOrder(['Aardvark drain job', 'Zebra geyser job'])
        ->assertSee(route('pros.jobs.show', $sooner), false)->assertSee(route('pros.jobs.show', $later), false)->assertSee('left to reply');
});

it('hides sections with nothing in them and shows a calm empty state (spec 021, AC20)', function (): void {
    Livewire::test(Welcome::class)
        ->assertDontSee('Answer these first')->assertDontSee('Coming up')->assertDontSee('Waiting for the customer')
        ->assertSee("No new jobs right now. We'll WhatsApp you when one fits.");
});

it('shows quotes still waiting on the customer (spec 021, AC20)', function (): void {
    $invite = todayInvite($this->pro, ['status' => InviteStatus::Quoted]);
    todayQuote($invite, 'submitted', ['total_cents' => 123456]);

    Livewire::test(Welcome::class)->assertSee('Waiting for the customer')->assertSee('R 1 234.56')->assertDontSee('Answer these first');
});

it('shows the address and contact only for jobs this pro won, within the next 7 days (spec 021, AC20)', function (): void {
    $mine = todayInvite($this->pro, ['status' => InviteStatus::Quoted], ServiceJob::factory()->open()->create());
    $customer = $mine->serviceJob->customer;
    $customer->forceFill(['first_name' => 'Nomvula', 'phone_e164' => '+27829990000'])->save();
    $mine->serviceJob->property->forceFill(['street_address' => '7 Private Lane'])->save();
    todayWin($mine);

    $far = todayInvite($this->pro, ['status' => InviteStatus::Quoted], ServiceJob::factory()->open()->create());
    $far->serviceJob->property->forceFill(['street_address' => '99 Far Away Road'])->save();
    todayWin($far, date: now()->addDays(30)->toDateString());

    $other = Pro::factory()->approved()->create();
    $theirInvite = todayInvite($other, ['status' => InviteStatus::Quoted], ServiceJob::factory()->open()->create());
    $theirInvite->serviceJob->property->forceFill(['street_address' => '1 Rival Street'])->save();
    todayWin($theirInvite);

    Livewire::test(Welcome::class)->assertSee('Coming up')
        ->assertSee('Nomvula')->assertSee('+27829990000')->assertSee('7 Private Lane')
        ->assertDontSee('99 Far Away Road')->assertDontSee('1 Rival Street');
});

it('keeps the customer\'s street address out of invites that were not won (spec 021, AC20)', function (): void {
    $invite = todayInvite($this->pro);
    $invite->serviceJob->property->forceFill(['street_address' => '7 Private Lane'])->save();
    $invite->serviceJob->customer->forceFill(['phone_e164' => '+27829990000'])->save();

    Livewire::test(Welcome::class)->assertSee('Answer these first')->assertDontSee('7 Private Lane')->assertDontSee('+27829990000');
});

it('never shows another pro\'s invites or jobs (spec 021, AC20, AC31)', function (): void {
    $other = Pro::factory()->approved()->create();
    $theirs = todayInvite($other);

    Livewire::test(Welcome::class)->assertDontSee($theirs->serviceJob->service->name)->assertDontSee('Answer these first');
    Livewire::test(ProJobs::class)->assertDontSee(route('pros.jobs.show', $theirs), false);
});

it('keeps pros who are not approved on the application welcome, not Today (spec 021, AC2, AC20)', function (): void {
    $applicant = User::factory()->pro()->create();
    Pro::factory()->create(['user_id' => $applicant->id, 'status' => 'submitted']);
    $this->actingAs($applicant);

    Livewire::test(Welcome::class)->assertDontSee("You're available")->assertSee('under review');
});

// --- Pause (AC21) ------------------------------------------------------------------------

it('pauses and resumes new invites from Today and says so clearly (spec 021, AC21)', function (): void {
    Livewire::test(Welcome::class)->assertSee("You're available")->call('setPaused', true)
        ->assertSee("You're paused")->assertSee('Resume');
    expect($this->pro->refresh()->isPaused())->toBeTrue();

    Livewire::test(Welcome::class)->assertSee("You're paused")->call('setPaused', false)->assertSee("You're available");
    expect($this->pro->refresh()->isPaused())->toBeFalse();
});

it('is idempotent and writes one log entry per real change (spec 021, AC21)', function (): void {
    $set = app(SetProAvailability::class);
    $set->handle($this->user, $this->pro, true);
    $set->handle($this->user, $this->pro, true);
    $set->handle($this->user, $this->pro, false);
    $set->handle($this->user, $this->pro, false);

    expect(Activity::query()->whereIn('description', ['pro paused', 'pro resumed'])->pluck('description')->all())->toBe(['pro paused', 'pro resumed']);
});

it('excludes a paused pro from invites but leaves their jobs and approval alone (spec 021, AC21)', function (): void {
    $service = Service::factory()->create();
    $suburb = Suburb::factory()->create();
    $this->pro->services()->attach($service);
    $this->pro->serviceAreas()->attach($suburb);
    $eligible = fn () => app(EligibleProsQuery::class)->for($service, $suburb)->whereKey($this->pro->id)->exists();
    $invite = todayInvite($this->pro);
    expect($eligible())->toBeTrue();

    app(SetProAvailability::class)->handle($this->user, $this->pro, true);

    expect($eligible())->toBeFalse();
    expect($this->pro->refresh()->status->value)->toBe('approved')->and($invite->refresh()->status)->toBe(InviteStatus::Invited);
    Livewire::test(Welcome::class)->assertSee('Answer these first')->assertSee("You're paused");

    app(SetProAvailability::class)->handle($this->user, $this->pro, false);
    expect($eligible())->toBeTrue();
});

it('only lets an approved pro pause their own account (spec 021, AC21, AC31)', function (): void {
    $other = Pro::factory()->approved()->create();
    expect(fn () => app(SetProAvailability::class)->handle($this->user, $other, true))->toThrow(HttpException::class);

    $applicant = User::factory()->pro()->create();
    $draft = Pro::factory()->create(['user_id' => $applicant->id, 'status' => 'submitted']);
    expect(fn () => app(SetProAvailability::class)->handle($applicant, $draft, true))->toThrow(HttpException::class);
    expect($other->refresh()->isPaused())->toBeFalse()->and($draft->refresh()->isPaused())->toBeFalse();
});

// --- Jobs pipeline (AC23) ----------------------------------------------------------------

it('sorts each invite into Invites, Quoted, Booked or Done (spec 021, AC23)', function (): void {
    $invite = todayInvite($this->pro);
    $quoted = todayInvite($this->pro, ['status' => InviteStatus::Quoted]);
    todayQuote($quoted);
    $won = todayInvite($this->pro, ['status' => InviteStatus::Quoted]);
    todayWin($won);
    $finished = todayInvite($this->pro, ['status' => InviteStatus::Quoted]);
    todayWin($finished, ServiceJobStatus::Completed);

    $pipeline = ProPipeline::for($this->pro);

    expect($pipeline['invites']->pluck('invite.id')->all())->toBe([$invite->id])
        ->and($pipeline['quoted']->pluck('invite.id')->all())->toBe([$quoted->id])
        ->and($pipeline['booked']->pluck('invite.id')->all())->toBe([$won->id])
        ->and($pipeline['done']->pluck('invite.id')->all())->toBe([$finished->id])
        ->and($pipeline['done'][0]['note'])->toBe('Completed');
});

it('gives every past job a reason (spec 021, AC23)', function (): void {
    $expired = todayInvite($this->pro, ['expires_at' => now()->subHour()]);
    $declined = todayInvite($this->pro, ['status' => InviteStatus::Declined]);
    $withdrawn = todayInvite($this->pro, ['status' => InviteStatus::Quoted]);
    todayQuote($withdrawn, 'withdrawn');
    $lost = todayInvite($this->pro, ['status' => InviteStatus::Quoted]);
    $lostJob = $lost->serviceJob;
    todayQuote($lost, 'declined');
    $winner = Quote::factory()->create(['service_job_id' => $lostJob->id, 'status' => 'accepted']);
    $lostJob->forceFill(['accepted_quote_id' => $winner->id, 'status' => ServiceJobStatus::Scheduled])->save();
    $cancelled = todayInvite($this->pro, [], ServiceJob::factory()->open()->create());
    $cancelled->serviceJob->forceFill(['status' => ServiceJobStatus::Cancelled])->save();
    $neverQuoted = todayInvite($this->pro, ['status' => InviteStatus::Closed]);
    $other = Quote::factory()->create(['service_job_id' => $neverQuoted->service_job_id, 'status' => 'accepted']);
    $neverQuoted->serviceJob->forceFill(['accepted_quote_id' => $other->id, 'status' => ServiceJobStatus::Scheduled])->save();

    $notes = ProPipeline::for($this->pro)['done']->mapWithKeys(fn (array $row): array => [$row['invite']->id => $row['note']])->all();

    expect($notes)->toEqual([
        $expired->id => 'Expired', $declined->id => 'You declined', $withdrawn->id => 'You withdrew your quote',
        $lost->id => 'Quote not chosen', $cancelled->id => 'Cancelled', $neverQuoted->id => 'Closed',
    ]);
});

it('shows the four tabs with rows that all open their job, and the old tab names still work (spec 021, AC23)', function (): void {
    $done = todayInvite($this->pro, ['expires_at' => now()->subHour()]);
    $open = todayInvite($this->pro);

    Livewire::test(ProJobs::class)->assertSee('Invites')->assertSee('Quoted')->assertSee('Booked')->assertSee('Done')
        ->assertSee(route('pros.jobs.show', $open), false)->assertDontSee(route('pros.jobs.show', $done), false);
    Livewire::test(ProJobs::class)->set('tab', 'done')->assertSee(route('pros.jobs.show', $done), false)->assertSee('Expired');
    Livewire::test(ProJobs::class)->set('tab', 'past')->assertSee(route('pros.jobs.show', $done), false);
    Livewire::test(ProJobs::class)->set('tab', 'new')->assertSee(route('pros.jobs.show', $open), false);
    Livewire::test(ProJobs::class)->set('tab', 'nonsense')->assertSee(route('pros.jobs.show', $open), false);
});

it('shows a useful empty state on each tab (spec 021, AC23, AC30)', function (): void {
    Livewire::test(ProJobs::class)->assertSee('No new jobs right now');
    Livewire::test(ProJobs::class)->set('tab', 'quoted')->assertSee('Quotes you have sent wait here');
    Livewire::test(ProJobs::class)->set('tab', 'booked')->assertSee('Jobs you win will appear here');
    Livewire::test(ProJobs::class)->set('tab', 'done')->assertSee('No past jobs yet.');
});

it('opens a past job to an explanation instead of a dead link (spec 021, AC23)', function (): void {
    $done = todayInvite($this->pro, ['expires_at' => now()->subHour()]);

    $this->get(route('pros.jobs.show', $done))->assertOk()->assertSee('no longer available');
});

it('shows open invites as a badge on the Jobs tab (spec 021, AC2)', function (): void {
    todayInvite($this->pro);
    todayInvite($this->pro);
    todayInvite($this->pro, ['expires_at' => now()->subHour()]);

    $html = $this->get(route('pros.welcome'))->getContent();

    expect($html)->toMatch('/<span>Jobs<span class="ml-1[^>]*><span class="sr-only">Unread:\s*<\/span>2<\/span>/s');
});
