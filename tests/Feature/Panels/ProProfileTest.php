<?php

declare(strict_types=1);

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Pros\Actions\UpdateProProfile;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Support\ProPublicProfile;
use App\Livewire\Account\Jobs\Show;
use App\Livewire\Account\ProProfile as CustomerProProfile;
use App\Livewire\Pros\Profile;
use App\Livewire\Pros\ProfilePreview;
use App\Livewire\Pros\Welcome;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function profilePro(array $attributes = []): Pro
{
    $user = User::factory()->pro()->create(['first_name' => 'Thabo', 'last_name' => 'Dlamini', 'email' => 'thabo.secret@example.com']);
    $pro = Pro::factory()->approved()->create([
        'user_id' => $user->id, 'business_name' => 'Dlamini Plumbing', 'bio' => 'Twenty years fixing Durban leaks.',
        'vat_number' => '4123456789', ...$attributes,
    ]);
    $pro->trades()->attach(Trade::factory()->create(['name' => 'Plumbing', 'registration' => RegistrationType::Pirb]));

    return $pro;
}

function profileDocument(Pro $pro, DocumentType $type, ?string $expires, DocumentStatus $status = DocumentStatus::Verified): void
{
    $document = $pro->documents()->firstOrNew(['type' => $type]);
    $document->forceFill(['status' => $status, 'number' => 'PIRB123', 'verified_at' => now(), 'expires_at' => $expires])->save();
}

function profileQuote(Pro $pro, User $customer, string $status = 'submitted'): Quote
{
    $job = ServiceJob::factory()->open()->create(['customer_id' => $customer->id]);

    return Quote::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, 'status' => $status]);
}

beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();
});

// --- The customer's view of a pro (AC17–AC19) --------------------------------------------

it('shows a quoting pro\'s profile to the customer and nothing private (spec 021, AC17)', function (): void {
    $pro = profilePro();
    $pro->user->forceFill(['phone_e164' => '+27821110000'])->save();
    profileDocument($pro, DocumentType::Pirb, now()->addYear()->toDateString());
    profileDocument($pro, DocumentType::IdDocument, null);
    profileDocument($pro, DocumentType::ProofOfAddress, null);
    $quote = profileQuote($pro, $this->customer);
    $this->actingAs($this->customer);

    Livewire::test(CustomerProProfile::class, ['quote' => $quote])
        ->assertSee('Dlamini Plumbing')->assertSee('Twenty years fixing Durban leaks.')
        ->assertSee('Plumbing')->assertSee('Works around')->assertSee('Musgrave')
        ->assertSee('On GetSorted since')->assertSee('PIRB registration')->assertSee('Valid')
        ->assertDontSee('+27821110000')->assertDontSee('thabo.secret@example.com')->assertDontSee('4123456789')
        ->assertDontSee('Identity document')->assertDontSee('Proof of address')->assertDontSee('PIRB123');
});

it('marks an expired registration as expired (spec 021, AC17)', function (): void {
    $pro = profilePro();
    profileDocument($pro, DocumentType::Pirb, now()->subDays(3)->toDateString());
    $this->actingAs($this->customer);

    Livewire::test(CustomerProProfile::class, ['quote' => profileQuote($pro, $this->customer)])->assertSee('PIRB registration')->assertSee('Expired');
});

it('does not list a registration that was never verified (spec 021, AC17)', function (): void {
    $pro = profilePro();
    profileDocument($pro, DocumentType::Pirb, now()->addYear()->toDateString(), DocumentStatus::Pending);
    $this->actingAs($this->customer);

    Livewire::test(CustomerProProfile::class, ['quote' => profileQuote($pro, $this->customer)])->assertDontSee('PIRB registration')->assertDontSee('Registrations');
});

it('invents no rating, review or count (spec 021, AC18)', function (): void {
    $pro = profilePro();
    $this->actingAs($this->customer);

    $html = Livewire::test(CustomerProProfile::class, ['quote' => profileQuote($pro, $this->customer)])->html();

    expect(strtolower(strip_tags($html)))->not->toContain('rating')->not->toContain('review')->not->toContain('stars')->not->toContain('jobs completed')->not->toContain('★');
});

it('builds the customer view from a short allow-list only (spec 021, AC17)', function (): void {
    expect(array_map(fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(ProPublicProfile::class))->getProperties()))
        ->toBe(['businessName', 'bio', 'trades', 'area', 'radiusKm', 'registrations', 'since', 'photoUrl']);
});

