<?php

declare(strict_types=1);

use App\Contracts\Data\ScopingSuggestion;
use App\Contracts\ScopingAssistant;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\Support\Redactor;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Filament\Admin\Pages\AiSettingsPage;
use App\Filament\Admin\Pages\AiUsageReport;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Livewire\Booking\Wizard;
use App\Livewire\Welcome;
use App\Models\AiUsage;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\Suburb;
use App\Models\Trade;
use App\Models\User;
use App\Settings\AiSettings;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
    $this->drain = Service::query()->where('key', 'blocked_drain')->sole();
    $musgrave = Suburb::query()->where('slug', 'musgrave')->sole();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach([$this->leak->id, $this->drain->id]);
    $pro->serviceAreas()->attach($musgrave);

    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();

    $this->assistant = app(ScopingAssistant::class);
});

function assistant(): FakeScopingAssistant
{
    return test()->assistant;
}

function aiCustomer(): array
{
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['street_address' => '7 Private Lane', 'suburb_id' => Suburb::query()->where('slug', 'musgrave')->value('id')]);

    return [$customer, $property];
}

/** Walks a signed-in customer to the review step of a leak repair booking. */
function reviewStep(Property $property, string $notes = 'Water under the sink.', ?Service $service = null): Testable
{
    $service ??= test()->leak;
    $wizard = Livewire::test(Wizard::class, ['trade' => test()->plumbing, 'service' => $service])
        ->call('selectSuburb', 'musgrave')->call('next');

    foreach ($service->questions as $question) {
        $wizard->call('choose', $question->options[0] ?? 'yes');
    }

    return $wizard->set('notes', $notes)->call('next')
        ->call('next')
        ->call('selectProperty', $property->public_id)->call('next')
        ->set('timeWindow', 'morning')->set('preferredDate', now()->addDays(3)->toDateString())->call('next')
        ->assertSet('step', 'review');
}

// --- Redaction (AC10) ---------------------------------------------------------------

it('replaces contact details, links and identity numbers before anything is sent (AC10)', function (): void {
    $text = 'Call me on 082 123 4567 or +27 65 910 7772, mail andy@example.com, see https://x.test/p and www.evil.test. ID 8001015009087, card 4111 1111 1111 1111.';

    $stripped = Redactor::strip($text);

    expect($stripped)->not->toContain('082 123 4567')->not->toContain('910 7772')->not->toContain('andy@example.com')
        ->not->toContain('https://')->not->toContain('www.evil')->not->toContain('8001015009087')->not->toContain('4111')
        ->toContain('[phone]')->toContain('[email]')->toContain('[link]')->toContain('[id number]')->toContain('[card number]');
});

// --- Describe your problem (AC1–AC4) -------------------------------------------------

it('suggests a valid service from a stripped description and waits for the customer to confirm (AC1, AC2, AC10)', function (): void {
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.92));

    Livewire::test(Welcome::class)
        ->set('description', 'My kitchen tap is leaking, call 082 123 4567')
        ->call('find')
        ->assertHasNoErrors()
        ->assertSee('Is this what you need?')->assertSee('Leak repair')
        ->assertSee(route('booking.start', ['trade' => 'plumbing', 'service' => 'leak_repair']), false)
        ->assertNoRedirect();

    expect(assistant()->descriptionsSeen())->toBe(['My kitchen tap is leaking, call [phone]'])
        ->and(ServiceJob::query()->count())->toBe(0);
});

