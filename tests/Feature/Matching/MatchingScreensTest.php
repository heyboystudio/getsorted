<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Actions\StoreJobPhoto;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Filament\Admin\Pages\MatchingSettingsPage;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Filament\Admin\Resources\ServiceJobs\RelationManagers\InvitesRelationManager;
use App\Livewire\Account\Jobs\Show as CustomerJob;
use App\Livewire\Pros\Jobs\Index as ProJobs;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Models\Pro;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\User;
use App\Settings\MatchingSettings;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    Storage::fake('media');
});

function screenPro(): Pro
{
    $pro = proNear(['plumbing'], 2);
    $pro->forceFill(['business_name' => 'Dlamini Plumbing'])->save();

    return $pro;
}

/** Posts a job with a photo and customer details pros must never see. */
function screenJob(): ServiceJob
{
    $customer = User::factory()->customer()->create(['first_name' => 'Nomvula', 'last_name' => 'Secretname', 'phone_e164' => '+27829990000']);
    $property = Property::factory()->for($customer)->create(['street_address' => '7 Private Lane']);
    $draft = app(SaveBookingDraft::class)->handle($customer, tradeOf('plumbing'), null, new BookingData(
        facts: [['id' => 'f1', 'text' => 'tap drips when fully closed', 'turn' => 1]], notes: 'Behind the fridge. WhatsApp me on 082 123 4567 or mail me@example.com, 7 Private Lane.',
        propertyPublicId: $property->public_id, preferredDate: now()->toImmutable()->addDays(2), timeWindow: TimeWindow::Morning,
    ));
    app(StoreJobPhoto::class)->handle($customer, $draft, UploadedFile::fake()->image('leak.jpg', 40, 30));
    $draft->forceFill(['ai_summary' => 'Leaking tap. Call 071 222 3333.', 'ai_summary_source' => 'customer_edited'])->save();

    return app(PostServiceJob::class)->handle($customer, $draft);
}

// --- Pro screens (AC7–AC10) ------------------------------------------------------------

it('lists a pro\'s new and past invites (AC7)', function (): void {
    $pro = screenPro();
    $job = screenJob();
    $this->actingAs($pro->user);

    Livewire::test(ProJobs::class)->assertSee('Plumbing')->assertSee('tap drips when fully closed')->assertSee('Musgrave')->assertSee('km away')->assertSee('left')
        ->assertDontSee('No new jobs right now');

    $job->invites()->update(['status' => InviteStatus::Expired->value]);
    Livewire::test(ProJobs::class)->assertSee('No new jobs right now')
        ->set('tab', 'past')->assertSee('Plumbing')->assertSee('Expired');
});

it('sends pros who are not approved to their application instead (AC7)', function (): void {
    $pro = Pro::factory()->create();
    $this->actingAs($pro->user)->get(route('pros.jobs'))->assertRedirect(route('pros.status'));
    $this->actingAs(User::factory()->customer()->create())->get(route('pros.jobs'))->assertRedirect(route('pros.join'));
});

it('shows the job without the customer\'s identity, address or contact details (AC8, decision 3)', function (): void {
    $pro = screenPro();
    $job = screenJob();
    $job->forceFill(['facts' => [['id' => 'f1', 'text' => 'leak behind the fridge. Call 082 123 4567.', 'turn' => 1]]])->save();
    $invite = $job->invites()->sole();
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => $invite])
        ->assertSee('Plumbing')->assertSee('Musgrave')->assertSee('What the customer reported')
        ->assertSee('leak behind the fridge.')->assertSee('Leaking tap.')
        ->assertSee('1 pro invited')->assertSee('0 quotes in')->assertSee('about 2 km away')
        ->assertSee('Accept job') // The pro accepts first; the estimate form opens after that.
        ->assertDontSee('Nomvula')->assertDontSee('Secretname')->assertDontSee('7 Private Lane')
        ->assertDontSee('082 123 4567')->assertDontSee('me@example.com')->assertDontSee('071 222 3333')->assertDontSee('829990000');

    expect($invite->fresh()->status)->toBe(InviteStatus::Viewed);
});

