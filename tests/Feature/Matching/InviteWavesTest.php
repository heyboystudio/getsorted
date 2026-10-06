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
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use App\Settings\MatchingSettings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

function matchingMessages(): FakeMessagingChannel
{
    return app(MessagingChannel::class);
}

/**
 * Approved plumbers based within a few km of the job (spec 020).
 *
 * @return list<Pro>
 */
function eligiblePros(int $count, float $kmFromJob = 2): array
{
    return collect(range(1, $count))->map(fn (): Pro => proNear(['plumbing'], $kmFromJob))->all();
}

/** A customer posts a plumbing job in Musgrave through the real booking actions. */
function postLeakJob(?User $customer = null): ServiceJob
{
    $customer ??= User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['street_address' => '7 Private Lane']);
    $draft = app(SaveBookingDraft::class)->handle($customer, tradeOf('plumbing'), null, new BookingData(
        facts: [['id' => 'f1', 'text' => 'tap drips when closed', 'turn' => 1]],
        notes: 'Under the sink. Call me on 082 123 4567.', propertyPublicId: $property->public_id,
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

// --- Invites (spec 020) ---------------------------------------------------------------

it('invites up to ten eligible pros at once after posting, each with a WhatsApp message', function (): void {
    eligiblePros(12);

    $job = postLeakJob();

    $invites = ServiceJobInvite::query()->where('service_job_id', $job->id)->get();
    expect($invites)->toHaveCount(10)
        ->and($invites->pluck('status')->unique()->all())->toBe([InviteStatus::Invited])
        ->and($invites->pluck('wave')->unique()->all())->toBe([1])
        ->and($invites->first()->expires_at->toDateTimeString())->toBe($invites->first()->invited_at->addHours(24)->toDateTimeString())
        ->and($job->fresh()->last_wave_at)->not->toBeNull();

    matchingMessages()->assertSent('job_invite', times: 10);
    matchingMessages()->assertSent('job_invite', fn ($message): bool => $message->parameters['suburb'] === 'Musgrave'
        && ! str_contains(json_encode($message->parameters), '7 Private Lane') && ! str_contains(json_encode($message->parameters), '082'), times: 10);
});

it('uses the invite count and expiry from settings', function (): void {
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 2;
    $settings->invite_expiry_hours = 6;
    $settings->save();
    eligiblePros(4);

    $job = postLeakJob();

    expect($job->invites()->count())->toBe(2)
        ->and($job->invites()->first()->expires_at->toDateTimeString())->toBe(now()->addHours(6)->toDateTimeString());
});

it('rotates work: pros with the fewest recent invites go first', function (): void {
    [$busy, $quiet] = eligiblePros(2);
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 1;
    $settings->save();
    foreach (range(1, 3) as $i) {
        ServiceJobInvite::factory()->for($busy)->create(['invited_at' => now()->subDays(2)]);
    }
    ServiceJobInvite::factory()->for($quiet)->create(['invited_at' => now()->subDays(10)]);

    $job = postLeakJob();

    expect($job->invites()->sole()->pro_id)->toBe($quiet->id);
});

it('prefers the nearest pro when recent work is equal', function (): void {
    $far = proNear(['plumbing'], 9);
    $near = proNear(['plumbing'], 1);
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 1;
    $settings->save();

    $job = postLeakJob();

    expect($job->invites()->sole()->pro_id)->toBe($near->id)->and($far->id)->not->toBe($near->id);
});

it('only invites pros of the job trade', function (): void {
    $plumber = proNear(['plumbing'], 1);
    proNear(['electrical'], 1);

    $job = postLeakJob();

    expect($job->invites()->pluck('pro_id')->all())->toBe([$plumber->id]);
});

it('respects each pro radius with a two kilometre soft edge', function (): void {
    $inside = proNear(['plumbing'], 14);        // within its own 15 km
    $edge = proNear(['plumbing'], 16.5);        // 15–17 km: soft edge
    $outside = proNear(['plumbing'], 19);       // beyond 17 km
    $smallRadius = proNear(['plumbing'], 8, radiusKm: 5); // 8 km away but only travels 5 (+2)

    $job = postLeakJob();

    $invited = $job->invites()->pluck('pro_id')->all();
    expect($invited)->toContain($inside->id, $edge->id)->not->toContain($outside->id)->not->toContain($smallRadius->id);
});

it('uses soft-edge pros only to fill invites that nearer pros leave open', function (): void {
    $inside = proNear(['plumbing'], 5);
    proNear(['plumbing'], 16.5);
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 1;
    $settings->save();

    $job = postLeakJob();

    expect($job->invites()->sole()->pro_id)->toBe($inside->id);
});

it('tops up missing invites later without inviting anyone twice', function (): void {
    eligiblePros(3);
    $job = postLeakJob();
    expect($job->invites()->count())->toBe(3);

    eligiblePros(4);
    $this->travel(31)->minutes();
    app(RunMatchingSchedule::class)->handle();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(7)->and($job->invites()->distinct()->count('pro_id'))->toBe(7)
        ->and($job->invites()->max('wave'))->toBe(1);
});

it('stops topping up once the job is older than the invite window', function (): void {
    eligiblePros(2);
    $job = postLeakJob();
    eligiblePros(3);

    $this->travel(25)->hours();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(2);
});

it('expires unanswered invites after 24 hours, and running twice changes nothing more', function (): void {
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

it('closes open invites and stops inviting when a job is no longer open', function (): void {
    eligiblePros(3);
    $job = postLeakJob();
    DB::table('service_jobs')->where('id', $job->id)->update(['status' => ServiceJobStatus::Cancelled->value]);
    eligiblePros(2);

    $this->travel(31)->minutes();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(3)
        ->and($job->invites()->pluck('status')->unique()->all())->toBe([InviteStatus::Closed]);
});

it('re-checks eligibility at invite time', function (): void {
    eligiblePros(2);
    $job = postLeakJob();
    $later = eligiblePros(1)[0];
    $later->forceFill(['status' => 'suspended'])->save();

    $this->travel(31)->minutes();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(2);
});

it('stops inviting once the job has all the quotes it accepts', function (): void {
    eligiblePros(2);
    $job = postLeakJob();
    $job->forceFill(['quotes_count' => 5])->save();
    eligiblePros(3);

    $this->travel(31)->minutes();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(2);
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
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 5;
    $settings->save();
    eligiblePros(6);
    $job = postLeakJob();
    $extra = Pro::query()->whereNotIn('id', $job->invites()->pluck('pro_id'))->sole();
    $ineligible = Pro::factory()->approved()->create();
    $ineligible->trades()->attach(tradeOf('painting'));

    expect(fn () => app(InviteProManually::class)->handle(matchingAdmin(Role::AdminFinance), $job, $extra))->toThrow(AuthorizationException::class)
        ->and(fn () => app(InviteProManually::class)->handle(matchingAdmin(), $job, $ineligible))->toThrow(CannotInvite::class)
        ->and(fn () => app(InviteProManually::class)->handle(matchingAdmin(), $job, $job->invites()->with('pro')->first()->pro))->toThrow(CannotInvite::class);

    $admin = matchingAdmin(Role::AdminSuper);
    $invite = app(InviteProManually::class)->handle($admin, $job, $extra);

    expect($invite->invited_by)->toBe($admin->id)->and($invite->status)->toBe(InviteStatus::Invited)
        ->and(DB::table('activity_log')->where('description', 'job_pro_invited')->count())->toBe(1);
    matchingMessages()->assertSent('job_invite', times: 6);
});

it('stops matching with a reason, so no further invites go out (AC11)', function (): void {
    eligiblePros(3);
    $job = postLeakJob();
    eligiblePros(3);

    expect(fn () => app(StopMatching::class)->handle(matchingAdmin(), $job, ''))->toThrow(ValidationException::class);
    app(StopMatching::class)->handle(matchingAdmin(), $job, 'Customer asked us to pause.');

    $this->travel(31)->minutes();
    app(RunMatchingSchedule::class)->handle();

    expect($job->fresh()->matching_stopped_at)->not->toBeNull()
        ->and($job->invites()->count())->toBe(3)
        ->and(DB::table('activity_log')->where('description', 'job_matching_stopped')->count())->toBe(1);
});

it('schedules matching every five minutes (rules)', function (): void {
    $events = collect(app(Schedule::class)->events());
    $matching = $events->first(fn ($event): bool => str_contains((string) $event->command, 'sortd:run-matching'));

    expect($matching)->not->toBeNull()->and($matching->expression)->toBe('*/5 * * * *');
});