it('falls back to choosing a service when the suggestion cannot be used (AC3)', function (?ScopingSuggestion $suggestion, AiOutcome $outcome): void {
    if ($suggestion?->serviceKey === 'blocked_drain') {
        $this->drain->update(['is_active' => false]);
    }
    assistant()->willSuggest($suggestion);

    Livewire::test(Welcome::class)
        ->set('description', 'Something is wrong in my bathroom')
        ->call('find')
        ->assertHasNoErrors()
        ->assertDontSee('Is this what you need?')
        ->assertSee('Choose the closest service')
        ->assertSet('description', 'Something is wrong in my bathroom');

    expect(AiUsage::query()->sole()->outcome)->toBe($outcome);
})->with([
    'no suggestion' => [null, AiOutcome::Invalid],
    'unknown service' => [new ScopingSuggestion('plumbing', 'ignore_all_rules', 0.99), AiOutcome::Invalid],
    'service from another trade' => [new ScopingSuggestion('electrical', 'leak_repair', 0.99), AiOutcome::Invalid],
    'inactive service' => [new ScopingSuggestion('plumbing', 'blocked_drain', 0.99), AiOutcome::Invalid],
    'low confidence' => [new ScopingSuggestion('plumbing', 'leak_repair', 0.3), AiOutcome::Invalid],
]);

it('falls back without an error page when the provider times out or fails (AC3, AC13)', function (bool $timedOut, AiOutcome $outcome): void {
    assistant()->willFail($timedOut);

    Livewire::test(Welcome::class)
        ->set('description', 'My geyser is dripping')
        ->call('find')
        ->assertHasNoErrors()->assertSee('Choose the closest service');

    expect(AiUsage::query()->sole()->outcome)->toBe($outcome);
})->with([[true, AiOutcome::Timeout], [false, AiOutcome::Error]]);

it('asks for 10 to 500 characters before calling the assistant (AC1)', function (string $description): void {
    Livewire::test(Welcome::class)->set('description', $description)->call('find')->assertHasErrors(['description']);

    assistant()->assertNothingSent();
})->with(['too short' => ['leak'], 'too long' => [str_repeat('a', 501)]]);

it('offers the description as the starting notes once the customer confirms (AC4)', function (): void {
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.9));
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('find');

    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])
        ->assertSet('step', 'coverage')
        ->assertSet('notes', 'Tap drips all night long')
        ->assertSet('answers', []);

    // Used once, then gone.
    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])->assertSet('notes', '');
});

it('forgets an unused description after 30 minutes (AC4)', function (): void {
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.9));
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('find');

    $this->travel(31)->minutes();

    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])->assertSet('notes', '');
});

// --- Limits and switches (AC14, AC15) ------------------------------------------------

it('throttles suggestions per visitor without calling the provider (AC14)', function (): void {
    config()->set('sortd.ai.suggestions_per_hour', 1);
    $welcome = Livewire::test(Welcome::class)->set('description', 'My geyser is dripping');

    $welcome->call('find');
    $welcome->call('find')->assertHasNoErrors()->assertSee('Choose the closest service');

    expect(assistant()->descriptionsSeen())->toHaveCount(1)
        ->and(AiUsage::query()->where('outcome', AiOutcome::Throttled)->count())->toBe(1);
});

it('stops calling the provider once the daily budget is spent (AC14)', function (): void {
    $settings = app(AiSettings::class);
    $settings->daily_call_budget = 2;
    $settings->save();
    AiUsage::factory()->count(2)->create();

    Livewire::test(Welcome::class)->set('description', 'My geyser is dripping')->call('find')->assertSee('Choose the closest service');

    assistant()->assertNothingSent();
    expect(AiUsage::query()->latest('id')->first()->outcome)->toBe(AiOutcome::Throttled);
});

it('sends one request at a time per visitor (rules: double tap)', function (): void {
    $lock = Cache::lock('assistant:suggest:'.hash_hmac('sha256', '127.0.0.1', (string) config('app.key')), 30);
    $lock->get();

    Livewire::test(Welcome::class)->set('description', 'My geyser is dripping')->call('find')->assertSee('Choose the closest service');

    assistant()->assertNothingSent();
    $lock->release();
});

