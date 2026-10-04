<?php

declare(strict_types=1);

use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\Actions\DeclineInvite;
use App\Domain\Matching\Actions\InviteProManually;
use App\Domain\Matching\Actions\OpenInvite;
use App\Domain\Matching\Actions\RunMatchingSchedule;
use App\Domain\Matching\Actions\StopMatching;
use App\Domain\Matching\Enums\DeclineReason;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\Suburb;
use App\Models\User;
use App\Settings\MatchingSettings;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
    $this->musgrave = Suburb::query()->where('slug', 'musgrave')->sole();
});

function matchingMessages(): FakeMessagingChannel
{
    return app(MessagingChannel::class);
}

/** Approved pros offering leak repair in Musgrave. */
function eligiblePros(int $count): array
{
    return collect(range(1, $count))->map(function (): Pro {
        $pro = Pro::factory()->approved()->create();
        $pro->services()->attach(test()->leak);
        $pro->serviceAreas()->attach(test()->musgrave);

        return $pro;
    })->all();
}

/** A customer posts a leak repair job in Musgrave through the real booking actions. */
function postLeakJob(?User $customer = null): ServiceJob
{
    $customer ??= User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['suburb_id' => test()->musgrave->id, 'street_address' => '7 Private Lane']);
    $answers = [];
    foreach (test()->leak->questions as $question) {
        $answers[$question->key] = ['prompt' => $question->prompt, 'type' => $question->type->value, 'answer' => $question->options[0]];
    }
    $draft = app(SaveBookingDraft::class)->handle($customer, test()->leak, null, new BookingData(
        answers: $answers, notes: 'Under the sink. Call me on 082 123 4567.', propertyPublicId: $property->public_id,
        preferredDate: now()->toImmutable()->addDays(2), timeWindow: TimeWindow::Morning,
    ));

    return app(PostServiceJob::class)->handle($customer, $draft);
}

function matchingAdmin(Role $role = Role::AdminSupport): User
{
    $admin = User::factory()->create();
    $admin->assignRole($role->value);

    return $admin;
}

// --- Waves (AC1–AC6) ------------------------------------------------------------------

it('invites the first wave of five eligible pros after posting, each with a WhatsApp message (AC1)', function (): void {
    $pros = eligiblePros(7);

    $job = postLeakJob();

    $invites = ServiceJobInvite::query()->where('service_job_id', $job->id)->get();
    expect($invites)->toHaveCount(5)
        ->and($invites->pluck('status')->unique()->all())->toBe([InviteStatus::Invited])
        ->and($invites->pluck('wave')->unique()->all())->toBe([1])
        ->and($invites->first()->expires_at->toDateTimeString())->toBe($invites->first()->invited_at->addHours(24)->toDateTimeString())
        ->and($job->fresh()->last_wave_at)->not->toBeNull();

    matchingMessages()->assertSent('job_invite', times: 5);
    matchingMessages()->assertSent('job_invite', fn ($message): bool => $message->parameters['suburb'] === 'Musgrave'
        && ! str_contains(json_encode($message->parameters), '7 Private Lane') && ! str_contains(json_encode($message->parameters), '082'), times: 5);
});

it('uses the wave sizes and timers from settings (AC1, AC12)', function (): void {
    $settings = app(MatchingSettings::class);
    $settings->wave_one_size = 2;
    $settings->invite_expiry_hours = 6;
    $settings->save();
    eligiblePros(4);

    $job = postLeakJob();

    expect($job->invites()->count())->toBe(2)
        ->and($job->invites()->first()->expires_at->toDateTimeString())->toBe(now()->addHours(6)->toDateTimeString());
});

it('rotates work: pros with the fewest recent invites go first (AC2, decision 2)', function (): void {
    [$busy, $quiet] = eligiblePros(2);
    $settings = app(MatchingSettings::class);
    $settings->wave_one_size = 1;
    $settings->save();
    foreach (range(1, 3) as $i) {
        ServiceJobInvite::factory()->for($busy)->create(['invited_at' => now()->subDays(2)]);
    }
    ServiceJobInvite::factory()->for($quiet)->create(['invited_at' => now()->subDays(10)]);

    $job = postLeakJob();

    expect($job->invites()->sole()->pro_id)->toBe($quiet->id);
});

it('sends a later wave of three after 12 hours, never inviting anyone twice (AC3)', function (): void {
    eligiblePros(10);
    $job = postLeakJob();

    $this->travel(11)->hours();
    app(RunMatchingSchedule::class)->handle();
    expect($job->invites()->count())->toBe(5);

    $this->travel(2)->hours();
    app(RunMatchingSchedule::class)->handle();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(8)
        ->and($job->invites()->where('wave', 2)->count())->toBe(3)
        ->and($job->invites()->distinct()->count('pro_id'))->toBe(8);

    $this->travel(13)->hours();
    app(RunMatchingSchedule::class)->handle();
    $this->travel(13)->hours();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(10)->and($job->invites()->max('wave'))->toBe(3);
});

it('expires unanswered invites after 24 hours, and running twice changes nothing more (AC4)', function (): void {
    eligiblePros(2);
    $job = postLeakJob();
    $declined = $job->invites()->with('pro.user')->first();
    app(DeclineInvite::class)->handle($declined->pro->user, $declined, DeclineReason::TooBusy, null);

    $this->travel(25)->hours();
    app(RunMatchingSchedule::class)->handle();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->where('status', InviteStatus::Expired)->count())->toBe(1)
        ->and($declined->fresh()->status)->toBe(InviteStatus::Declined);
});

