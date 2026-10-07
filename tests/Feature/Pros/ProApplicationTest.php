<?php

declare(strict_types=1);

use App\Contracts\Data\GeocodedAddress;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Pros\Actions\ChangeProStanding;
use App\Domain\Pros\Actions\CheckReference;
use App\Domain\Pros\Actions\DecideApplication;
use App\Domain\Pros\Actions\EditProCoverage;
use App\Domain\Pros\Actions\PruneVettingRecords;
use App\Domain\Pros\Actions\SaveApplicationStep;
use App\Domain\Pros\Actions\StartApplication;
use App\Domain\Pros\Actions\StoreProDocument;
use App\Domain\Pros\Actions\SubmitApplication;
use App\Domain\Pros\Actions\VetDocument;
use App\Domain\Pros\Data\BusinessDetails;
use App\Domain\Pros\Data\ReferenceData;
use App\Domain\Pros\Enums\BusinessType;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Enums\ReferenceOutcome;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Models\Pro;
use App\Models\ProEvent;
use App\Models\Trade;
use App\Models\User;
use App\Settings\VettingSettings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    Storage::fake('media');
});

function proMessaging(): FakeMessagingChannel
{
    return app(MessagingChannel::class);
}

function applicant(): User
{
    return User::factory()->pro()->create();
}

function vetter(Role $role = Role::AdminVetting): User
{
    $admin = User::factory()->create();
    $admin->assignRole($role->value);

    return $admin;
}

/** A complete application, ready to submit. */
function completeApplication(?User $user = null, array $tradeKeys = ['plumbing']): Pro
{
    $user ??= applicant();
    $pro = app(StartApplication::class)->handle($user);
    $steps = app(SaveApplicationStep::class);
    $steps->business($user, $pro, new BusinessDetails('Dlamini Plumbing', BusinessType::SoleTrader, null));
    $steps->trades($user, $pro, Trade::query()->whereIn('key', $tradeKeys)->pluck('id')->all());
    $steps->base($user, $pro, new GeocodedAddress('10 Musgrave Road, Berea, Durban', 'Berea', -29.8460, 31.0050, '10 Musgrave Road', '4001', ['Berea']), 'fake-berea', 15);
    $steps->references($user, $pro, [
        new ReferenceData('Thandi Mkhize', '082 123 4567', 'Past customer'),
        new ReferenceData('Sipho Ndlovu', '071 234 5678', 'Supplier'),
    ], refereesAgreed: true);
    $steps->bio($user, $pro, 'Fifteen years fixing leaks in Durban.');
    $steps->consent($user, $pro, true);

    foreach ([DocumentType::IdDocument, DocumentType::ProofOfAddress, DocumentType::ProfilePhoto] as $type) {
        app(StoreProDocument::class)->handle($user, $pro, $type, UploadedFile::fake()->image($type->value.'.jpg', 40, 30));
    }

    return $pro->fresh();
}

function submitted(?User $user = null, array $tradeKeys = ['plumbing']): Pro
{
    $pro = completeApplication($user, $tradeKeys);
    app(SubmitApplication::class)->handle($pro->user, $pro);

    return $pro->fresh();
}

/** Verifies everything an approval needs. */
function vetEverything(Pro $pro, User $admin): void
{
    foreach ($pro->documents as $document) {
        app(VetDocument::class)->verify($admin, $document, $document->type->hasExpiry() ? now()->addYear()->toImmutable() : null);
    }

    foreach ($pro->references as $reference) {
        app(CheckReference::class)->handle($admin, $reference, ReferenceOutcome::Positive, 'Happy with the work.');
    }
}

// --- Application (AC1–AC6) ----------------------------------------------------------