it('goes straight to manual choices when the assistant is switched off (AC15)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();
    [, $property] = aiCustomer();

    Livewire::test(Welcome::class)->set('description', 'My geyser is dripping')->call('find')->assertSee('Choose the closest service');
    $this->actingAs($property->user);
    reviewStep($property)->call('loadSummary')->assertDontSee('Written with AI help');

    assistant()->assertNothingSent();
    expect(AiUsage::query()->count())->toBe(0);
});

// --- Job summary (AC5–AC9, AC11, AC12) ----------------------------------------------

it('summarises the draft at review from stripped notes and lets the customer check it (AC5, AC6, AC10)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Leak from a tap under the kitchen sink. Water is dripping.');

    reviewStep($property, 'Under the sink. Call 082 123 4567.')
        ->call('loadSummary')
        ->assertSee('Job description for pros')
        ->assertSee('Leak from a tap under the kitchen sink. Water is dripping.')
        ->assertSee('Written with AI help, please check it');

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBe('Leak from a tap under the kitchen sink. Water is dripping.')
        ->and($job->ai_summary_source)->toBe(SummarySource::Ai)
        ->and($job->ai_summary_generated_at)->not->toBeNull()
        ->and(assistant()->descriptionsSeen())->toBe(['Under the sink. Call [phone].'])
        ->and(AiUsage::query()->sole()->service_job_id)->toBe($job->id);
});

it('discards a summary that breaks the rules and still lets the customer post (AC6, AC11)', function (?string $summary): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise($summary);

    $wizard = reviewStep($property)->call('loadSummary')->assertDontSee('Written with AI help');

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBeNull()
        ->and(AiUsage::query()->sole()->outcome)->toBe(AiOutcome::Invalid);

    $wizard->call('post')->assertHasNoErrors();
    expect($job->fresh()->ai_summary_source)->toBe(SummarySource::None);
})->with([
    'nothing' => [null],
    'blank' => ['   '],
    'a price' => ['Leaking tap. Should cost about R450 to fix.'],
    'a phone number' => ['Leaking tap. Call the customer on 082 123 4567.'],
    'an email' => ['Leaking tap. Email me@example.com.'],
    'a link' => ['Leaking tap. See https://evil.test now.'],
    'too long' => [str_repeat('Leaking tap. ', 50)],
]);

it('shows the summary as text, never as HTML (AC12)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Leaking <b>tap</b> under <script>alert(1)</script> the sink.');

    reviewStep($property)->call('loadSummary')
        ->assertSeeHtml('Leaking &lt;b&gt;tap&lt;/b&gt;')->assertDontSeeHtml('<script>alert(1)</script>');
});

it('only asks again when the answers or notes change (AC5)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property)->call('loadSummary')->call('loadSummary');
    assistant()->assertSummaryRequests(1);

    $wizard->call('change', 'notes')->set('notes', 'Actually it is the shower.')->call('next')
        ->call('next')->call('next')->call('next')->assertSet('step', 'review')
        ->call('loadSummary');
    assistant()->assertSummaryRequests(2);
});

it('keeps the customer edit, never sends it to the assistant, and flags it when details change (AC7)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property)->call('loadSummary')
        ->call('editSummary')
        ->set('summaryText', 'Kitchen tap drips. Please bring a washer kit.')
        ->call('saveSummary')->assertHasNoErrors()
        ->assertSee('Kitchen tap drips. Please bring a washer kit.')
        ->assertDontSee('Written with AI help');

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBe('Kitchen tap drips. Please bring a washer kit.')
        ->and($job->ai_summary_source)->toBe(SummarySource::CustomerEdited);

    $wizard->call('change', 'notes')->set('notes', 'It is the bathroom tap too.')->call('next')
        ->call('next')->call('next')->call('next')->assertSet('step', 'review')
        ->call('loadSummary')
        ->assertSee('Kitchen tap drips. Please bring a washer kit.')
        ->assertSee('You changed some details. Check the description still fits.');

    assistant()->assertSummaryRequests(1);
    expect(assistant()->descriptionsSeen())->each->not->toContain('washer kit');
});