it('closes open invites and stops waves when a job is no longer open (AC5)', function (): void {
    eligiblePros(8);
    $job = postLeakJob();
    DB::table('service_jobs')->where('id', $job->id)->update(['status' => ServiceJobStatus::Cancelled->value]);

    $this->travel(13)->hours();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(5)
        ->and($job->invites()->pluck('status')->unique()->all())->toBe([InviteStatus::Closed]);
});

it('re-checks eligibility at invite time (AC6)', function (): void {
    $pros = eligiblePros(6);
    $job = postLeakJob();
    $left = Pro::query()->whereNotIn('id', $job->invites()->pluck('pro_id'))->sole();
    $left->forceFill(['status' => 'suspended'])->save();

    $this->travel(13)->hours();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(5);
});

it('stops later waves once enough quotes are in (AC3)', function (): void {
    eligiblePros(8);
    $job = postLeakJob();
    $job->invites()->limit(2)->get()->each(fn (ServiceJobInvite $invite) => $invite->forceFill(['status' => InviteStatus::Quoted])->save());

    $this->travel(13)->hours();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(5);
});

// --- Pro actions (AC8, AC9) --------------------------------------------------------------

it('marks an invite viewed once, and only for its own pro (AC8, AC10)', function (): void {
    eligiblePros(2);
    $job = postLeakJob();
    [$mine, $theirs] = $job->invites()->with('pro.user')->get()->all();

    app(OpenInvite::class)->handle($mine->pro->user, $mine);
    $first = $mine->fresh()->viewed_at;
    $this->travel(1)->hour();
    app(OpenInvite::class)->handle($mine->pro->user, $mine->fresh());

    expect($mine->fresh()->status)->toBe(InviteStatus::Viewed)
        ->and($mine->fresh()->viewed_at->equalTo($first))->toBeTrue()
        ->and(fn () => app(OpenInvite::class)->handle($mine->pro->user, $theirs))->toThrow(AuthorizationException::class);
});

it('declines with a reason, needs text for "other", and cannot be reopened (AC9)', function (): void {
    eligiblePros(1);
    $invite = postLeakJob()->invites()->with('pro.user')->sole();
    $user = $invite->pro->user;

    expect(fn () => app(DeclineInvite::class)->handle($user, $invite, DeclineReason::Other, ' '))->toThrow(ValidationException::class);

    app(DeclineInvite::class)->handle($user, $invite, DeclineReason::TooFar, null);

    expect($invite->fresh())->status->toBe(InviteStatus::Declined)->decline_reason->toBe(DeclineReason::TooFar)
        ->and($invite->fresh()->responded_at)->not->toBeNull()
        ->and(fn () => app(DeclineInvite::class)->handle($user, $invite->fresh(), DeclineReason::TooBusy, null))->toThrow(CannotInvite::class)
        ->and(fn () => app(OpenInvite::class)->handle($user, $invite->fresh()))->toThrow(CannotInvite::class);
});

it('treats an expired invite as no longer available (AC4, screens)', function (): void {
    eligiblePros(1);
    $invite = postLeakJob()->invites()->with('pro.user')->sole();
    $this->travel(25)->hours();

    expect(fn () => app(OpenInvite::class)->handle($invite->pro->user, $invite))->toThrow(CannotInvite::class);
});

// --- Admin (AC11) ---------------------------------------------------------------------

it('lets support and super admins invite an eligible, not-yet-invited pro by hand (AC11)', function (): void {
    eligiblePros(6);
    $job = postLeakJob();
    $extra = Pro::query()->whereNotIn('id', $job->invites()->pluck('pro_id'))->sole();
    $ineligible = Pro::factory()->approved()->create();

    expect(fn () => app(InviteProManually::class)->handle(matchingAdmin(Role::AdminFinance), $job, $extra))->toThrow(AuthorizationException::class)
        ->and(fn () => app(InviteProManually::class)->handle(matchingAdmin(), $job, $ineligible))->toThrow(CannotInvite::class)
        ->and(fn () => app(InviteProManually::class)->handle(matchingAdmin(), $job, $job->invites()->with('pro')->first()->pro))->toThrow(CannotInvite::class);

    $admin = matchingAdmin(Role::AdminSuper);
    $invite = app(InviteProManually::class)->handle($admin, $job, $extra);

    expect($invite->invited_by)->toBe($admin->id)->and($invite->status)->toBe(InviteStatus::Invited)
        ->and(DB::table('activity_log')->where('description', 'job_pro_invited')->count())->toBe(1);
    matchingMessages()->assertSent('job_invite', times: 6);
});

it('stops matching with a reason, so no further waves run (AC11)', function (): void {
    eligiblePros(8);
    $job = postLeakJob();

    expect(fn () => app(StopMatching::class)->handle(matchingAdmin(), $job, ''))->toThrow(ValidationException::class);
    app(StopMatching::class)->handle(matchingAdmin(), $job, 'Customer asked us to pause.');

    $this->travel(13)->hours();
    app(RunMatchingSchedule::class)->handle();

    expect($job->fresh()->matching_stopped_at)->not->toBeNull()
        ->and($job->invites()->count())->toBe(5)
        ->and(DB::table('activity_log')->where('description', 'job_matching_stopped')->count())->toBe(1);
});

it('schedules matching every five minutes (rules)', function (): void {
    $events = collect(app(Schedule::class)->events());
    $matching = $events->first(fn ($event): bool => str_contains((string) $event->command, 'sortd:run-matching'));

    expect($matching)->not->toBeNull()->and($matching->expression)->toBe('*/5 * * * *');
});