it('starts one draft application per pro and saves each step (AC1)', function (): void {
    $user = applicant();

    $pro = app(StartApplication::class)->handle($user);
    expect(app(StartApplication::class)->handle($user)->id)->toBe($pro->id)
        ->and($pro->status)->toBe(ProStatus::Draft);

    $pro = completeApplication($user);
    expect($pro->business_name)->toBe('Dlamini Plumbing')
        ->and($pro->business_type)->toBe(BusinessType::SoleTrader)
        ->and($pro->trades->pluck('key')->all())->toBe(['plumbing'])
        ->and($pro->base_area_label)->toBe('Berea')->and($pro->service_radius_km)->toBe(15)
        ->and($pro->base_location->getLatitude())->toEqualWithDelta(-29.846, 0.0001)
        ->and($pro->references)->toHaveCount(2)
        ->and($pro->references->first()->phone_e164)->toBe('+27821234567')
        ->and($pro->documents)->toHaveCount(3)
        ->and($pro->vetting_consent_at)->not->toBeNull();
});

it('only lets pros apply, and only for active trades, a South African address and a sensible radius (AC1, spec 020)', function (): void {
    expect(fn () => app(StartApplication::class)->handle(User::factory()->customer()->create()))->toThrow(AuthorizationException::class);

    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    $inactive = tradeOf('tiling');
    $inactive->update(['is_active' => false]);
    $steps = app(SaveApplicationStep::class);
    $address = fn (float $lat, float $lng): GeocodedAddress => new GeocodedAddress('1 Road', 'Somewhere', $lat, $lng, areaNames: ['Somewhere']);

    expect(fn () => $steps->trades($user, $pro, [$inactive->id]))->toThrow(ValidationException::class)
        ->and(fn () => $steps->trades($user, $pro, []))->toThrow(ValidationException::class)
        ->and(fn () => $steps->base($user, $pro, $address(51.5, -0.12), 'place', 15))->toThrow(ValidationException::class)
        ->and(fn () => $steps->base($user, $pro, $address(-29.85, 31.02), 'place', 0))->toThrow(ValidationException::class)
        ->and(fn () => $steps->base($user, $pro, $address(-29.85, 31.02), 'place', 51))->toThrow(ValidationException::class);

    $steps->base($user, $pro, $address(-29.85, 31.02), 'place', 25);
    expect($pro->fresh())->service_radius_km->toBe(25)->base_area_label->toBe('Somewhere')
        ->and(DB::table('pros')->where('id', $pro->id)->value('base_address'))->not->toContain('1 Road');
});

it('accepts images and PDFs only, re-encodes images and keeps every file private (AC2)', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    $store = app(StoreProDocument::class);

    $photo = $store->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('id.jpg', 40, 30));
    $pdf = $store->handle($user, $pro, DocumentType::ProofOfAddress, UploadedFile::fake()->createWithContent('bill.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));

    expect($photo->file()->mime_type)->toBe('image/webp')->and($photo->file()->disk)->toBe('media')
        ->and($pdf->file()->mime_type)->toBe('application/pdf');

    expect(fn () => $store->handle($user, $pro, DocumentType::ProfilePhoto, UploadedFile::fake()->createWithContent('photo.jpg', 'not an image')))->toThrow(ValidationException::class)
        ->and(fn () => $store->handle($user, $pro, DocumentType::ProfilePhoto, UploadedFile::fake()->create('notes.txt', 1, 'text/plain')))->toThrow(ValidationException::class)
        ->and(fn () => $store->handle($user, $pro, DocumentType::ProfilePhoto, UploadedFile::fake()->createWithContent('fake.pdf', 'not really a pdf')))->toThrow(ValidationException::class)
        ->and(fn () => $store->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('big.jpg')->size(10_241)))->toThrow(ValidationException::class);
});

it('replaces a document of the same type instead of adding another (AC2)', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);

    app(StoreProDocument::class)->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('a.jpg', 40, 30));
    app(StoreProDocument::class)->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('b.jpg', 50, 30));

    expect($pro->documents()->where('type', DocumentType::IdDocument)->count())->toBe(1)
        ->and(DB::table('media')->count())->toBe(1);
});