it('shows job photos to the invited pro only, through a signed link (AC8, AC10)', function (): void {
    $pro = screenPro();
    $job = screenJob();
    $invite = $job->invites()->sole();
    $photo = $job->getMedia(ServiceJob::PHOTO_COLLECTION)->sole();
    $url = $invite->photoUrl($photo);

    $this->actingAs($pro->user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');

    $other = Pro::factory()->approved()->create();
    $this->actingAs($other->user)->get($url)->assertNotFound();
    $this->actingAs($pro->user)->get(route('job-photos.show', ['job' => $job, 'photo' => $photo->uuid]))->assertForbidden();

    $invite->forceFill(['status' => InviteStatus::Declined])->save();
    $this->actingAs($pro->user)->get($invite->photoUrl($photo))->assertNotFound();

    $invite->forceFill(['status' => InviteStatus::Invited])->save();
    $pro->forceFill(['status' => ProStatus::Suspended])->save();
    $this->actingAs($pro->user)->get($url)->assertNotFound();
});

it('declines from the invite page with a reason (AC9)', function (): void {
    $pro = screenPro();
    $invite = screenJob()->invites()->with('pro.user')->sole();
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => $invite])
        ->set('reason', 'other')->set('note', '')->call('decline')->assertHasErrors(['note'])
        ->set('reason', 'too_busy')->call('decline')->assertHasNoErrors()->assertRedirect(route('pros.jobs'));

    expect($invite->fresh()->status)->toBe(InviteStatus::Declined);
});

it('keeps pros out of invites that are not theirs, and says when a job is gone (AC10, screens)', function (): void {
    screenPro();
    $invite = screenJob()->invites()->with('pro.user')->sole();
    $other = Pro::factory()->approved()->create();

    $this->actingAs($other->user)->get(route('pros.jobs.show', $invite))->assertNotFound();

    $invite->forceFill(['status' => InviteStatus::Closed])->save();
    $this->actingAs($invite->pro->user);
    Livewire::test(ProJob::class, ['invite' => $invite])->assertSee('This job is no longer available')->assertDontSee('Behind the fridge');
});

it('links an approved pro from the welcome page to their jobs (screens)', function (): void {
    $pro = screenPro();

    $this->actingAs($pro->user)->get(route('pros.welcome'))->assertSee(route('pros.jobs'), false);
});

// --- Admin (AC11, AC12) ------------------------------------------------------------------

it('shows admins each job\'s invites (AC11)', function (): void {
    screenPro();
    $job = screenJob();
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(InvitesRelationManager::class, ['ownerRecord' => $job, 'pageClass' => ViewServiceJob::class])
        ->assertCanSeeTableRecords($job->invites)->assertSee('Dlamini Plumbing')->assertSee('Invited');
});

it('lets support admins invite a pro and stop matching from the job view (AC11)', function (): void {
    screenPro();
    $job = screenJob();
    $extra = screenPro();
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])
        ->callAction('invitePro', data: ['pro_id' => $extra->id])->assertHasNoActionErrors();
    expect($job->invites()->count())->toBe(2);

    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])
        ->callAction('stopMatching', data: ['reason' => 'Paused at the customer\'s request.'])->assertHasNoActionErrors();
    expect($job->fresh()->matching_stopped_at)->not->toBeNull();

    $finance = User::factory()->create();
    $finance->assignRole(Role::AdminFinance->value);
    $this->actingAs($finance);
    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])->assertActionHidden('invitePro')->assertActionHidden('stopMatching');
});

it('lets only super admins change the matching settings (AC12)', function (): void {
    $support = User::factory()->create();
    $support->assignRole(Role::AdminSupport->value);
    $this->actingAs($support);
    Filament::setCurrentPanel('admin');
    expect(MatchingSettingsPage::canAccess())->toBeFalse();

    $super = User::factory()->create();
    $super->assignRole(Role::AdminSuper->value);
    $this->actingAs($super);

    Livewire::test(MatchingSettingsPage::class)
        ->fillForm(['invite_count' => 8, 'max_quotes' => 4, 'default_radius_km' => 20, 'soft_edge_km' => 3, 'invite_expiry_hours' => 12])
        ->call('save')->assertHasNoFormErrors();

    $settings = app(MatchingSettings::class)->refresh();
    expect([$settings->invite_count, $settings->max_quotes, $settings->default_radius_km, $settings->soft_edge_km, $settings->invite_expiry_hours])->toBe([8, 4, 20, 3, 12]);

    Livewire::test(MatchingSettingsPage::class)
        ->fillForm(['invite_count' => 0, 'max_quotes' => 0, 'default_radius_km' => 0, 'soft_edge_km' => -1, 'invite_expiry_hours' => 0])
        ->call('save')->assertHasFormErrors(['invite_count', 'max_quotes', 'default_radius_km', 'soft_edge_km', 'invite_expiry_hours']);
});

// --- Customer (AC13) ---------------------------------------------------------------------

it('tells the customer how many pros were invited, without names (AC13)', function (): void {
    screenPro();
    screenPro();
    $job = screenJob();
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])->assertSee('Finding your pros')->assertSee('2 pros invited')->assertDontSee('Dlamini Plumbing');
});

it('tells the customer when no pro could be invited yet (AC13, rules)', function (): void {
    $job = ServiceJob::factory()->open()->create(['trade_id' => tradeOf('plumbing')->id]);
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])->assertSee("We're still looking");
});
