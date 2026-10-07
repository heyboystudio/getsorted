<?php

declare(strict_types=1);

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Actions\CheckReference;
use App\Domain\Pros\Actions\DecideApplication;
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
use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Admin\Resources\ProApplications\Pages\ListProApplications;
use App\Filament\Admin\Resources\ProApplications\Pages\ViewProApplication;
use App\Filament\Admin\Resources\ProApplications\RelationManagers\DocumentsRelationManager;
use App\Filament\Admin\Resources\ProApplications\RelationManagers\ReferencesRelationManager;
use App\Livewire\Pros\Application;
use App\Livewire\Pros\Status;
use App\Models\Pro;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    Storage::fake('media');
});

function screensApplicant(): User
{
    return User::factory()->pro()->create(['first_name' => 'Bongani']);
}

function screensAdmin(Role $role = Role::AdminVetting): User
{
    $admin = User::factory()->create();
    $admin->assignRole($role->value);

    return $admin;
}

/** A submitted application built through the domain actions. */
function screensSubmitted(): Pro
{
    $user = screensApplicant();
    $pro = app(StartApplication::class)->handle($user);
    $steps = app(SaveApplicationStep::class);
    $steps->business($user, $pro, new BusinessDetails('Bongani Fix-It', BusinessType::Company, '4123456789'));
    $steps->trades($user, $pro, [tradeOf('plumbing')->id]);
    $steps->base($user, $pro, new GeocodedAddress('10 Musgrave Road, Berea, Durban', 'Berea', -29.8460, 31.0050, '10 Musgrave Road', '4001', ['Berea']), 'fake-berea', 15);
    $steps->references($user, $pro, [new ReferenceData('Thandi Mkhize', '082 123 4567', 'Customer'), new ReferenceData('Sipho Ndlovu', '071 234 5678', 'Supplier')], true);
    $steps->bio($user, $pro, 'Reliable plumber.');
    $steps->consent($user, $pro, true);
    foreach ([DocumentType::IdDocument, DocumentType::ProofOfAddress, DocumentType::ProfilePhoto] as $type) {
        app(StoreProDocument::class)->handle($user, $pro, $type, UploadedFile::fake()->image($type->value.'.jpg', 40, 30));
    }
    app(SubmitApplication::class)->handle($user, $pro->fresh());

    return $pro->fresh();
}

// --- Pro screens (AC1, AC4–AC6) ------------------------------------------------------

it('offers to start, continue or check an application from the pro welcome page (screens)', function (): void {
    $user = screensApplicant();
    $this->actingAs($user)->get(route('pros.welcome'))->assertSee('Start your application')->assertSee(route('pros.apply'), false);

    app(StartApplication::class)->handle($user);
    $this->get(route('pros.welcome'))->assertSee('Continue your application');

    $submitted = screensSubmitted();
    $this->actingAs($submitted->user)->get(route('pros.welcome'))->assertSee('Check your application')->assertSee(route('pros.status'), false);
});

it('shows the whole application on one natural page, with no steps or progress bar (founder 2026-10-07)', function (): void {
    $this->actingAs(screensApplicant());

    Livewire::test(Application::class)
        ->assertSee('Tell us about your business')->assertSee('Your business')->assertSee('What work do you do?')->assertSee('Where do you work from?')
        ->assertSee('Your documents')->assertSee('References')->assertSee('About you')
        ->assertSee('Save progress')->assertSee('Send for review')
        ->assertDontSee('Step 1')->assertDontSee('Save and continue')->assertDontSee('Back');
});