it('offers a registration only for trades that have one, and stores the number encrypted (AC3, AC13, spec 020)', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    app(SaveApplicationStep::class)->trades($user, $pro, [tradeOf('painting')->id, tradeOf('electrical')->id]);

    expect($pro->fresh()->load('trades')->offeredRegistrations())->toBe([DocumentType::ElectricalRegisteredPerson]);

    app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-123456');
    $document = $pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->sole();

    expect($document->number)->toBe('ER-123456')
        ->and(DB::table('pro_documents')->where('id', $document->id)->value('number'))->not->toContain('ER-123456');
    expect(fn () => app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::Pirb, 'PIRB-1'))->toThrow(NotFoundHttpException::class);
});

it('submits a complete application once, then locks it (AC4)', function (): void {
    $pro = completeApplication();

    app(SubmitApplication::class)->handle($pro->user, $pro);
    expect(fn () => app(SubmitApplication::class)->handle($pro->user, $pro->fresh()))->toThrow(CannotChangeApplication::class);

    $pro->refresh();
    expect($pro->status)->toBe(ProStatus::Submitted)
        ->and($pro->submitted_at)->not->toBeNull()
        ->and(ProEvent::query()->where('pro_id', $pro->id)->sole()->to_status)->toBe(ProStatus::Submitted)
        ->and(fn () => app(SaveApplicationStep::class)->bio($pro->user, $pro, 'Changed'))->toThrow(AuthorizationException::class);
});

it('refuses to submit until every required item is there (AC4)', function (string $missing): void {
    $pro = completeApplication();

    match ($missing) {
        'id document' => $pro->documents()->where('type', DocumentType::IdDocument)->delete(),
        'proof of address' => $pro->documents()->where('type', DocumentType::ProofOfAddress)->delete(),
        'profile photo' => $pro->documents()->where('type', DocumentType::ProfilePhoto)->delete(),
        'references' => $pro->references()->limit(1)->delete(),
        'bio' => $pro->update(['bio' => null]),
        'consent' => $pro->forceFill(['vetting_consent_at' => null])->save(),
        'base address' => $pro->forceFill(['base_location' => null])->save(),
        'trades' => $pro->trades()->detach(),
    };

    expect(fn () => app(SubmitApplication::class)->handle($pro->user, $pro->fresh()))->toThrow(ValidationException::class);
    expect($pro->fresh()->status)->toBe(ProStatus::Draft);
})->with(['id document', 'proof of address', 'profile photo', 'references', 'bio', 'consent', 'base address', 'trades']);

it('needs the referees\' agreement and two different South African mobile numbers (AC13)', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    $steps = app(SaveApplicationStep::class);
    $refs = [new ReferenceData('A Person', '082 123 4567', 'Customer'), new ReferenceData('B Person', '071 234 5678', 'Supplier')];

    expect(fn () => $steps->references($user, $pro, $refs, refereesAgreed: false))->toThrow(ValidationException::class)
        ->and(fn () => $steps->references($user, $pro, [$refs[0], $refs[0]], refereesAgreed: true))->toThrow(ValidationException::class)
        ->and(fn () => $steps->references($user, $pro, [$refs[0], new ReferenceData('B', '12345', 'x')], refereesAgreed: true))->toThrow(ValidationException::class)
        ->and(fn () => $steps->references($user, $pro, [$refs[0], new ReferenceData('Me', $user->phone_e164, 'Myself')], refereesAgreed: true))->toThrow(ValidationException::class);

    $steps->references($user, $pro, $refs, refereesAgreed: true);
    expect(DB::table('pro_references')->where('pro_id', $pro->id)->pluck('phone_e164')->implode(' '))->not->toContain('821234567');
});

