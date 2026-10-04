<?php

declare(strict_types=1);

use App\Contracts\Data\ScopingSuggestion;
use App\Contracts\ScopingAssistant;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Assistant\Actions\SummariseDraft;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\Support\Redactor;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Filament\Admin\Pages\AiSettingsPage;
use App\Filament\Admin\Pages\AiUsageReport;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Livewire\Booking\JobSummaryCard;
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
use Symfony\Component\HttpKernel\Exception\HttpException;

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

function summaryCard(): Testable
{
    return Livewire::test(JobSummaryCard::class, ['jobPublicId' => ServiceJob::query()->latest('id')->value('public_id')]);
}

/** Changes the notes from review, then walks back to review. */
function backToReviewWithNotes(Testable $wizard, string $notes): Testable
{
    return $wizard->call('change', 'notes')->set('notes', $notes)->call('next')
        ->call('next')->call('next')->call('next')->assertSet('step', 'review');
}

// --- Redaction (AC10) ---------------------------------------------------------------

it('replaces contact details, links and identity numbers before anything is sent (AC10)', function (): void {
    $text = 'Call me on 082 123 4567 or +27 65 910 7772, mail andy@example.com, see https://x.test/p and www.evil.test. ID 8001015009087, card 4111 1111 1111 1111.';

    $stripped = Redactor::strip($text);

    expect($stripped)->not->toContain('082 123 4567')->not->toContain('910 7772')->not->toContain('andy@example.com')
        ->not->toContain('https://')->not->toContain('www.evil')->not->toContain('8001015009087')->not->toContain('4111')
        ->toContain('[phone]')->toContain('[email]')->toContain('[link]')->toContain('[id number]')->toContain('[card number]');
});

it('also strips street addresses, slashed and non-ASCII digit phone numbers and spelled-out emails (AC10, security review)', function (string $text, string $leak, string $placeholder): void {
    $stripped = Redactor::strip($text);

    expect($stripped)->not->toContain($leak)->toContain($placeholder)
        ->and(Redactor::containsPersonalData($text))->toBeTrue();
})->with([
    'street address' => ['Blocked drain at 14 Smith Rd, Berea', '14 Smith Rd', '[address]'],
    'street address long form' => ['Come to 7 Private Lane please', '7 Private Lane', '[address]'],
    'slashed phone' => ['Call 082/123/4567', '082/123/4567', '[phone]'],
    'full-width digits' => ['Call ０８２ １２３ ４５６７ now', '１２３', '[phone]'],
    'spelled-out email' => ['Mail andy at gmail dot com', 'gmail dot com', '[email]'],
]);

it('keeps ordinary job details intact (AC10)', function (): void {
    $text = 'Built in 2019, 3 taps, 15mm pipe, about 2 by 3 m, leaking since 12/09/2026.';

    expect(Redactor::strip($text))->toBe($text);
});

it('strips personal data from free-text answers before summarising (AC10, security review)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->leak->questions()->create(['key' => 'anything_else', 'prompt' => 'Anything else?', 'type' => 'text', 'required' => false, 'sort' => 99]);
    $job = ServiceJob::factory()->forProperty($property)->create([
        'service_id' => $this->leak->id,
        'scoping_answers' => [
            'severity' => ['prompt' => 'How bad is it?', 'type' => 'single_choice', 'answer' => 'Dripping'],
            'anything_else' => ['prompt' => 'Anything else?', 'type' => 'text', 'answer' => 'Gate code 1234, call 082 123 4567 or andy@example.com'],
        ],
    ]);
    assistant()->willSummarise('Dripping tap.');

    app(SummariseDraft::class)->handle($customer, $job);

    expect(assistant()->summaryAnswersSeen())->toBe([[
        'anything_else' => 'Gate code 1234, call [phone] or [email]',
        'severity' => 'Dripping',
    ]]);
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

it('lets the customer turn down a suggestion and pick a service themselves (AC2)', function (): void {
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.92));

    Livewire::test(Welcome::class)->set('description', 'My kitchen tap is leaking')->call('find')
        ->call('chooseSomethingElse')
        ->assertDontSee('Is this what you need?')->assertSee('Choose the closest service')->assertSee('Plumbing');
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

it('puts the description into an existing draft without notes, on the notes step (AC4)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    $draft = ServiceJob::factory()->forProperty($property)->create(['service_id' => $this->leak->id, 'customer_notes' => null]);
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.9));
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('find');

    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])
        ->assertSet('jobPublicId', $draft->public_id)
        ->assertSet('step', 'notes')
        ->assertSet('notes', 'Tap drips all night long');
});

it('keeps the notes an existing draft already has (AC4)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    ServiceJob::factory()->forProperty($property)->create(['service_id' => $this->leak->id, 'customer_notes' => 'My own earlier notes']);
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.9));
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('find');

    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])
        ->assertSet('notes', 'My own earlier notes');
});

it('forgets an unused description after 30 minutes (AC4)', function (): void {
    assistant()->willSuggest(new ScopingSuggestion('plumbing', 'leak_repair', 0.9));
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('find');

    $this->travel(31)->minutes();

    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])->assertSet('notes', '');
});