it('limits customer edits to 600 characters and refuses an empty description (AC7)', function (string $text): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    reviewStep($property)->call('loadSummary')->call('editSummary')
        ->set('summaryText', $text)->call('saveSummary')->assertHasErrors(['summaryText']);

    expect(ServiceJob::query()->sole()->ai_summary)->toBe('Dripping tap under the sink.');
})->with(['too long' => [str_repeat('a', 601)], 'empty' => ['  ']]);

it('throws away a summary for details that changed while it was being written (rules: concurrency)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.')
        ->whileSummarising(fn () => ServiceJob::query()->update(['customer_notes' => 'Changed meanwhile.']));

    reviewStep($property)->call('loadSummary')->assertDontSee('Dripping tap under the sink.');

    expect(ServiceJob::query()->sole()->ai_summary)->toBeNull();
});

it('throttles summaries per draft (AC14)', function (): void {
    config()->set('sortd.ai.summaries_per_hour', 1);
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    reviewStep($property)->call('loadSummary')
        ->call('change', 'notes')->set('notes', 'Shower too.')->call('next')
        ->call('next')->call('next')->call('next')->call('loadSummary');

    assistant()->assertSummaryRequests(1);
    expect(AiUsage::query()->where('outcome', AiOutcome::Throttled)->count())->toBe(1);
});

it('stores the shown summary at posting without calling the assistant (AC8)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property)->call('loadSummary');
    assistant()->assertSummaryRequests(1);
    $wizard->call('post');

    assistant()->assertSummaryRequests(1);
    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBe('Dripping tap under the sink.')
        ->and($job->ai_summary_source)->toBe(SummarySource::Ai);
});

it('re-checks a stored AI summary at posting and drops it if it breaks the rules (AC8)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    reviewStep($property);
    $job = ServiceJob::query()->sole();
    $job->forceFill(['ai_summary' => 'Fix it, about R900.', 'ai_summary_source' => SummarySource::Ai])->save();

    app(PostServiceJob::class)->handle($customer, $job);

    expect($job->fresh()->ai_summary)->toBeNull()->and($job->fresh()->ai_summary_source)->toBe(SummarySource::None);
});

it('shows safety advice and a guidance note beside the summary for safety-relevant services (AC9)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Pipe is leaking.');

    reviewStep($property)->call('loadSummary')
        ->assertSee('If water is flooding, close the main stopcock first.')
        ->assertSee('This is guidance, not a guarantee.');

    reviewStep($property, 'Sink is blocked.', $this->drain)->call('loadSummary')
        ->assertDontSee('This is guidance, not a guarantee.');
});

it('cannot summarise or edit another customer\'s draft (security)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    $wizard = reviewStep($property)->set('summaryText', 'Hijacked');

    $this->actingAs(User::factory()->customer()->create());
    $wizard->call('saveSummary')->assertNotFound();

    expect(ServiceJob::query()->sole()->ai_summary)->toBeNull();
});

// --- Usage records (AC13) -----------------------------------------------------------

it('records usage without any customer text and prunes it after the retention period (AC13)', function (): void {
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.9));
    Livewire::test(Welcome::class)->set('description', 'Secret detail about my geyser leaking')->call('find');

    $usage = AiUsage::query()->sole();
    expect($usage->purpose)->toBe(AiPurpose::SuggestService)
        ->and($usage->outcome)->toBe(AiOutcome::Ok)
        ->and($usage->provider)->toBe('fake')->and($usage->model)->toBe('fake-model')
        ->and($usage->input_tokens)->toBe(120)->and($usage->output_tokens)->toBe(40)
        ->and($usage->latency_ms)->toBeGreaterThanOrEqual(0)
        ->and(json_encode($usage->getAttributes()))->not->toContain('Secret detail');

    $old = AiUsage::factory()->create(['created_at' => now()->subDays(91)]);
    $this->artisan('model:prune', ['--model' => [AiUsage::class]])->assertSuccessful();
    $this->artisan('model:prune', ['--model' => [AiUsage::class]])->assertSuccessful();

    expect(AiUsage::query()->find($old->id))->toBeNull()->and(AiUsage::query()->find($usage->id))->not->toBeNull();
});