it('reopens only flagged items when changes are requested, then accepts a resubmission (AC5, AC6)', function (): void {
    $pro = submitted();
    $admin = vetter();
    $id = $pro->documents()->where('type', DocumentType::IdDocument)->sole();

    app(VetDocument::class)->flag($admin, $id, 'The photo is blurred.');
    app(DecideApplication::class)->requestChanges($admin, $pro->fresh(), 'Please upload a clearer ID.');
    $pro->refresh();

    expect($pro->status)->toBe(ProStatus::ChangesRequested)
        ->and($pro->decision_reason)->toBe('Please upload a clearer ID.')
        ->and(fn () => app(SaveApplicationStep::class)->bio($pro->user, $pro, 'New bio'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(StoreProDocument::class)->handle($pro->user, $pro, DocumentType::ProofOfAddress, UploadedFile::fake()->image('x.jpg', 20, 20)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SubmitApplication::class)->handle($pro->user, $pro))->toThrow(ValidationException::class);

    app(StoreProDocument::class)->handle($pro->user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('clear.jpg', 60, 40));
    $replaced = $pro->documents()->where('type', DocumentType::IdDocument)->sole();
    // The message stays until resubmission so the pro can still fix a wrong upload (code review).
    expect($replaced->status)->toBe(DocumentStatus::Pending)->and($replaced->flag_message)->toBe('The photo is blurred.');

    app(SubmitApplication::class)->handle($pro->user, $pro->fresh());
    expect($pro->fresh()->status)->toBe(ProStatus::Submitted)
        ->and($replaced->fresh()->flag_message)->toBeNull();
});

// --- Vetting (AC7–AC11) -------------------------------------------------------------

it('approves a fully checked application, records who did it, messages the pro and starts coverage (AC9)', function (): void {
    $pro = submitted();
    $admin = vetter();
    expect(app(EligibleProsQuery::class)->exists(tradeOf('plumbing'), durban()))->toBeFalse();

    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    $pro->refresh();
    expect($pro->status)->toBe(ProStatus::Approved)
        ->and($pro->approved_at)->not->toBeNull()
        ->and($pro->decided_by)->toBe($admin->id)
        ->and(app(EligibleProsQuery::class)->exists(tradeOf('plumbing'), durban()))->toBeTrue();

    proMessaging()->assertSent('pro_approved', fn ($message): bool => $message->phoneE164 === $pro->user->phone_e164);
    expect(ProEvent::query()->where('pro_id', $pro->id)->latest('id')->first())
        ->to_status->toBe(ProStatus::Approved)->actor_id->toBe($admin->id);
});

it('will not approve until ID, address and both references check out (AC9)', function (string $gap): void {
    $pro = submitted();
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);

    match ($gap) {
        'id not verified' => $pro->documents()->where('type', DocumentType::IdDocument)->update(['status' => DocumentStatus::Pending->value, 'verified_at' => null]),
        'address not verified' => $pro->documents()->where('type', DocumentType::ProofOfAddress)->update(['status' => DocumentStatus::Pending->value, 'verified_at' => null]),
        'negative reference' => $pro->references()->limit(1)->update(['outcome' => ReferenceOutcome::Negative->value]),
        'reference not reached' => $pro->references()->limit(1)->update(['outcome' => ReferenceOutcome::NoAnswer->value]),
    };

    expect(fn () => app(DecideApplication::class)->approve($admin, $pro->fresh()))->toThrow(CannotChangeApplication::class);
    expect($pro->fresh()->status)->toBe(ProStatus::Submitted);
})->with(['id not verified', 'address not verified', 'negative reference', 'reference not reached']);

it('approves an electrician without a registration, who stays unverified but can still be matched (spec 020, decision 2)', function (): void {
    $electrical = tradeOf('electrical');
    $pro = submitted(tradeKeys: ['electrical']);
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);

    app(DecideApplication::class)->approve($admin, $pro->fresh());

    expect($pro->fresh()->status)->toBe(ProStatus::Approved)
        ->and($pro->fresh()->load('documents')->isVerifiedFor($electrical))->toBeFalse()
        ->and(app(EligibleProsQuery::class)->exists($electrical, durban()))->toBeTrue();
});

it('needs a trade and a base address before approval (AC9, spec 020)', function (): void {
    $pro = submitted();
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    $pro->forceFill(['base_location' => null])->save();

    expect(fn () => app(DecideApplication::class)->approve($admin, $pro->fresh()))->toThrow(CannotChangeApplication::class, 'base address');
});

