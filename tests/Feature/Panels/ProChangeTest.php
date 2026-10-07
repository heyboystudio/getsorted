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
use App\Models\Trade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
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
    $pro->trades()->attach(Trade::factory()->create(['name' => 'Plumbing', 'registration' => RegistrationType::Pirb]));

    if ($holdsPirb) {
        $pro->documents()->create(['type' => DocumentType::Pirb])->forceFill([
            'status' => DocumentStatus::Verified, 'number' => 'OLD-111', 'verified_at' => now(), 'expires_at' => now()->addDays(10),
        ])->save();
    }

    return $pro;
}

function changeTrade(string $name, ?RegistrationType $registration = null, bool $active = true): Trade
{
    return Trade::factory()->create(['name' => $name, 'registration' => $registration, 'is_active' => $active]);
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

function changeRequest(Pro $pro, ?Trade $trade = null, ?DocumentType $registration = null, string $number = 'NEW-222', bool $file = true): ProChangeRequest
{
    return app(RequestProChange::class)->handle($pro->user, $pro, $trade, $registration, $registration === null ? null : $number, $registration !== null && $file ? changeFile() : null);
}

beforeEach(function (): void {
    Storage::fake('media');
});

// --- Asking (AC27) -----------------------------------------------------------------------

it('keeps a new trade pending without changing what the pro offers (spec 021, AC27)', function (): void {
    $pro = changePro();
    $painting = changeTrade('Painting');

    $request = changeRequest($pro, $painting);

    expect($request->status)->toBe(ProChangeStatus::Pending)->and($request->trade_id)->toBe($painting->id);
    expect($pro->trades()->pluck('trades.name')->all())->toBe(['Plumbing']);
});

it('never requires a registration to add a trade, because registrations are only badges (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeTrade('Electrical', RegistrationType::ElectricalRegisteredPerson);

    $request = changeRequest($pro, $electrical);

    expect($request->document_type)->toBeNull()->and($request->file())->toBeNull();
});

it('takes a registration for the new trade with its number and file, or none at all (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeTrade('Electrical', RegistrationType::ElectricalRegisteredPerson);

    expect(fn () => changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'EW-1', file: false))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'not a number!'))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $electrical, DocumentType::Pirb, 'PB-1'))->not->toThrow(ValidationException::class);
});

it('stores the registration with the request and leaves the pro\'s documents alone (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeTrade('Electrical', RegistrationType::ElectricalRegisteredPerson);

    $request = changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'ew-123/45');

    expect($request->registration_number)->toBe('EW-123/45')->and($request->file())->not->toBeNull();
    expect($pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->exists())->toBeFalse()->and($pro->trades()->count())->toBe(1);
});

it('keeps the old registration verified and the pro matchable while a renewal waits (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $trade = $pro->trades()->firstOrFail();
    $point = Point::makeGeodetic(-29.8587, 31.0218);

    changeRequest($pro, null, DocumentType::Pirb, 'NEW-222');

    $old = $pro->documents()->where('type', DocumentType::Pirb)->firstOrFail();
    expect($old->status)->toBe(DocumentStatus::Verified)->and($old->number)->toBe('OLD-111');
    expect(app(EligibleProsQuery::class)->near($trade, $point)->whereKey($pro->id)->exists())->toBeTrue();
});

it('only sends a registration for one of the pro\'s trades, or renews one already given (spec 021, AC27)', function (): void {
    $pro = changePro();

    expect(fn () => changeRequest($pro, null, DocumentType::ElectricalRegisteredPerson))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, null, DocumentType::IdDocument))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro))->toThrow(ValidationException::class);
    expect(changeRequest($pro, null, DocumentType::Pirb, 'PB-9')->document_type)->toBe(DocumentType::Pirb);
});