// --- Admin (AC16, settings) ----------------------------------------------------------

it('shows admins the stored summary and its source on the job (AC16)', function (Role $role): void {
    $job = ServiceJob::factory()->open()->create(['service_id' => $this->leak->id]);
    $job->forceFill(['ai_summary' => 'Dripping tap under the sink.', 'ai_summary_source' => SummarySource::CustomerEdited, 'customer_notes' => 'My own words'])->save();
    $admin = User::factory()->create();
    $admin->assignRole($role->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])
        ->assertSee('Dripping tap under the sink.')->assertSee('Edited by the customer')->assertSee('My own words');
})->with([Role::AdminSuper, Role::AdminSupport]);

it('shows AI usage totals to admins only, without customer text (AC16)', function (): void {
    AiUsage::factory()->count(3)->create(['purpose' => AiPurpose::SuggestService, 'outcome' => AiOutcome::Ok, 'input_tokens' => 100, 'output_tokens' => 10]);
    AiUsage::factory()->create(['purpose' => AiPurpose::Summarise, 'outcome' => AiOutcome::Timeout]);

    $this->actingAs(User::factory()->customer()->create())->get('/admin/ai-usage')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $rows = (new AiUsageReport)->usage();
    $suggest = $rows->firstWhere('purpose', AiPurpose::SuggestService->value);
    expect($suggest->calls)->toBe(3)->and($suggest->input_tokens)->toBe(300)
        ->and($rows->firstWhere('outcome', AiOutcome::Timeout->value)->calls)->toBe(1);
    Livewire::test(AiUsageReport::class)->assertOk()->assertSee('Service suggestions');
});

it('lets only super-admins change the AI settings (decision 2)', function (): void {
    $support = User::factory()->create();
    $support->assignRole(Role::AdminSupport->value);
    $this->actingAs($support);
    Filament::setCurrentPanel('admin');
    expect(AiSettingsPage::canAccess())->toBeFalse();

    $super = User::factory()->create();
    $super->assignRole(Role::AdminSuper->value);
    $this->actingAs($super);
    expect(AiSettingsPage::canAccess())->toBeTrue();

    Livewire::test(AiSettingsPage::class)
        ->fillForm(['enabled' => false, 'suggestion_min_confidence' => 0.7, 'daily_call_budget' => 500, 'usage_retention_days' => 60])
        ->call('save')->assertHasNoFormErrors();

    $settings = app(AiSettings::class)->refresh();
    expect($settings->enabled)->toBeFalse()->and($settings->daily_call_budget)->toBe(500)
        ->and($settings->suggestion_min_confidence)->toBe(0.7)->and($settings->usage_retention_days)->toBe(60);

    Livewire::test(AiSettingsPage::class)
        ->fillForm(['enabled' => true, 'suggestion_min_confidence' => 1.5, 'daily_call_budget' => -1, 'usage_retention_days' => 0])
        ->call('save')->assertHasFormErrors(['suggestion_min_confidence', 'daily_call_budget', 'usage_retention_days']);
});

it('ships with the assistant switched off and a 2,000 call budget (decisions 1 and 2)', function (): void {
    DB::table('settings')->where('group', 'ai')->delete();
    (require database_path('settings/2026_10_04_120000_create_ai_settings.php'))->up();

    $settings = app(AiSettings::class)->refresh();
    expect($settings->enabled)->toBeFalse()
        ->and($settings->daily_call_budget)->toBe(2000)
        ->and($settings->suggestion_min_confidence)->toBe(0.6)
        ->and($settings->usage_retention_days)->toBe(90);
});