it('shows a profile only to the customer whose job has a live quote from that pro (spec 021, AC19)', function (): void {
    $pro = profilePro();
    $quote = profileQuote($pro, $this->customer);

    $this->actingAs(User::factory()->customer()->create())->get(route('account.pro-profile', $quote))->assertNotFound();
    $this->actingAs($pro->user)->get(route('account.pro-profile', $quote))->assertRedirect();

    auth()->logout();
    $this->get(route('account.pro-profile', $quote))->assertRedirect(route('login'));

    $this->actingAs($this->customer)->get(route('account.pro-profile', $quote))->assertOk()->assertSee('Dlamini Plumbing');
});

it('hides the profile once the quote is withdrawn, declined or expired, or the pro is suspended (spec 021, AC19)', function (string $status): void {
    $pro = profilePro();
    $quote = profileQuote($pro, $this->customer, $status);

    $this->actingAs($this->customer)->get(route('account.pro-profile', $quote))->assertNotFound();
})->with(['withdrawn', 'declined', 'expired', 'superseded']);

it('hides the profile of a pro who is no longer approved (spec 021, AC19)', function (): void {
    $pro = profilePro();
    $quote = profileQuote($pro, $this->customer);
    $pro->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($this->customer)->get(route('account.pro-profile', $quote))->assertNotFound();
});

it('links the pro\'s name on a quote to their profile (spec 021, AC17)', function (): void {
    $pro = profilePro();
    $quote = profileQuote($pro, $this->customer);
    $this->actingAs($this->customer);

    Livewire::test(Show::class, ['job' => $quote->serviceJob])->assertSee(route('account.pro-profile', $quote), false)->assertSee('View profile');
});

// --- The pro's own profile (AC25–AC26, AC28) ---------------------------------------------

it('lets an approved pro edit their bio, and rejects empty or oversized text (spec 021, AC26)', function (): void {
    $pro = profilePro();
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->assertSet('bio', 'Twenty years fixing Durban leaks.')
        ->set('bio', '  Family plumbers since 2004.  ')->call('saveBio')->assertHasNoErrors()->assertSee('Saved.');
    expect($pro->refresh()->bio)->toBe('Family plumbers since 2004.');

    Livewire::test(Profile::class)->set('bio', '')->call('saveBio')->assertHasErrors('bio');
    Livewire::test(Profile::class)->set('bio', str_repeat('a', 501))->call('saveBio')->assertHasErrors('bio');
    expect($pro->refresh()->bio)->toBe('Family plumbers since 2004.');
});

it('saves the weekly cap, accepts empty for no limit and refuses nonsense (spec 021, AC26)', function (): void {
    $pro = profilePro();
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->set('weeklyCap', '8')->call('saveCap')->assertHasNoErrors();
    expect($pro->refresh()->weekly_job_cap)->toBe(8);

    foreach (['0', '51', 'abc', '-2', '3.5'] as $bad) {
        Livewire::test(Profile::class)->set('weeklyCap', $bad)->call('saveCap')->assertHasErrors('cap');
    }
    expect($pro->refresh()->weekly_job_cap)->toBe(8);

    Livewire::test(Profile::class)->set('weeklyCap', '')->call('saveCap')->assertHasNoErrors();
    expect($pro->refresh()->weekly_job_cap)->toBeNull();
});

it('changes how far the pro travels at once, and refuses a distance outside 1 to 50 km (spec 021, AC26)', function (): void {
    $pro = profilePro();
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->assertSet('radiusKm', 15)->set('radiusKm', 25)->call('saveWorkArea')->assertHasNoErrors()->assertSee('Saved.');
    expect($pro->refresh()->service_radius_km)->toBe(25);

    foreach ([0, 51, -3] as $bad) {
        Livewire::test(Profile::class)->set('radiusKm', $bad)->call('saveWorkArea')->assertHasErrors('radiusKm');
    }
    expect($pro->refresh()->service_radius_km)->toBe(25);
});

it('moves the work area to a new Google address at once, and refuses one outside South Africa (spec 021, AC26)', function (): void {
    $pro = profilePro();
    $update = app(UpdateProProfile::class);

    $update->workArea($pro->user, $pro, new GeocodedAddress('1 Lagoon Drive, Umhlanga Rocks', 'Umhlanga', -29.7261, 31.0848, areaNames: ['Umhlanga']), 'place-umhlanga', 20);

    $pro->refresh();
    expect($pro->base_area_label)->toBe('Umhlanga')->and($pro->service_radius_km)->toBe(20)->and($pro->base_place_id)->toBe('place-umhlanga');

    expect(fn () => $update->workArea($pro->user, $pro, new GeocodedAddress('Paris', 'Paris', 48.85, 2.35), 'place-paris', 20))->toThrow(ValidationException::class);
    expect($pro->refresh()->base_area_label)->toBe('Umhlanga');
});