it('requires a reason to reject, messages the pro and makes them wait before reapplying (AC10)', function (): void {
    $pro = submitted();
    $admin = vetter();

    expect(fn () => app(DecideApplication::class)->reject($admin, $pro, '  '))->toThrow(ValidationException::class);

    app(DecideApplication::class)->reject($admin, $pro->fresh(), 'References could not confirm the work.');
    $pro->refresh();

    expect($pro->status)->toBe(ProStatus::Rejected)
        ->and($pro->reapply_after->toDateString())->toBe(now()->addDays(90)->toDateString());
    proMessaging()->assertSent('pro_rejected');

    expect(fn () => app(StartApplication::class)->handle($pro->user))->toThrow(CannotChangeApplication::class);

    $this->travel(91)->days();
    $again = app(StartApplication::class)->handle($pro->user);
    expect($again->id)->toBe($pro->id)->and($again->status)->toBe(ProStatus::Draft);
});

it('uses the reapply wait from settings (decision 4)', function (): void {
    $settings = app(VettingSettings::class);
    $settings->reapply_after_days = 30;
    $settings->save();
    $pro = submitted();

    app(DecideApplication::class)->reject(vetter(), $pro, 'Not enough experience yet.');

    expect($pro->fresh()->reapply_after->toDateString())->toBe(now()->addDays(30)->toDateString());
});

it('suspends and reinstates an approved pro, with a reason and a history row (AC11)', function (): void {
    $pro = submitted();
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    expect(fn () => app(ChangeProStanding::class)->suspend($admin, $pro->fresh(), ''))->toThrow(ValidationException::class);
    app(ChangeProStanding::class)->suspend($admin, $pro->fresh(), 'No-show complaints under review.');

    expect($pro->fresh()->status)->toBe(ProStatus::Suspended)
        ->and(app(EligibleProsQuery::class)->exists(tradeOf('plumbing'), durban()))->toBeFalse();

    app(ChangeProStanding::class)->reinstate($admin, $pro->fresh());
    expect($pro->fresh()->status)->toBe(ProStatus::Approved)
        ->and(app(EligibleProsQuery::class)->exists(tradeOf('plumbing'), durban()))->toBeTrue()
        ->and(ProEvent::query()->where('pro_id', $pro->id)->pluck('to_status')->map->value->all())
        ->toBe(['submitted', 'approved', 'suspended', 'approved']);
});

it('only allows the documented status changes (rules)', function (): void {
    $pro = completeApplication();
    $admin = vetter();

    expect(fn () => app(DecideApplication::class)->approve($admin, $pro))->toThrow(CannotChangeApplication::class)
        ->and(fn () => app(ChangeProStanding::class)->suspend($admin, $pro, 'x'))->toThrow(CannotChangeApplication::class)
        ->and(fn () => app(ChangeProStanding::class)->reinstate($admin, $pro))->toThrow(CannotChangeApplication::class);
});

it('lets only vetting and super admins vet, and never their own application (AC7, rules)', function (Role $role, bool $allowed): void {
    $pro = submitted();
    $admin = vetter($role);
    $document = $pro->documents()->first();

    $attempt = fn () => app(VetDocument::class)->verify($admin, $document, null);

    if ($allowed) {
        $attempt();
    } else {
        expect($attempt)->toThrow(AuthorizationException::class);
    }

    expect($document->fresh()->status)->toBe($allowed ? DocumentStatus::Verified : DocumentStatus::Pending);
})->with([
    'vetting' => [Role::AdminVetting, true],
    'super' => [Role::AdminSuper, true],
    'support' => [Role::AdminSupport, false],
    'finance' => [Role::AdminFinance, false],
]);

it('stops an admin vetting their own application (rules)', function (): void {
    $admin = vetter(Role::AdminSuper);
    $admin->assignRole(Role::Pro->value);
    $pro = submitted($admin);

    expect(fn () => app(VetDocument::class)->verify($admin, $pro->documents()->first(), null))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DecideApplication::class)->reject($admin, $pro, 'Nope'))->toThrow(AuthorizationException::class);
});