it('fills in the application on one page, saves progress quietly and submits it (AC1, AC4)', function (): void {
    $user = screensApplicant();
    $this->actingAs($user);
    $page = Livewire::test(Application::class);

    // Nothing started yet: saving progress asks for nothing, but sending for review lists everything missing at once.
    $page->call('saveProgress')->assertHasNoErrors()->assertSee('Saved.')
        ->call('submit')->assertHasErrors(['businessName', 'businessType', 'tradeIds', 'addressQuery', 'bio']);

    $page->set('businessName', 'Bongani Fix-It')->set('businessType', 'sole_trader')->call('saveProgress')->assertHasNoErrors();
    expect(Pro::query()->sole()->business_name)->toBe('Bongani Fix-It');

    $page->set('tradeIds', [tradeOf('plumbing')->id])
        ->set('addressQuery', 'Musgrave')->call('pickAddress', 'fake-berea')->set('radiusKm', 15)
        ->call('saveProgress')->assertHasNoErrors();

    foreach (['id_document', 'proof_of_address', 'profile_photo'] as $type) {
        $page->set('uploads.'.$type, UploadedFile::fake()->image($type.'.jpg', 40, 30))->assertHasNoErrors();
    }

    $page->set('references', [
        ['name' => 'Thandi Mkhize', 'phone' => '082 123 4567', 'relationship' => 'Customer'],
        ['name' => 'Sipho Ndlovu', 'phone' => '071 234 5678', 'relationship' => 'Supplier'],
    ])->set('refereesAgreed', true)->set('bio', 'Reliable plumber.')->set('consent', true)
        ->call('submit')->assertHasNoErrors()->assertRedirect(route('pros.status'));

    expect(Pro::query()->sole()->status)->toBe(ProStatus::Submitted);
});

it('shows the registration part only when a saved trade has one, and describes it neutrally (AC3)', function (): void {
    $user = screensApplicant();
    $this->actingAs($user);
    $page = Livewire::test(Application::class)->assertDontSee('Your registration');

    $page->set('tradeIds', [tradeOf('painting')->id])->call('saveProgress')->assertDontSee('Your registration');
    $page->set('tradeIds', [tradeOf('electrical')->id])->call('saveProgress')
        ->assertSee('Your registration')->assertSee('Registered electrician')->assertSee('You can skip this and add it later')
        ->assertDontSee('badge')->assertDontSee('verified')->assertDontSee('not verified')
        ->set('registrationNumbers.electrical_registered_person', 'ER-555')
        ->set('uploads.electrical_registered_person', UploadedFile::fake()->image('er.jpg', 20, 20))
        ->call('saveProgress')->assertHasNoErrors();

    expect(Pro::query()->sole()->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->sole()->number)->toBe('ER-555');
});

it('shows the pro their own saved address in words, without internal wording, and only offers the search after Change', function (): void {
    $user = screensApplicant();
    $this->actingAs($user);
    $pro = app(StartApplication::class)->handle($user);
    app(SaveApplicationStep::class)->base($user, $pro, new GeocodedAddress('10 Musgrave Road, Berea, Durban', 'Berea', -29.8460, 31.0050, '10 Musgrave Road', '4001', ['Berea']), 'fake-berea', 15);

    Livewire::test(Application::class)
        ->assertSee('10 Musgrave Road, Berea, Durban')->assertDontSee('Saved:')->assertDontSee('Search again')->assertDontSee('Your address')
        ->call('$set', 'changingAddress', true)->assertSee('Your address');
});

