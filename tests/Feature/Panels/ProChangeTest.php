<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Pros\Actions\DecideProChange;
use App\Domain\Pros\Actions\RequestProChange;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Admin\Resources\ProChangeRequests\Pages\ListProChangeRequests;
use App\Livewire\Pros\Profile;
use App\Models\Pro;
use App\Models\ProChangeRequest;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function changePro(bool $holdsPirb = false): Pro
{
    $user = User::factory()->pro()->create(['first_name' => 'Thabo']);
    $pro = Pro::factory()->approved()->create(['user_id' => $user->id, 'business_name' => 'Dlamini Plumbing', 'bio' => 'Plumber.']);
    $pro->serviceAreas()->attach(Suburb::factory()->create(['is_active' => true]));
    $pro->services()->attach(Service::factory()->create(['name' => 'Leak repair']));

    if ($holdsPirb) {
        $pro->documents()->create(['type' => DocumentType::Pirb])->forceFill([
            'status' => DocumentStatus::Verified, 'number' => 'OLD-111', 'verified_at' => now(), 'expires_at' => now()->addDays(10),
        ])->save();
    }

    return $pro;
}

function changeService(string $name, ?RegistrationType $registration = null, bool $active = true): Service
{
    return Service::factory()->create(['name' => $name, 'requires_registration' => $registration, 'is_active' => $active]);
}

function changeFile(): UploadedFile
{
    return UploadedFile::fake()->image('certificate.jpg', 60, 40);
}

function changeAdmin(Role $role = Role::AdminVetting): User
{
    $admin = User::factory()->create();
    $admin->assignRole($role->value);

    return $admin;
}

function changeRequest(Pro $pro, ?Service $service = null, ?DocumentType $registration = null, string $number = 'NEW-222', bool $file = true): ProChangeRequest
{
    return app(RequestProChange::class)->handle($pro->user, $pro, $service, $registration, $registration === null ? null : $number, $registration !== null && $file ? changeFile() : null);
}

beforeEach(function (): void {
    Storage::fake('media');
});

// --- Asking (AC27) -----------------------------------------------------------------------

it('keeps a new service pending without changing what the pro offers (spec 021, AC27)', function (): void {
    $pro = changePro();
    $plumbing = changeService('Geyser installation');

    $request = changeRequest($pro, $plumbing);

    expect($request->status)->toBe(ProChangeStatus::Pending)->and($request->service_id)->toBe($plumbing->id);
    expect($pro->services()->pluck('services.name')->all())->toBe(['Leak repair']);
});

it('asks for the registration number and file when a service needs one the pro does not hold (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeService('Fault finding', RegistrationType::ElectricalRegisteredPerson);

    expect(fn () => changeRequest($pro, $electrical))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $electrical, DocumentType::Pirb))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'EW-1', file: false))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'not a number!'))->toThrow(ValidationException::class);

    $request = changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'ew-123/45');

    expect($request->document_type)->toBe(DocumentType::ElectricalRegisteredPerson)->and($request->registration_number)->toBe('EW-123/45')->and($request->file())->not->toBeNull();
    expect($pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->exists())->toBeFalse()->and($pro->services()->count())->toBe(1);
});

it('lets a service through with no new file when the pro already holds the registration (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $pirbService = changeService('Drain unblocking', RegistrationType::Pirb);

    $request = changeRequest($pro, $pirbService);

    expect($request->document_type)->toBeNull()->and($request->file())->toBeNull();
});

it('needs a new file when the registration the pro holds has expired (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $pro->documents()->where('type', DocumentType::Pirb)->firstOrFail()->forceFill(['expires_at' => now()->subDay()])->save();

    expect(fn () => changeRequest($pro, changeService('Drain unblocking', RegistrationType::Pirb)))->toThrow(ValidationException::class);
});

it('keeps the old registration verified and live while a renewal waits (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $service = changeService('Drain unblocking', RegistrationType::Pirb);
    $pro->services()->attach($service);
    $pro->serviceAreas()->each(fn ($suburb) => null);
    $suburb = $pro->serviceAreas()->firstOrFail();

    changeRequest($pro, null, DocumentType::Pirb, 'NEW-222');

    $old = $pro->documents()->where('type', DocumentType::Pirb)->firstOrFail();
    expect($old->status)->toBe(DocumentStatus::Verified)->and($old->number)->toBe('OLD-111');
    expect(app(EligibleProsQuery::class)->for($service, $suburb)->whereKey($pro->id)->exists())->toBeTrue();
});