it('refuses a second decision on an application that already changed (rules: two admins)', function (): void {
    $pro = submitted();
    $first = vetter();
    $second = vetter();
    $stale = $pro->fresh();

    app(DecideApplication::class)->reject($first, $pro->fresh(), 'Incomplete references.');

    expect(fn () => app(DecideApplication::class)->requestChanges($second, $stale, 'Upload a new ID.'))->toThrow(CannotChangeApplication::class);
    expect($pro->fresh()->status)->toBe(ProStatus::Rejected);
});

it('stops badging an expired registration but still matches the pro (AC12, spec 020)', function (): void {
    $electrical = tradeOf('electrical');
    $user = applicant();
    completeApplication($user, ['plumbing', 'electrical']);
    $pro = Pro::query()->where('user_id', $user->id)->sole();
    app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-1');
    app(StoreProDocument::class)->handle($user, $pro, DocumentType::ElectricalRegisteredPerson, UploadedFile::fake()->image('er.jpg', 20, 20));
    app(SubmitApplication::class)->handle($user, $pro->fresh());
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    expect($pro->fresh()->load('documents')->isVerifiedFor($electrical))->toBeTrue();

    $this->travel(13)->months();

    expect($pro->fresh()->load('documents')->isVerifiedFor($electrical))->toBeFalse()
        ->and(app(EligibleProsQuery::class)->exists($electrical, durban()))->toBeTrue()
        ->and($pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->sole()->isExpired())->toBeTrue();
});

// --- Privacy and retention (AC13–AC15) -----------------------------------------------

it('keeps documents, reference phones and registration numbers out of the activity log (AC15)', function (): void {
    $pro = submitted();
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    $log = DB::table('activity_log')->get()->toJson();

    expect($log)->toContain('pro_approved')
        ->not->toContain('821234567')->not->toContain('Thandi')->not->toContain('Fifteen years')
        ->not->toContain('.webp');
});

it('deletes documents and references of rejected or abandoned applications after 12 months, safely twice (AC14, decision 3)', function (): void {
    $rejected = submitted();
    app(DecideApplication::class)->reject(vetter(), $rejected, 'Not suitable.');
    $abandoned = completeApplication();
    $recent = completeApplication();
    $approved = submitted();
    $admin = vetter();
    vetEverything($approved->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $approved->fresh());

    $this->travel(13)->months();
    $recent->forceFill(['last_activity_at' => now()])->save();

    expect(app(PruneVettingRecords::class)->handle())->toBe(2)
        ->and(app(PruneVettingRecords::class)->handle())->toBe(0);

    foreach ([$rejected, $abandoned] as $pro) {
        expect($pro->documents()->count())->toBe(0)->and($pro->references()->count())->toBe(0)
            ->and($pro->fresh()->bio)->toBeNull()
            ->and(User::query()->find($pro->user_id))->not->toBeNull();
    }
    expect($recent->documents()->count())->toBe(3)->and($approved->documents()->count())->toBe(3)
        ->and(DB::table('media')->count())->toBe(6);
});

it('schedules the vetting prune daily (AC14)', function (): void {
    $events = collect(app(Schedule::class)->events())->map->command->implode(' ');

    expect($events)->toContain('sortd:prune-vetting-records');
});

// --- Review fixes --------------------------------------------------------------------

it('clears every earlier check when a rejected pro reapplies (security review)', function (): void {
    $pro = submitted();
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->reject($admin, $pro->fresh(), 'Not now.');
    $this->travel(91)->days();

    app(StartApplication::class)->handle($pro->user);

    expect($pro->documents()->pluck('status')->map->value->unique()->all())->toBe(['pending'])
        ->and($pro->documents()->whereNotNull('verified_at')->count())->toBe(0)
        ->and($pro->references()->pluck('outcome')->map->value->unique()->all())->toBe(['pending']);
});