it('refuses trades the pro has, has already asked for, or that are not offered (spec 021, AC27)', function (): void {
    $pro = changePro();
    $offered = changeTrade('Painting');
    $inactive = changeTrade('Retired trade', active: false);

    changeRequest($pro, $offered);

    expect(fn () => changeRequest($pro, $offered))->toThrow(ValidationException::class);
    expect(fn () => changeRequest($pro, $pro->trades()->firstOrFail()))->toThrow(ValidationException::class);
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
    $trade = changeTrade('Painting');

    expect(fn () => app(RequestProChange::class)->handle($rival->user, $pro, $trade, null, null, null))->toThrow(HttpException::class);

    $pro->forceFill(['status' => 'suspended'])->save();
    expect(fn () => app(RequestProChange::class)->handle($pro->user, $pro, $trade, null, null, null))->toThrow(HttpException::class);
});

it('walks a pro through the request forms on their profile and shows the waiting state (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeTrade('Electrical', RegistrationType::ElectricalRegisteredPerson);
    changeTrade('Painting');
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)
        ->assertSee('Add a trade')->assertSee('Painting')->assertSee('Electrical')
        ->call('requestTrade')->assertHasErrors('newTrade')
        ->set('newTrade', (string) $electrical->id)
        ->assertSee('Optional: add your Registered electrician')
        ->set('tradeNumber', 'EW-9')->call('requestTrade')->assertHasErrors('tradeUpload')
        ->set('tradeUpload', changeFile())->call('requestTrade')
        ->assertHasNoErrors()->assertSee('Sent.')->assertSee('Waiting for review')->assertSee('Add Electrical');

    expect($pro->changeRequests()->sole()->registration_number)->toBe('EW-9');
    Livewire::test(Profile::class)->assertDontSee('<option value="'.$electrical->id.'">', false);
});

it('lets a pro add a trade without any registration from their profile (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeTrade('Electrical', RegistrationType::ElectricalRegisteredPerson);
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->set('newTrade', (string) $electrical->id)->call('requestTrade')->assertHasNoErrors()->assertSee('Waiting for review');

    expect($pro->changeRequests()->sole()->document_type)->toBeNull();
});

it('lets a pro send or renew a registration from their profile (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->assertSee('Send or renew a registration')
        ->call('requestRegistration')->assertHasErrors('renewType')
        ->set('renewType', 'pirb')->call('requestRegistration')->assertHasErrors('renewNumber')
        ->set('renewNumber', 'NEW-222')->set('renewUpload', changeFile())->call('requestRegistration')
        ->assertHasNoErrors()->assertSee('Registration: PIRB registration')->assertSee('Waiting for review');

    expect($pro->changeRequests()->sole()->document_type)->toBe(DocumentType::Pirb);
});

// --- Deciding (AC27) ---------------------------------------------------------------------