it('only renews a registration the pro already gave us, or adds the one a new service needs (spec 021, AC27)', function (): void {
    $pro = changePro();

    expect(fn () => changeRequest($pro, null, DocumentType::Pirb))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, null, DocumentType::IdDocument))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro))->toThrow(ValidationException::class);
});

it('refuses services the pro has, has already asked for, or that are not offered (spec 021, AC27)', function (): void {
    $pro = changePro();
    $offered = changeService('Geyser installation');
    $inactive = changeService('Retired service', active: false);

    changeRequest($pro, $offered);

    expect(fn () => changeRequest($pro, $offered))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $pro->services()->firstOrFail()))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $inactive))->toThrow(ValidationException::class);
    expect($pro->changeRequests()->count())->toBe(1);
});

it('refuses a PDF that can run scripts and a file that is too big (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $evil = UploadedFile::fake()->createWithContent('cert.pdf', "%PDF-1.4\n1 0 obj<</JavaScript (app.alert(1))>>endobj\n%%EOF");

    expect(fn () => app(RequestProChange::class)->handle($pro->user, $pro, null, DocumentType::Pirb, 'NEW-1', $evil))->toThrow(ValidationException::class);
    expect(fn () => app(RequestProChange::class)->handle($pro->user, $pro, null, DocumentType::Pirb, 'NEW-1', UploadedFile::fake()->create('big.pdf', 11_000, 'application/pdf')))->toThrow(ValidationException::class);
    expect($pro->changeRequests()->count())->toBe(0);
});

it('lets only an approved pro ask for their own changes (spec 021, AC27, AC31)', function (): void {
    $pro = changePro();
    $rival = changePro();
    $service = changeService('Geyser installation');

    expect(fn () => app(RequestProChange::class)->handle($rival->user, $pro, $service, null, null, null))->toThrow(HttpException::class);

    $pro->forceFill(['status' => 'suspended'])->save();
    expect(fn () => app(RequestProChange::class)->handle($pro->user, $pro, $service, null, null, null))->toThrow(HttpException::class);
});

it('walks a pro through the request forms on their profile and shows the waiting state (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeService('Fault finding', RegistrationType::ElectricalRegisteredPerson);
    changeService('Geyser installation');
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)
        ->assertSee('Add a service')->assertSee('Geyser installation')->assertSee('Fault finding')
        ->assertDontSee('Renew a registration')
        ->call('requestService')->assertHasErrors('newService')
        ->set('newService', (string) $electrical->id)
        ->assertSee('Registered electrician')->assertSee('Registration number')
        ->call('requestService')->assertHasErrors(['serviceNumber'])
        ->set('serviceNumber', 'EW-9')->set('serviceUpload', changeFile())->call('requestService')
        ->assertHasNoErrors()->assertSee('Sent.')->assertSee('Waiting for review')->assertSee('Add Fault finding');

    expect($pro->changeRequests()->sole()->registration_number)->toBe('EW-9');
    Livewire::test(Profile::class)->assertDontSee('<option value="'.$electrical->id.'">', false);
});

it('lets a pro who holds a registration renew it from their profile (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->assertSee('Renew a registration')
        ->call('requestRenewal')->assertHasErrors('renewType')
        ->set('renewType', 'pirb')->call('requestRenewal')->assertHasErrors('renewNumber')
        ->set('renewNumber', 'NEW-222')->set('renewUpload', changeFile())->call('requestRenewal')
        ->assertHasNoErrors()->assertSee('Registration: PIRB registration')->assertSee('Waiting for review');

    expect($pro->changeRequests()->sole()->document_type)->toBe(DocumentType::Pirb);
});

// --- Deciding (AC27) ---------------------------------------------------------------------

it('adds a service when a vetting admin approves it (spec 021, AC27)', function (): void {
    $pro = changePro();
    $service = changeService('Geyser installation');
    $request = changeRequest($pro, $service);

    app(DecideProChange::class)->approve(changeAdmin(), $request);

    expect($pro->services()->pluck('services.name')->sort()->values()->all())->toBe(['Geyser installation', 'Leak repair']);
    $request->refresh();
    expect($request->status)->toBe(ProChangeStatus::Approved)->and($request->decided_at)->not->toBeNull()->and($request->decided_by)->not->toBeNull();
});