it('drops an expired description from the session on the next page visit (AC4, security review)', function (): void {
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('find');
    $this->travel(31)->minutes();

    Livewire::test(Welcome::class);

    expect(session()->has(Welcome::DESCRIPTION_KEY))->toBeFalse();
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

it('records at most one throttled row per visitor per hour (security review)', function (): void {
    config()->set('sortd.ai.suggestions_per_hour', 1);
    $welcome = Livewire::test(Welcome::class)->set('description', 'My geyser is dripping');

    foreach (range(1, 5) as $attempt) {
        $welcome->call('find');
    }

    expect(AiUsage::query()->where('outcome', AiOutcome::Throttled)->count())->toBe(1)
        ->and(AiUsage::query()->count())->toBe(2);
});

it('limits signed-in customers by account rather than by network address (security review)', function (): void {
    config()->set('sortd.ai.suggestions_per_hour', 1);
    $this->actingAs(User::factory()->customer()->create());
    Livewire::test(Welcome::class)->set('description', 'My geyser is dripping')->call('find');

    $this->actingAs(User::factory()->customer()->create());
    Livewire::test(Welcome::class)->set('description', 'My geyser is dripping')->call('find');

    expect(assistant()->descriptionsSeen())->toHaveCount(2);
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

it('resets the daily budget at midnight Durban time (AC14)', function (): void {
    $this->travelTo(now('Africa/Johannesburg')->setTime(23, 30));
    $settings = app(AiSettings::class);
    $settings->daily_call_budget = 1;
    $settings->save();
    $welcome = Livewire::test(Welcome::class)->set('description', 'My geyser is dripping');

    $welcome->call('find');
    $welcome->call('find');
    expect(assistant()->descriptionsSeen())->toHaveCount(1);

    $this->travelTo(now('Africa/Johannesburg')->addDay()->setTime(0, 5));
    $welcome->call('find');
    expect(assistant()->descriptionsSeen())->toHaveCount(2);
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
    reviewStep($property);
    summaryCard()->assertDontSee('Job description for pros')->call('load')->assertDontSee('Written with AI help');

    assistant()->assertNothingSent();
    expect(AiUsage::query()->count())->toBe(0);
});

// --- Job summary (AC5–AC9, AC11, AC12) ----------------------------------------------

it('shows the description card on review, loading on its own (AC5)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);

    reviewStep($property)->assertSeeLivewire(JobSummaryCard::class);
    summaryCard()->assertSee('Job description for pros')->assertSeeHtml('wire:init="load"');
    assistant()->assertSummaryRequests(0);
});

it('summarises the draft at review from stripped notes and lets the customer check it (AC5, AC6, AC10)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Leak from a tap under the kitchen sink. Water is dripping.');

    reviewStep($property, 'Under the sink. Call 082 123 4567.');
    summaryCard()->call('load')
        ->assertSee('Job description for pros')
        ->assertSee('Leak from a tap under the kitchen sink. Water is dripping.')
        ->assertSee('Written with AI help, please check it');

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBe('Leak from a tap under the kitchen sink. Water is dripping.')
        ->and($job->ai_summary_source)->toBe(SummarySource::Ai)
        ->and($job->ai_summary_generated_at)->not->toBeNull()
        ->and(assistant()->descriptionsSeen())->toBe(['Under the sink. Call [phone].'])
        ->and(json_encode(assistant()->summaryAnswersSeen()))->not->toContain('Private Lane')->not->toContain($customer->first_name)->not->toContain($property->public_id)
        ->and(AiUsage::query()->sole()->service_job_id)->toBe($job->id);
});

it('discards a summary that breaks the rules and still lets the customer post (AC6, AC11)', function (?string $summary): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise($summary);

    $wizard = reviewStep($property);
    summaryCard()->call('load')->assertDontSee('Written with AI help')->assertDontSee('Job description for pros');

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
    'an address' => ['Leaking tap at 14 Smith Rd.'],
    'too long' => [str_repeat('Leaking tap. ', 50)],
]);

it('keeps a summary that mentions a date (AC11)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Tap has dripped since 2026-09-12 under the kitchen sink.');

    reviewStep($property);
    summaryCard()->call('load')->assertSee('Tap has dripped since 2026-09-12');
});

it('shows the summary as text, never as HTML (AC12)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Leaking <b>tap</b> under <script>alert(1)</script> the sink.');

    reviewStep($property);
    summaryCard()->call('load')
        ->assertSeeHtml('Leaking &lt;b&gt;tap&lt;/b&gt;')->assertDontSeeHtml('<script>alert(1)</script>');
});

it('only asks again when the answers or notes change (AC5)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property);
    summaryCard()->call('load')->call('load');
    assistant()->assertSummaryRequests(1);

    backToReviewWithNotes($wizard, 'Actually it is the shower.');
    summaryCard()->call('load');
    assistant()->assertSummaryRequests(2);
});