it('keeps customers and guests out of the application (AC1)', function (): void {
    $this->get(route('pros.apply'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->customer()->create())->get(route('pros.apply'))->assertRedirect(route('pros.join'));
});

it('sends a submitted pro to the status page instead of the form (AC4)', function (): void {
    $pro = screensSubmitted();

    $this->actingAs($pro->user)->get(route('pros.apply'))->assertRedirect(route('pros.status'));
});

it('shows the status, checklist and vetting messages, but never internal notes (AC5)', function (): void {
    $pro = screensSubmitted();
    $admin = screensAdmin();
    $id = $pro->documents()->where('type', DocumentType::IdDocument)->sole();
    app(VetDocument::class)->flag($admin, $id, 'The photo is blurred.');
    app(VetDocument::class)->verify($admin, $pro->documents()->where('type', DocumentType::ProofOfAddress)->sole(), null);
    app(CheckReference::class)->handle($admin, $pro->references()->first(), ReferenceOutcome::Positive, 'INTERNAL: said he is slow');
    app(DecideApplication::class)->requestChanges($admin, $pro->fresh(), 'Please upload a clearer ID.');

    $this->actingAs($pro->user);
    Livewire::test(Status::class)
        ->assertSee('Changes requested')
        ->assertSee('Please upload a clearer ID.')
        ->assertSee('The photo is blurred.')
        ->assertSee('Verified')
        ->assertSee('Fix these items')
        ->assertDontSee('INTERNAL')
        ->assertDontSee('082 123 4567');
});

it('lets a pro fix only flagged documents from the form after changes are requested (AC6)', function (): void {
    $pro = screensSubmitted();
    $admin = screensAdmin();
    app(VetDocument::class)->flag($admin, $pro->documents()->where('type', DocumentType::IdDocument)->sole(), 'Blurred.');
    app(DecideApplication::class)->requestChanges($admin, $pro->fresh(), 'Clearer ID please.');
    $this->actingAs($pro->user);

    Livewire::test(Application::class)
        ->assertSee('Your documents')->assertDontSee('Tell us about your business')->assertSee('Update your application')
        ->set('uploads.proof_of_address', UploadedFile::fake()->image('poa.jpg', 20, 20))->assertForbidden();

    Livewire::test(Application::class)
        ->set('uploads.id_document', UploadedFile::fake()->image('id.jpg', 30, 20))->assertHasNoErrors()
        ->call('submit')->assertRedirect(route('pros.status'));

    expect($pro->fresh()->status)->toBe(ProStatus::Submitted);
});

// --- Document links (AC8, policies) --------------------------------------------------

it('serves a document only through a valid signed link to its owner or a vetting admin (AC8)', function (): void {
    $pro = screensSubmitted();
    $document = $pro->documents()->where('type', DocumentType::IdDocument)->sole();
    $url = $document->temporaryUrl();

    expect($url)->not->toContain('/'.$document->id.'?')->toContain($document->public_id);

    $this->actingAs($pro->user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(screensApplicant())->get($url)->assertNotFound();
    $this->actingAs(User::factory()->customer()->create())->get($url)->assertNotFound();

    $vetting = screensAdmin();
    $this->actingAs($vetting)->get($url)->assertForbidden();
    $this->actingAs($vetting)->withSession([AdminLogin::SESSION_KEY => $vetting->id])->get($url)->assertOk();

    $support = screensAdmin(Role::AdminSupport);
    $this->actingAs($support)->withSession([AdminLogin::SESSION_KEY => $support->id])->get($url)->assertNotFound();

    $this->travel(6)->minutes();
    $this->actingAs($pro->user)->get($url)->assertForbidden();
});

it('downloads PDFs instead of showing them inline (AC8)', function (): void {
    $user = screensApplicant();
    $pro = app(StartApplication::class)->handle($user);
    $document = app(StoreProDocument::class)->handle($user, $pro, DocumentType::ProofOfAddress, UploadedFile::fake()->createWithContent('bill.pdf', "%PDF-1.4\n%%EOF"));

    $this->actingAs($user)->get($document->temporaryUrl())->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=document.pdf');
});

// --- Admin (AC7–AC11) ----------------------------------------------------------------

it('lists submitted applications, oldest first, to vetting and super admins only (AC7)', function (): void {
    $older = screensSubmitted();
    $this->travel(1)->day();
    $newer = screensSubmitted();
    Filament::setCurrentPanel('admin');

    foreach ([Role::AdminSupport, Role::AdminFinance] as $role) {
        $this->actingAs(screensAdmin($role));
        Livewire::test(ListProApplications::class)->assertForbidden();
        Livewire::test(ViewProApplication::class, ['record' => $older->public_id])->assertForbidden();
    }
    $this->actingAs(User::factory()->customer()->create())->get('/admin/applications')->assertForbidden();

    $this->actingAs(screensAdmin(Role::AdminSuper));
    Livewire::test(ListProApplications::class)
        ->assertCanSeeTableRecords([$older, $newer], inOrder: true)
        ->assertSee('Bongani Fix-It')
        ->assertDontSee('082 123 4567');
});

it('shows the whole application with document links and records checks (AC8)', function (): void {
    $pro = screensSubmitted();
    $admin = screensAdmin();
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ViewProApplication::class, ['record' => $pro->public_id])
        ->assertSee('Bongani Fix-It')->assertSee('4123456789')->assertSee('Plumbing')->assertSee('Berea')->assertSee('Reliable plumber.');

    $document = $pro->documents()->where('type', DocumentType::IdDocument)->sole();
    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pro, 'pageClass' => ViewProApplication::class])
        ->assertSee('Identity document')
        ->callAction(TestAction::make('verify')->table($document), data: [])
        ->assertHasNoFormErrors();
    expect($document->fresh()->status)->toBe(DocumentStatus::Verified)->and($document->fresh()->verified_by)->toBe($admin->id);

    $address = $pro->documents()->where('type', DocumentType::ProofOfAddress)->sole();
    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pro, 'pageClass' => ViewProApplication::class])
        ->callAction(TestAction::make('flag')->table($address), data: ['flag_message' => ''])->assertHasFormErrors(['flag_message']);
    expect($address->fresh()->status)->toBe(DocumentStatus::Pending);
    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $pro, 'pageClass' => ViewProApplication::class])
        ->callAction(TestAction::make('flag')->table($address), data: ['flag_message' => 'Older than 3 months.'])->assertHasNoFormErrors();
    expect($address->fresh()->status)->toBe(DocumentStatus::Flagged);

    $reference = $pro->references()->first();
    Livewire::test(ReferencesRelationManager::class, ['ownerRecord' => $pro, 'pageClass' => ViewProApplication::class])
        ->assertSee('082 123 4567')
        ->callAction(TestAction::make('record')->table($reference), data: ['outcome' => 'positive', 'note' => 'Good work.'])
        ->assertHasNoFormErrors();
    expect($reference->fresh()->outcome)->toBe(ReferenceOutcome::Positive)->and($reference->fresh()->checked_by)->toBe($admin->id);
});