it('needs the registration checked again when its number changes (security review)', function (): void {
    $electrical = tradeOf('electrical');
    $user = applicant();
    $pro = completeApplication($user, ['plumbing', 'electrical']);
    app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-1');
    $document = app(StoreProDocument::class)->handle($user, $pro, DocumentType::ElectricalRegisteredPerson, UploadedFile::fake()->image('er.jpg', 20, 20));
    $document->forceFill(['status' => DocumentStatus::Verified, 'verified_at' => now(), 'expires_at' => now()->addYear()])->save();

    app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-2');

    expect($document->fresh()->status)->toBe(DocumentStatus::Pending)->and($document->fresh()->verified_at)->toBeNull();
});

it('will not verify a registration without its number (spec check)', function (): void {
    $electrical = tradeOf('electrical');
    $user = applicant();
    $pro = completeApplication($user, ['plumbing', 'electrical']);
    $document = app(StoreProDocument::class)->handle($user, $pro, DocumentType::ElectricalRegisteredPerson, UploadedFile::fake()->image('er.jpg', 20, 20));
    app(SubmitApplication::class)->handle($user, $pro->fresh());

    expect(fn () => app(VetDocument::class)->verify(vetter(), $document, now()->addYear()->toImmutable()))->toThrow(ValidationException::class)
        ->and(fn () => app(VetDocument::class)->verify(vetter(), $document->fresh(), null))->toThrow(ValidationException::class);
});

it('limits uploads and submissions per pro (security review)', function (): void {
    config()->set('sortd.pros.uploads_per_hour', 2);
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);

    app(StoreProDocument::class)->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('a.jpg', 20, 20));
    app(StoreProDocument::class)->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('b.jpg', 20, 20));

    expect(fn () => app(StoreProDocument::class)->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->image('c.jpg', 20, 20)))
        ->toThrow(ValidationException::class);

    config()->set('sortd.pros.uploads_per_hour', 30);
    config()->set('sortd.pros.submissions_per_hour', 1);
    $other = completeApplication();
    $other->forceFill(['bio' => null])->save();
    expect(fn () => app(SubmitApplication::class)->handle($other->user, $other->fresh()))->toThrow(ValidationException::class, 'Still needed');

    $other->forceFill(['bio' => 'Back again.'])->save();
    expect(fn () => app(SubmitApplication::class)->handle($other->user, $other->fresh()))->toThrow(ValidationException::class, 'Please try again later');
    expect($other->fresh()->status)->toBe(ProStatus::Draft);
});

it('refuses PDFs that carry scripts or embedded files (security review)', function (string $marker): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);

    expect(fn () => app(StoreProDocument::class)->handle($user, $pro, DocumentType::ProofOfAddress, UploadedFile::fake()->createWithContent('bill.pdf', "%PDF-1.4\n1 0 obj<< {$marker} >>endobj\n%%EOF")))
        ->toThrow(ValidationException::class);
})->with(['/JavaScript', '/JS', '/Launch', '/EmbeddedFile']);

it('logs every status change, including the pro\'s own (spec check AC11)', function (): void {
    $pro = submitted();

    expect(DB::table('activity_log')->where('description', 'pro_status_changed')->where('subject_id', $pro->id)->count())->toBe(1);
});

it('lets a pro replace a reference vetting could not use, with the new referee\'s agreement (AC6, AC13)', function (): void {
    $pro = submitted();
    $admin = vetter();
    $bad = $pro->references()->first();
    app(CheckReference::class)->handle($admin, $bad, ReferenceOutcome::NoAnswer, null);
    app(DecideApplication::class)->requestChanges($admin, $pro->fresh(), 'One referee did not answer.');
    proMessaging()->assertSent('pro_changes_requested');
    $steps = app(SaveApplicationStep::class);
    $good = $pro->references()->whereKeyNot($bad->id)->sole();

    expect(fn () => $steps->replaceReference($pro->user, $pro->fresh(), $bad, new ReferenceData('Lindiwe Zulu', '083 555 1234', 'Customer'), refereeAgreed: false))->toThrow(ValidationException::class)
        ->and(fn () => $steps->replaceReference($pro->user, $pro->fresh(), $good, new ReferenceData('X Y', '083 555 9999', 'Friend'), refereeAgreed: true))->toThrow(AuthorizationException::class);

    $steps->replaceReference($pro->user, $pro->fresh(), $bad, new ReferenceData('Lindiwe Zulu', '083 555 1234', 'Customer'), refereeAgreed: true);

    expect($bad->fresh()->name)->toBe('Lindiwe Zulu')->and($bad->fresh()->outcome)->toBe(ReferenceOutcome::Pending);
    app(SubmitApplication::class)->handle($pro->user, $pro->fresh());
    expect($pro->fresh()->status)->toBe(ProStatus::Submitted);
});