it('logs each profile edit without storing the text (spec 021, AC26)', function (): void {
    $pro = profilePro();
    app(UpdateProProfile::class)->bio($pro->user, $pro, 'A private little bio');
    app(UpdateProProfile::class)->weeklyCap($pro->user, $pro, 5);

    $entries = Activity::query()->where('description', 'pro profile edited')->orderBy('id')->get();

    expect($entries)->toHaveCount(2)->and($entries->pluck('properties.field')->all())->toBe(['bio', 'weekly_job_cap']);
    expect(json_encode($entries->pluck('properties')->all()))->not->toContain('private little bio');
});

it('lets only the pro themselves edit, and only while approved (spec 021, AC26, AC31)', function (): void {
    $pro = profilePro();
    $rival = Pro::factory()->approved()->create();

    expect(fn () => app(UpdateProProfile::class)->bio($rival->user, $pro, 'Hijacked'))->toThrow(HttpException::class);

    $pro->forceFill(['status' => 'suspended'])->save();
    expect(fn () => app(UpdateProProfile::class)->bio($pro->user, $pro, 'Still editing'))->toThrow(HttpException::class);
    expect($pro->refresh()->bio)->toBe('Twenty years fixing Durban leaks.');
});

it('sends a pro who is not approved away from the profile pages (spec 021, AC2)', function (): void {
    $applicant = User::factory()->pro()->create();
    Pro::factory()->create(['user_id' => $applicant->id, 'status' => 'submitted']);

    $this->actingAs($applicant)->get(route('pros.profile'))->assertRedirect(route('pros.status'));
    $this->actingAs($applicant)->get(route('pros.profile.preview'))->assertRedirect(route('pros.status'));
    $this->actingAs($this->customer)->get(route('pros.profile'))->assertRedirect(route('pros.join'));
});

it('previews the profile exactly as customers see it, with no contact details (spec 021, AC28)', function (): void {
    $pro = profilePro();
    $pro->user->forceFill(['phone_e164' => '+27821110000'])->save();
    profileDocument($pro, DocumentType::Pirb, now()->addYear()->toDateString());
    $this->actingAs($pro->user);

    Livewire::test(ProfilePreview::class)
        ->assertSee('Dlamini Plumbing')->assertSee('Twenty years fixing Durban leaks.')->assertSee('Plumbing')->assertSee('PIRB registration')
        ->assertSee('never shown')
        ->assertDontSee('+27821110000')->assertDontSee('thabo.secret@example.com')->assertDontSee('4123456789');

    $quote = profileQuote($pro, $this->customer);
    $customerHtml = $this->actingAs($this->customer)->get(route('account.pro-profile', $quote))->getContent();
    $previewHtml = $this->actingAs($pro->user)->get(route('pros.profile.preview'))->getContent();
    $core = fn (string $html): string => preg_replace('/\s+/', ' ', strip_tags(substr($html, (int) strpos($html, '<article'), (int) strpos($html, '</article>') - (int) strpos($html, '<article'))));

    expect($core($previewHtml))->toBe($core($customerHtml));
});

// --- Expiry warnings (AC29) --------------------------------------------------------------

it('warns on Profile and Today about a registration that expires within 30 days or has expired (spec 021, AC29)', function (): void {
    $pro = profilePro();
    profileDocument($pro, DocumentType::Pirb, now()->addDays(10)->toDateString());
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->assertSee('A registration needs attention')->assertSee('Expires');
    Livewire::test(Welcome::class)->assertSee('PIRB registration expires on')->assertSee('Renew it');

    profileDocument($pro, DocumentType::Pirb, now()->subDay()->toDateString());
    Livewire::test(Profile::class)->assertSee('Expired');
    Livewire::test(Welcome::class)->assertSee('PIRB registration has expired');
});

it('stays quiet about registrations with plenty of time left or none at all (spec 021, AC29)', function (): void {
    $pro = profilePro();
    profileDocument($pro, DocumentType::Pirb, now()->addDays(90)->toDateString());
    $this->actingAs($pro->user);

    Livewire::test(Profile::class)->assertDontSee('A registration needs attention')->assertSee('Valid until');
    Livewire::test(Welcome::class)->assertDontSee('expires on');
});

it('prompts for a bio on Today only when it is missing (spec 021, AC22)', function (): void {
    $pro = profilePro();
    $this->actingAs($pro->user);

    Livewire::test(Welcome::class)->assertDontSee('Finish your profile');

    $pro->forceFill(['bio' => null])->save();
    Livewire::test(Welcome::class)->assertSee('Finish your profile')->assertSee(route('pros.profile'), false);
});