it('approves, requests changes and rejects from the application view (AC9, AC10)', function (): void {
    $admin = screensAdmin();
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $ready = screensSubmitted();
    foreach ($ready->documents as $document) {
        app(VetDocument::class)->verify($admin, $document, null);
    }
    foreach ($ready->references as $reference) {
        app(CheckReference::class)->handle($admin, $reference, ReferenceOutcome::Positive, null);
    }
    Livewire::test(ViewProApplication::class, ['record' => $ready->public_id])->callAction('approve')->assertHasNoErrors();
    expect($ready->fresh()->status)->toBe(ProStatus::Approved);

    $notReady = screensSubmitted();
    Livewire::test(ViewProApplication::class, ['record' => $notReady->public_id])->callAction('approve')->assertNotified();
    expect($notReady->fresh()->status)->toBe(ProStatus::Submitted);

    Livewire::test(ViewProApplication::class, ['record' => $notReady->public_id])
        ->callAction('requestChanges', data: ['reason' => ''])->assertHasActionErrors(['reason']);
    expect($notReady->fresh()->status)->toBe(ProStatus::Submitted);
    Livewire::test(ViewProApplication::class, ['record' => $notReady->public_id])
        ->callAction('requestChanges', data: ['reason' => 'Upload a clearer ID.'])->assertHasNoActionErrors();
    expect($notReady->fresh()->status)->toBe(ProStatus::ChangesRequested);

    $third = screensSubmitted();
    Livewire::test(ViewProApplication::class, ['record' => $third->public_id])
        ->callAction('reject', data: ['reason' => 'Could not confirm references.'])->assertHasNoActionErrors();
    expect($third->fresh()->status)->toBe(ProStatus::Rejected);
});

it('suspends and reinstates an approved pro from the view (AC11)', function (): void {
    $admin = screensAdmin(Role::AdminSuper);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
    $pro = screensSubmitted();
    foreach ($pro->documents as $document) {
        app(VetDocument::class)->verify($admin, $document, null);
    }
    foreach ($pro->references as $reference) {
        app(CheckReference::class)->handle($admin, $reference, ReferenceOutcome::Positive, null);
    }
    app(DecideApplication::class)->approve($admin, $pro->fresh());

    Livewire::test(ViewProApplication::class, ['record' => $pro->public_id])
        ->assertActionHidden('approve')
        ->callAction('suspend', data: ['reason' => 'Complaint under review.'])->assertHasNoActionErrors();
    expect($pro->fresh()->status)->toBe(ProStatus::Suspended);

    Livewire::test(ViewProApplication::class, ['record' => $pro->public_id])->callAction('reinstate');
    expect($pro->fresh()->status)->toBe(ProStatus::Approved);
});

it('lets admins correct trades and radius, with a log entry (rules)', function (): void {
    $admin = screensAdmin();
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
    $pro = screensSubmitted();

    Livewire::test(ViewProApplication::class, ['record' => $pro->public_id])
        ->callAction('editCoverage', data: ['trade_ids' => [tradeOf('painting')->id], 'radius_km' => 22])
        ->assertHasNoActionErrors();

    expect($pro->fresh()->trades->pluck('key')->all())->toBe(['painting'])
        ->and($pro->fresh()->service_radius_km)->toBe(22)
        ->and(DB::table('activity_log')->where('description', 'pro_coverage_edited')->count())->toBe(1);
});