it('keeps the customer edit, never sends it to the assistant, and flags it when details change (AC7)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property);
    summaryCard()->call('load')
        ->call('edit')->assertSet('text', 'Dripping tap under the sink.')
        ->set('text', 'Kitchen tap drips. Please bring a washer kit.')
        ->call('save')->assertHasNoErrors()
        ->assertSee('Kitchen tap drips. Please bring a washer kit.')
        ->assertDontSee('Written with AI help');

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBe('Kitchen tap drips. Please bring a washer kit.')
        ->and($job->ai_summary_source)->toBe(SummarySource::CustomerEdited);

    backToReviewWithNotes($wizard, 'It is the bathroom tap too.');
    summaryCard()->call('load')
        ->assertSee('Kitchen tap drips. Please bring a washer kit.')
        ->assertSee('You changed some details. Check the description still fits.');

    assistant()->assertSummaryRequests(1);
    expect(assistant()->descriptionsSeen())->each->not->toContain('washer kit');
});

it('limits customer edits to 600 characters and refuses an empty description (AC7)', function (string $text): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    reviewStep($property);
    summaryCard()->call('load')->call('edit')
        ->set('text', $text)->call('save')->assertHasErrors(['text']);

    expect(ServiceJob::query()->sole()->ai_summary)->toBe('Dripping tap under the sink.');
})->with(['too long' => [str_repeat('a', 601)], 'empty' => ['  ']]);

it('throws away a summary for details that changed while it was being written (rules: concurrency)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.')
        ->whileSummarising(fn () => ServiceJob::query()->update(['customer_notes' => 'Changed meanwhile.']));

    reviewStep($property);
    summaryCard()->call('load')->assertDontSee('Dripping tap under the sink.');

    expect(ServiceJob::query()->sole()->ai_summary)->toBeNull();
});

it('throttles summaries per draft (AC14)', function (): void {
    config()->set('sortd.ai.summaries_per_hour', 1);
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property);
    summaryCard()->call('load');
    backToReviewWithNotes($wizard, 'Shower too.');
    summaryCard()->call('load');

    assistant()->assertSummaryRequests(1);
    expect(AiUsage::query()->where('outcome', AiOutcome::Throttled)->count())->toBe(1);
});

it('stores the shown summary at posting without calling the assistant (AC8)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property);
    summaryCard()->call('load');
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
    // Current details, so only the price rule can reject it.
    $job->forceFill(['ai_summary' => 'Fix it, about R900.', 'ai_summary_source' => SummarySource::Ai, 'ai_summary_input_hash' => JobSummaryInput::hash($job)])->save();

    app(PostServiceJob::class)->handle($customer, $job);

    expect($job->fresh()->ai_summary)->toBeNull()->and($job->fresh()->ai_summary_source)->toBe(SummarySource::None);
});

it('posts the customer\'s edited description as written (AC8)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');

    $wizard = reviewStep($property);
    summaryCard()->call('load')->call('edit')->set('text', 'Kitchen tap drips.')->call('save');
    $wizard->call('post')->assertHasNoErrors();

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBe('Kitchen tap drips.')->and($job->ai_summary_source)->toBe(SummarySource::CustomerEdited);
});

it('drops an AI summary written for older details at posting (AC8)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise('Dripping tap under the sink.');
    reviewStep($property);
    summaryCard()->call('load');
    $job = ServiceJob::query()->sole();
    $job->forceFill(['customer_notes' => 'Changed after the summary was written.'])->save();

    app(PostServiceJob::class)->handle($customer, $job);

    expect($job->fresh()->ai_summary)->toBeNull()->and($job->fresh()->ai_summary_source)->toBe(SummarySource::None);
});

it('shows safety advice and a guidance note at review for safety-relevant services only (AC9)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);

    reviewStep($property)
        ->assertSee('If water is flooding, close the main stopcock first.')
        ->assertSee('This is guidance, not a guarantee.');

    reviewStep($property, 'Sink is blocked.', $this->drain)
        ->assertDontSee('This is guidance, not a guarantee.');
});

it('shows the guidance note for a registered-electrician service without advice (AC9)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    $this->drain->update(['requires_registration' => 'electrical_registered_person', 'safety_advice' => []]);
    Pro::query()->sole()->documents()->forceCreate(['type' => 'electrical_registered_person', 'status' => 'verified', 'verified_at' => now()]);

    reviewStep($property, 'Sink is blocked.', $this->drain)->assertSee('This is guidance, not a guarantee.');
});

it('shows safety advice at review even when the assistant is off (AC9)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);

    reviewStep($property)
        ->assertSee('If water is flooding, close the main stopcock first.')
        ->assertSee('This is guidance, not a guarantee.');
    summaryCard()->assertDontSee('Job description for pros');
});

it('cannot summarise or edit another customer\'s draft (security)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    reviewStep($property);
    $publicId = ServiceJob::query()->sole()->public_id;

    $this->actingAs(User::factory()->customer()->create());
    Livewire::test(JobSummaryCard::class, ['jobPublicId' => $publicId])->assertNotFound();

    expect(ServiceJob::query()->sole()->ai_summary)->toBeNull();
    assistant()->assertSummaryRequests(0);
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

    expect(fn () => (new AiSettingsPage)->save())->toThrow(HttpException::class);

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