it('adds a trade when a vetting admin approves it (spec 021, AC27)', function (): void {
    $pro = changePro();
    $trade = changeTrade('Painting');
    $request = changeRequest($pro, $trade);

    app(DecideProChange::class)->approve(changeAdmin(), $request);

    expect($pro->trades()->pluck('trades.name')->sort()->values()->all())->toBe(['Painting', 'Plumbing']);
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

it('applies a trade and its new registration together, or neither (spec 021, AC27)', function (): void {
    $pro = changePro();
    $electrical = changeTrade('Electrical', RegistrationType::ElectricalRegisteredPerson);
    $request = changeRequest($pro, $electrical, DocumentType::ElectricalRegisteredPerson, 'EW-9');
    $admin = changeAdmin();

    expect(fn () => app(DecideProChange::class)->approve($admin, $request))->toThrow(ValidationException::class);
    expect($pro->trades()->count())->toBe(1)->and($pro->documents()->count())->toBe(0)->and($request->refresh()->isPending())->toBeTrue();

    app(DecideProChange::class)->approve($admin, $request, CarbonImmutable::now()->addYear());

    expect($pro->trades()->count())->toBe(2)->and($pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->firstOrFail()->status)->toBe(DocumentStatus::Verified);
});

it('leaves everything as it was when a change is rejected, and tells the pro why (spec 021, AC27)', function (): void {
    $pro = changePro(holdsPirb: true);
    $request = changeRequest($pro, changeTrade('Painting'), DocumentType::Pirb, 'NEW-222');
    $admin = changeAdmin();

    expect(fn () => app(DecideProChange::class)->reject($admin, $request, '   '))->toThrow(ValidationException::class);

    app(DecideProChange::class)->reject($admin, $request, 'The certificate photo is unreadable.');

    expect($pro->trades()->count())->toBe(1)->and($pro->documents()->where('type', DocumentType::Pirb)->firstOrFail()->number)->toBe('OLD-111');
    expect($request->refresh()->status)->toBe(ProChangeStatus::Rejected)->and($request->decision_reason)->toBe('The certificate photo is unreadable.');

    $this->actingAs($pro->user);
    Livewire::test(Profile::class)->assertSee('Not approved')->assertSee('The certificate photo is unreadable.');
});

it('decides each request once (spec 021, AC27)', function (): void {
    $pro = changePro();
    $request = changeRequest($pro, changeTrade('Painting'));
    $admin = changeAdmin();
    app(DecideProChange::class)->approve($admin, $request);

    expect(fn () => app(DecideProChange::class)->approve($admin, $request))->toThrow(CannotChangeApplication::class);
    expect(fn () => app(DecideProChange::class)->reject($admin, $request, 'Too late'))->toThrow(CannotChangeApplication::class);
});

it('will not apply a change to a pro who is no longer approved (spec 021, AC27)', function (): void {
    $pro = changePro();
    $request = changeRequest($pro, changeTrade('Painting'));
    $pro->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(DecideProChange::class)->approve(changeAdmin(), $request))->toThrow(CannotChangeApplication::class);
    expect($pro->trades()->count())->toBe(1);
});

it('lets only vetting and super admins decide, never on their own profile (spec 021, AC27, AC31)', function (): void {
    $pro = changePro();
    $request = changeRequest($pro, changeTrade('Painting'));

    foreach ([Role::AdminSupport, Role::AdminFinance] as $role) {
        expect(fn () => app(DecideProChange::class)->approve(changeAdmin($role), $request))->toThrow(AuthorizationException::class);
    }
    expect(fn () => app(DecideProChange::class)->approve($pro->user, $request))->toThrow(AuthorizationException::class);

    $adminPro = changePro();
    $adminPro->user->assignRole(Role::AdminVetting->value);
    $own = changeRequest($adminPro, changeTrade('Tiling'));
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
    $request = changeRequest($pro, changeTrade('Painting'), DocumentType::Pirb, 'NEW-222');
    Filament::setCurrentPanel('admin');

    foreach ([Role::AdminSupport, Role::AdminFinance] as $role) {
        $this->actingAs(changeAdmin($role));
        Livewire::test(ListProChangeRequests::class)->assertForbidden();
    }
    $this->actingAs(User::factory()->customer()->create())->get('/admin/pro-changes')->assertForbidden();

    $this->actingAs(changeAdmin(Role::AdminSuper));
    Livewire::test(ListProChangeRequests::class)->assertCanSeeTableRecords([$request])->assertSee('Dlamini Plumbing')->assertSee('Add Painting')->assertSee('NEW-222')
        ->callAction(TestAction::make('approve')->table($request), data: ['expires_at' => now()->addYear()->toDateString()])->assertHasNoFormErrors();

    expect($request->refresh()->status)->toBe(ProChangeStatus::Approved);
});

it('rejects from the queue with a required reason (spec 021, AC27)', function (): void {
    $request = changeRequest(changePro(), changeTrade('Painting'));
    Filament::setCurrentPanel('admin');
    $this->actingAs(changeAdmin());

    Livewire::test(ListProChangeRequests::class)->callAction(TestAction::make('reject')->table($request), data: ['reason' => ''])->assertHasFormErrors(['reason']);
    expect($request->refresh()->isPending())->toBeTrue();

    Livewire::test(ListProChangeRequests::class)->callAction(TestAction::make('reject')->table($request), data: ['reason' => 'Not a trade we can verify yet.'])->assertHasNoFormErrors();

    expect($request->refresh()->decision_reason)->toBe('Not a trade we can verify yet.');
});

it('hides an admin\'s own pro requests from them in the queue (spec 021, AC27, AC31)', function (): void {
    $adminPro = changePro();
    $adminPro->user->assignRole(Role::AdminVetting->value);
    $own = changeRequest($adminPro, changeTrade('Tiling'));
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