it('hides an admin\'s own application from them in the vetting screens (security review)', function (): void {
    $admin = screensAdmin();
    $admin->assignRole(Role::Pro->value);
    $own = screensSubmitted();
    $own->forceFill(['user_id' => $admin->id])->save();
    $other = screensSubmitted();
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListProApplications::class)->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$own]);
    Livewire::test(ViewProApplication::class, ['record' => $own->public_id])->assertNotFound();
});

it('shows an expired registration as expired on the status page (AC12)', function (): void {
    $user = screensApplicant();
    $electrical = tradeOf('electrical');
    $pro = app(StartApplication::class)->handle($user);
    app(SaveApplicationStep::class)->trades($user, $pro, [$electrical->id]);
    $document = app(StoreProDocument::class)->handle($user, $pro, DocumentType::ElectricalRegisteredPerson, UploadedFile::fake()->image('er.jpg', 20, 20));
    $document->forceFill(['status' => DocumentStatus::Verified, 'verified_at' => now(), 'expires_at' => now()->subDay()])->save();

    $this->actingAs($user);
    Livewire::test(Status::class)->assertSee('Expired');
});

it('lets a pro fix a flagged registration from the form, even after re-uploading first (code review, AC6)', function (): void {
    $user = screensApplicant();
    $electrical = tradeOf('electrical');
    $pro = app(StartApplication::class)->handle($user);
    $steps = app(SaveApplicationStep::class);
    $steps->business($user, $pro, new BusinessDetails('Spark', BusinessType::Company, null));
    $steps->trades($user, $pro, [tradeOf('plumbing')->id, $electrical->id]);
    $steps->base($user, $pro, new GeocodedAddress('10 Musgrave Road, Berea, Durban', 'Berea', -29.8460, 31.0050, '10 Musgrave Road', '4001', ['Berea']), 'fake-berea', 15);
    $steps->references($user, $pro, [new ReferenceData('Thandi Mkhize', '082 123 4567', 'Customer'), new ReferenceData('Sipho Ndlovu', '071 234 5678', 'Supplier')], true);
    $steps->bio($user, $pro, 'Electrician.');
    $steps->consent($user, $pro, true);
    $steps->registration($user, $pro, DocumentType::ElectricalRegisteredPerson, 'ER-OLD');
    foreach ([DocumentType::IdDocument, DocumentType::ProofOfAddress, DocumentType::ProfilePhoto, DocumentType::ElectricalRegisteredPerson] as $type) {
        app(StoreProDocument::class)->handle($user, $pro, $type, UploadedFile::fake()->image($type->value.'.jpg', 40, 30));
    }
    app(SubmitApplication::class)->handle($user, $pro->fresh());
    $admin = screensAdmin();
    app(VetDocument::class)->verify($admin, $pro->documents()->where('type', DocumentType::IdDocument)->sole(), null);
    app(VetDocument::class)->flag($admin, $pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->sole(), 'Certificate and number do not match.');
    app(DecideApplication::class)->requestChanges($admin, $pro->fresh(), 'Fix the electrical registration.');
    $this->actingAs($user);

    Livewire::test(Application::class)
        ->assertSee('Your registration')
        ->set('uploads.electrical_registered_person', UploadedFile::fake()->image('new.jpg', 30, 30))->assertHasNoErrors()
        ->set('registrationNumbers.electrical_registered_person', 'ER-NEW')
        ->call('saveProgress')->assertHasNoErrors()
        ->call('submit')->assertRedirect(route('pros.status'));

    $registration = $pro->documents()->where('type', DocumentType::ElectricalRegisteredPerson)->sole();
    expect($pro->fresh()->status)->toBe(ProStatus::Submitted)
        ->and($registration->number)->toBe('ER-NEW')
        ->and($registration->status)->toBe(DocumentStatus::Pending)
        ->and($registration->flag_message)->toBeNull()
        ->and($pro->documents()->where('type', DocumentType::IdDocument)->sole()->status)->toBe(DocumentStatus::Verified);
});

it('offers to apply again once the wait is over (code review)', function (): void {
    $pro = screensSubmitted();
    app(DecideApplication::class)->reject(screensAdmin(), $pro, 'Not yet.');
    $this->actingAs($pro->user);

    $this->get(route('pros.welcome'))->assertDontSee('Apply again');
    $this->travel(91)->days();
    $this->get(route('pros.welcome'))->assertSee('Apply again')->assertSee(route('pros.apply'), false);
    Livewire::test(Status::class)->assertSee('Apply again');
});