it('replaces the registration document, verified, with the expiry the admin enters (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $request = changeRequest($pro, null, DocumentType::Pirb, 'NEW-222');
    $admin = changeAdmin();

    expect(fn () => app(DecideProChange::class)->approve($admin, $request))->toThrow(ValidationException::class);
    expect(fn () => app(DecideProChange::class)->approve($admin, $request, CarbonImmutable::now()->subDay()))->toThrow(ValidationException::class);
    expect($pro->documents()->where('type', DocumentType::Pirb)->firstOrFail()->number)->toBe('OLD-111');

    $expires = CarbonImmutable::now()->addYear()->endOfDay();
    app(DecideProChange::class)->approve($admin, $request, $expires);

    $document = $pro->documents()->where('type', DocumentType::Pirb)->with('media')->firstOrFail();
    expect($document->number)->toBe('NEW-222')->and($document->status)->toBe(DocumentStatus::Verified)->and($document->expires_at?->toDateString())->toBe($expires->toDateString())
        ->and($document->verified_by)->toBe($admin->id)->and($document->file())->not->toBeNull();
    expect($request->refresh()->status)->toBe(ProChangeStatus::Approved);
});

it('applies a service and its new registration together, or neither (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeService('Fault finding', RegistrationType::ElectricalRegisteredPerson);
    $request = changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'EW-9');
    $admin = changeAdmin();

    expect(fn () => app(DecideProChange::class)->approve($admin, $request))->toThrow(ValidationException::class);
    expect($pro->services()->count())->toBe(1)->and($pro->documents()->count())->toBe(0)->and($request->refresh()->isPending())->toBeTrue();

    app(DecideProChange::class)->approve($admin, $request, CarbonImmutable::now()->addYear());

    expect($pro->services()->count())->toBe(2)->and($pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->firstOrFail()->status)->toBe(DocumentStatus::Verified);
});

it('leaves everything as it was when a change is rejected, and tells the pro why (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $request = changeRequest($pro, changeService('Geyser installation'), DocumentType::Pirb, 'NEW-222');
    $admin = changeAdmin();

    expect(fn () => app(DecideProChange::class)->reject($admin, $request, '   '))->toThrow(ValidationException::class);

    app(DecideProChange::class)->reject($admin, $request, 'The certificate photo is unreadable.');

    expect($pro->services()->count())->toBe(1)->and($pro->documents()->where('type', DocumentType::Pirb)->firstOrFail()->number)->toBe('OLD-111');
    expect($request->refresh()->status)->toBe(ProChangeStatus::Rejected)->and($request->decision_reason)->toBe('The certificate photo is unreadable.');

    $this->actingAs($pro->user);
    Livewire::test(Profile::class)->assertSee('Not approved')->assertSee('The certificate photo is unreadable.');
});

it('decides each request once (spec 021, AC27)', function (): void {
    $pro = changePro();
    $request = changeRequest($pro, changeService('Geyser installation'));
    $admin = changeAdmin();
    app(DecideProChange::class)->approve($admin, $request);

    expect(fn () => app(DecideProChange::class)->approve($admin, $request))->toThrow(CannotChangeApplication::class);
    expect(fn () => app(DecideProChange::class)->reject($admin, $request, 'Too late'))->toThrow(CannotChangeApplication::class);
});

it('will not apply a change to a pro who is no longer approved (spec 021, AC27)', function (): void {
    $pro = changePro();
    $request = changeRequest($pro, changeService('Geyser installation'));
    $pro->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(DecideProChange::class)->approve(changeAdmin(), $request))->toThrow(CannotChangeApplication::class);
    expect($pro->services()->count())->toBe(1);
});