it('removes the files and the vetting reasons when pruning (security review)', function (): void {
    $pro = submitted();
    $path = $pro->documents()->first()->file()->getPathRelativeToRoot();
    app(DecideApplication::class)->reject(vetter(), $pro->fresh(), 'Referee said the work was poor.');
    Storage::disk('media')->assertExists($path);

    $this->travel(13)->months();
    app(PruneVettingRecords::class)->handle();

    Storage::disk('media')->assertMissing($path);
    expect($pro->fresh()->decision_reason)->toBeNull()
        ->and(DB::table('pro_events')->where('pro_id', $pro->id)->whereNotNull('reason')->count())->toBe(0)
        ->and(ProEvent::query()->where('pro_id', $pro->id)->count())->toBe(2);
});

it('lets admins choose only active trades and a sensible radius when correcting coverage (security review)', function (): void {
    $pro = submitted();
    $inactive = tradeOf('tiling');
    $inactive->update(['is_active' => false]);

    expect(fn () => app(EditProCoverage::class)->handle(vetter(), $pro, [$inactive->id], 15))->toThrow(ValidationException::class)
        ->and(fn () => app(EditProCoverage::class)->handle(vetter(), $pro, [tradeOf('plumbing')->id], 80))->toThrow(ValidationException::class);

    app(EditProCoverage::class)->handle(vetter(), $pro, [tradeOf('plumbing')->id, tradeOf('painting')->id], 20);
    expect($pro->fresh()->trades->pluck('key')->sort()->values()->all())->toBe(['painting', 'plumbing'])->and($pro->fresh()->service_radius_km)->toBe(20);
});

it('accepts real-world PDFs: padding before the header, and binary stream data that happens to contain "/JS"', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    $store = app(StoreProDocument::class);
    $padded = "\n\n%PDF-1.7\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
    $binary = "%PDF-1.4\n1 0 obj<</Length 20>>\nstream\n\x00\x01/JS x\x02/Launch\n/EmbeddedFile\x03\nendstream\nendobj\n%%EOF";

    expect($store->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->createWithContent('scan.pdf', $padded))->file()->mime_type)->toBe('application/pdf')
        ->and($store->handle($user, $pro, DocumentType::ProofOfAddress, UploadedFile::fake()->createWithContent('bill.pdf', $binary))->file()->mime_type)->toBe('application/pdf');
});

it('refuses a PDF that really contains scripts or attachments, and says why', function (string $body): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);

    expect(fn () => app(StoreProDocument::class)->handle($user, $pro, DocumentType::IdDocument, UploadedFile::fake()->createWithContent('bad.pdf', $body)))
        ->toThrow(ValidationException::class, 'contains scripts or attachments');
})->with([
    'javascript action' => ["%PDF-1.4\n1 0 obj<</S/JavaScript/JS(app.alert(1))>>endobj\n%%EOF"],
    'launch action' => ["%PDF-1.4\n1 0 obj<</S/Launch/F(cmd.exe)>>endobj\n%%EOF"],
    'embedded file' => ["%PDF-1.4\n1 0 obj<</Type/EmbeddedFile>>endobj\n%%EOF"],
]);

it('tells the pro that the profile photo cannot be a PDF', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);

    expect(fn () => app(StoreProDocument::class)->handle($user, $pro, DocumentType::ProfilePhoto, UploadedFile::fake()->createWithContent('me.pdf', "%PDF-1.4\n%%EOF")))
        ->toThrow(ValidationException::class, 'must be a photo');
});
