<?php

declare(strict_types=1);

use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Pros\Actions\ChangeProStanding;
use App\Domain\Pros\Actions\CheckReference;
use App\Domain\Pros\Actions\DecideApplication;
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
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use App\Settings\VettingSettings;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    Storage::fake('media');
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
    $this->musgrave = Suburb::query()->where('slug', 'musgrave')->sole();
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
function completeApplication(?User $user = null, array $serviceKeys = ['leak_repair']): Pro
{
    $user ??= applicant();
    $pro = app(StartApplication::class)->handle($user);
    $steps = app(SaveApplicationStep::class);
    $steps->business($user, $pro, new BusinessDetails('Dlamini Plumbing', BusinessType::SoleTrader, null));
    $steps->services($user, $pro, Service::query()->whereIn('key', $serviceKeys)->pluck('id')->all());
    $steps->areas($user, $pro, [Suburb::query()->where('slug', 'musgrave')->value('id')]);
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

function submitted(?User $user = null, array $serviceKeys = ['leak_repair']): Pro
{
    $pro = completeApplication($user, $serviceKeys);
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
        ->and($pro->services->pluck('key')->all())->toBe(['leak_repair'])
        ->and($pro->serviceAreas->pluck('slug')->all())->toBe(['musgrave'])
        ->and($pro->references)->toHaveCount(2)
        ->and($pro->references->first()->phone_e164)->toBe('+27821234567')
        ->and($pro->documents)->toHaveCount(3)
        ->and($pro->vetting_consent_at)->not->toBeNull();
});

it('only lets pros apply, and only for active services and active suburbs (AC1)', function (): void {
    expect(fn () => app(StartApplication::class)->handle(User::factory()->customer()->create()))->toThrow(AuthorizationException::class);

    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    $inactive = Service::query()->where('key', 'blocked_drain')->sole();
    $inactive->update(['is_active' => false]);
    $inactiveSuburb = Suburb::query()->where('is_active', false)->first() ?? tap(Suburb::query()->where('slug', '!=', 'musgrave')->first())->update(['is_active' => false]);

    expect(fn () => app(SaveApplicationStep::class)->services($user, $pro, [$inactive->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveApplicationStep::class)->areas($user, $pro, [$inactiveSuburb->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveApplicationStep::class)->services($user, $pro, []))->toThrow(ValidationException::class);
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

it('asks for a registration only for services that need one, and stores the number encrypted (AC3, AC13)', function (): void {
    $user = applicant();
    $pro = app(StartApplication::class)->handle($user);
    $electrical = Service::query()->whereNotNull('requires_registration')->where('requires_registration', 'electrical_registered_person')->firstOrFail();
    app(SaveApplicationStep::class)->services($user, $pro, [$this->leak->id, $electrical->id]);

    expect($pro->fresh()->requiredRegistrations())->toBe([DocumentType::ElectricalRegisteredPerson]);

    app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-123456');
    $document = $pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->sole();

    expect($document->number)->toBe('ER-123456')
        ->and(DB::table('pro_documents')->where('id', $document->id)->value('number'))->not->toContain('ER-123456');
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
        'suburbs' => $pro->serviceAreas()->detach(),
    };

    expect(fn () => app(SubmitApplication::class)->handle($pro->user, $pro->fresh()))->toThrow(ValidationException::class);
    expect($pro->fresh()->status)->toBe(ProStatus::Draft);
})->with(['id document', 'proof of address', 'profile photo', 'references', 'bio', 'consent', 'suburbs']);

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
    expect($replaced->status)->toBe(DocumentStatus::Pending)->and($replaced->flag_message)->toBeNull();

    app(SubmitApplication::class)->handle($pro->user, $pro->fresh());
    expect($pro->fresh()->status)->toBe(ProStatus::Submitted);
});

// --- Vetting (AC7–AC11) -------------------------------------------------------------

it('approves a fully checked application, records who did it, messages the pro and starts coverage (AC9)', function (): void {
    $pro = submitted();
    $admin = vetter();
    expect(app(EligibleProsQuery::class)->exists($this->leak, $this->musgrave))->toBeFalse();

    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    $pro->refresh();
    expect($pro->status)->toBe(ProStatus::Approved)
        ->and($pro->approved_at)->not->toBeNull()
        ->and($pro->decided_by)->toBe($admin->id)
        ->and(app(EligibleProsQuery::class)->exists($this->leak, $this->musgrave))->toBeTrue();

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

it('needs at least one service it can actually do: a registration-only application cannot be approved (AC3, AC9)', function (): void {
    $electrical = Service::query()->where('requires_registration', 'electrical_registered_person')->firstOrFail();
    $pro = submitted(serviceKeys: [$electrical->key]);
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);

    expect(fn () => app(DecideApplication::class)->approve($admin, $pro->fresh()))->toThrow(CannotChangeApplication::class);
});

it('keeps a service without its registration out of coverage after approval (AC3)', function (): void {
    $electrical = Service::query()->where('requires_registration', 'electrical_registered_person')->firstOrFail();
    $pro = submitted(serviceKeys: ['leak_repair', $electrical->key]);
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    expect(app(EligibleProsQuery::class)->exists($this->leak, $this->musgrave))->toBeTrue()
        ->and(app(EligibleProsQuery::class)->exists($electrical, $this->musgrave))->toBeFalse();
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
        ->and(app(EligibleProsQuery::class)->exists($this->leak, $this->musgrave))->toBeFalse();

    app(ChangeProStanding::class)->reinstate($admin, $pro->fresh());
    expect($pro->fresh()->status)->toBe(ProStatus::Approved)
        ->and(app(EligibleProsQuery::class)->exists($this->leak, $this->musgrave))->toBeTrue()
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

it('stops counting an expired registration (AC12)', function (): void {
    $electrical = Service::query()->where('requires_registration', 'electrical_registered_person')->firstOrFail();
    $user = applicant();
    completeApplication($user, ['leak_repair', $electrical->key]);
    $pro = Pro::query()->where('user_id', $user->id)->sole();
    app(SaveApplicationStep::class)->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-1');
    app(StoreProDocument::class)->handle($user, $pro, DocumentType::ElectricalRegisteredPerson, UploadedFile::fake()->image('er.jpg', 20, 20));
    app(SubmitApplication::class)->handle($user, $pro->fresh());
    $admin = vetter();
    vetEverything($pro->fresh(), $admin);
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    expect(app(EligibleProsQuery::class)->exists($electrical, $this->musgrave))->toBeTrue();

    $this->travel(13)->months();

    expect(app(EligibleProsQuery::class)->exists($electrical, $this->musgrave))->toBeFalse()
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