it('lets only vetting and super admins decide, never on their own profile (spec 021, AC27, AC31)', function (): void {
    $pro = changePro();
    $request = changeRequest($pro, changeService('Geyser installation'));

    foreach ([Role::AdminSupport, Role::AdminFinance] as $role) {
        expect(fn () => app(DecideProChange::class)->approve(changeAdmin($role), $request))->toThrow(AuthorizationException::class);
    }
    expect(fn () => app(DecideProChange::class)->approve($pro->user, $request))->toThrow(AuthorizationException::class);

    $adminPro = changePro();
    $adminPro->user->assignRole(Role::AdminVetting->value);
    $own = changeRequest($adminPro, changeService('Tiling grout'));
    expect(fn () => app(DecideProChange::class)->approve($adminPro->user, $own))->toThrow(AuthorizationException::class);

    expect($request->refresh()->isPending())->toBeTrue();
});

it('logs requests and decisions without registration numbers (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $request = changeRequest($pro, null, DocumentType::Pirb, 'NEW-222');
    app(DecideProChange::class)->approve(changeAdmin(), $request, CarbonImmutable::now()->addYear());

    $entries = Activity::query()->whereIn('description', ['pro change requested', 'pro_change_approved'])->get();

    expect($entries->pluck('description')->sort()->values()->all())->toBe(['pro change requested', 'pro_change_approved']);
    expect(json_encode($entries->pluck('properties')->all()))->not->toContain('NEW-222')->not->toContain('OLD-111');
});

// --- The admin queue and the file link ---------------------------------------------------

it('shows the queue to vetting and super admins only, and decides from it (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $request = changeRequest($pro, changeService('Geyser installation'), DocumentType::Pirb, 'NEW-222');
    Filament::setCurrentPanel('admin');

    foreach ([Role::AdminSupport, Role::AdminFinance] as $role) {
        $this->actingAs(changeAdmin($role));
        Livewire::test(ListProChangeRequests::class)->assertForbidden();
    }
    $this->actingAs(User::factory()->customer()->create())->get('/admin/pro-changes')->assertForbidden();

    $this->actingAs(changeAdmin(Role::AdminSuper));
    Livewire::test(ListProChangeRequests::class)->assertCanSeeTableRecords([$request])->assertSee('Dlamini Plumbing')->assertSee('Add Geyser installation')->assertSee('NEW-222')
        ->callAction(TestAction::make('approve')->table($request), data: ['expires_at' => now()->addYear()->toDateString()])->assertHasNoFormErrors();

    expect($request->refresh()->status)->toBe(ProChangeStatus::Approved);
});

it('rejects from the queue with a required reason (spec 021, AC27)', function (): void {
    $request = changeRequest(changePro(), changeService('Geyser installation'));
    Filament::setCurrentPanel('admin');
    $this->actingAs(changeAdmin());

    Livewire::test(ListProChangeRequests::class)->callAction(TestAction::make('reject')->table($request), data: ['reason' => ''])->assertHasFormErrors(['reason']);
    expect($request->refresh()->isPending())->toBeTrue();

    Livewire::test(ListProChangeRequests::class)->callAction(TestAction::make('reject')->table($request), data: ['reason' => 'Not a service we can verify yet.'])->assertHasNoFormErrors();

    expect($request->refresh()->decision_reason)->toBe('Not a service we can verify yet.');
});

it('hides an admin\'s own pro requests from them in the queue (spec 021, AC27, AC31)', function (): void {
    $adminPro = changePro();
    $adminPro->user->assignRole(Role::AdminVetting->value);
    $own = changeRequest($adminPro, changeService('Tiling grout'));
    Filament::setCurrentPanel('admin');
    $this->actingAs($adminPro->user);

    Livewire::test(ListProChangeRequests::class)->assertCanNotSeeTableRecords([$own]);
});

it('serves the request file only through a valid signed link to its pro or a vetting admin (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $request = changeRequest($pro, null, DocumentType::Pirb, 'NEW-222');
    $url = $request->temporaryUrl();

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($pro->user)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(changePro()->user)->get($url)->assertNotFound();
    $this->actingAs($pro->user)->get(route('pro-changes.file', $request))->assertForbidden();

    $admin = changeAdmin();
    $this->actingAs($admin)->get($url)->assertForbidden();
    $this->actingAs($admin)->withSession([AdminLogin::SESSION_KEY => $admin->id])->get($url)->assertOk();

    $support = changeAdmin(Role::AdminSupport);
    $this->actingAs($support)->withSession([AdminLogin::SESSION_KEY => $support->id])->get($url)->assertNotFound();
});
