<?php

declare(strict_types=1);

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
use App\Livewire\Booking\Thread;
use App\Livewire\Welcome;
use App\Models\AiUsage;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use App\Settings\AiSettings;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->plumbing = tradeOf('plumbing');
    proNear(['plumbing'], 2);

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
    $property = Property::factory()->for($customer)->create(['street_address' => '7 Private Lane']);

    return [$customer, $property];
}

/** Walks a signed-in customer through the booking thread to the summary (spec 017), with these notes. */
function reviewStep(Property $property, string $notes = 'Water under the sink.', ?Trade $trade = null): Testable
{
    $trade ??= test()->plumbing;
    $thread = bookUpToSummary(describeJob(threadFor($trade), $trade), $property);

    return backToReviewWithNotes($thread, $notes);
}

function summaryCard(): Testable
{
    return Livewire::test(JobSummaryCard::class, ['jobPublicId' => ServiceJob::query()->latest('id')->value('public_id')]);
}

/** Changes the notes from the summary, which returns straight to it. */
function backToReviewWithNotes(Testable $thread, string $notes): Testable
{
    return $thread->call('change', 'details')->set('notesDraft', $notes)->call('saveNotes')->assertSet('stage', 'summary');
}

/** The same walk with Siya switched off: the customer's own words become the job notes. */
function manualReview(Property $property): Testable
{
    $thread = threadFor(test()->plumbing)->set('message', 'Tap drips all night long')->call('send')->call('startBooking');

    return bookUpToSummary($thread, $property)->assertSet('stage', 'summary');
}

/** One chat turn from a visitor: the scripted Siya just records nothing and says hello. */
function chatTurn(Testable $thread, string $message = 'My geyser is dripping'): Testable
{
    return $thread->set('message', $message)->call('send');
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

it('strips personal data from facts and notes before summarising (AC10, security review)', function (): void {
    [$customer, $property] = aiCustomer();
    $job = ServiceJob::factory()->forProperty($property)->create([
        'trade_id' => $this->plumbing->id,
        'facts' => [['id' => 'f1', 'text' => 'Gate code 1234, call 082 123 4567 or andy@example.com', 'turn' => 1]],
    ]);
    assistant()->willSummarise('Dripping tap.');

    app(SummariseDraft::class)->handle($customer, $job);

    expect(assistant()->summaryFactsSeen())->toBe([['Gate code 1234, call [phone] or [email]']]);
});

// --- The home page hands over what the customer typed (AC4; spec 017 AC1) ------------

it('starts the booking thread with the home-page description, used once (AC4; spec 017 AC1)', function (): void {
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('start')->assertRedirect(route('book'));

    threadFor()->assertSee('Tap drips all night long');

    expect(session()->has(Welcome::DESCRIPTION_KEY))->toBeFalse();
});

it('forgets an unused description after 30 minutes (AC4)', function (): void {
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('start');

    $this->travel(31)->minutes();

    threadFor()->assertDontSee('Tap drips all night long');
});

it('drops an expired description from the session on the next page visit (AC4, security review)', function (): void {
    Livewire::test(Welcome::class)->set('description', 'Tap drips all night long')->call('start');
    $this->travel(31)->minutes();

    Livewire::test(Welcome::class);

    expect(session()->has(Welcome::DESCRIPTION_KEY))->toBeFalse();
});

// --- Limits and switches (AC14, AC15) ------------------------------------------------

it('throttles chat per visitor without calling the provider (AC14)', function (): void {
    config()->set('sortd.ai.chat_messages_per_hour', 1);
    $thread = threadFor();

    chatTurn($thread);
    chatTurn($thread, 'And my bath too')->assertSee('Siya is busy right now');

    expect(assistant()->chatRequests())->toHaveCount(1)
        ->and(AiUsage::query()->where('outcome', AiOutcome::Throttled)->count())->toBe(1);
});

it('records at most one throttled row per visitor per hour (security review)', function (): void {
    config()->set('sortd.ai.chat_messages_per_hour', 1);
    $thread = threadFor();

    foreach (range(1, 5) as $attempt) {
        chatTurn($thread, "Message number {$attempt}");
    }

    expect(AiUsage::query()->where('outcome', AiOutcome::Throttled)->count())->toBe(1)
        ->and(AiUsage::query()->count())->toBe(2);
});

it('limits signed-in customers by account rather than by network address (security review)', function (): void {
    config()->set('sortd.ai.chat_messages_per_hour', 1);
    $this->actingAs(User::factory()->customer()->create());
    chatTurn(threadFor());

    $this->actingAs(User::factory()->customer()->create());
    session()->forget(Thread::SESSION_KEY);
    chatTurn(threadFor());

    expect(assistant()->chatRequests())->toHaveCount(2);
});

it('stops calling the provider once the daily budget is spent (AC14)', function (): void {
    $settings = app(AiSettings::class);
    $settings->daily_call_budget = 2;
    $settings->save();
    AiUsage::factory()->count(2)->create();

    chatTurn(threadFor())->assertSee('Siya is busy right now');

    expect(assistant()->chatRequests())->toBe([])
        ->and(AiUsage::query()->latest('id')->first()->outcome)->toBe(AiOutcome::Throttled);
});

it('resets the daily budget at midnight Durban time (AC14)', function (): void {
    $this->travelTo(now('Africa/Johannesburg')->setTime(23, 30));
    $settings = app(AiSettings::class);
    $settings->daily_call_budget = 1;
    $settings->save();
    $thread = threadFor();

    chatTurn($thread);
    chatTurn($thread, 'Second message');
    expect(assistant()->chatRequests())->toHaveCount(1);

    $this->travelTo(now('Africa/Johannesburg')->addDay()->setTime(0, 5));
    $thread->call('retry');
    expect(assistant()->chatRequests())->toHaveCount(2);
});

it('counts one budget unit per Siya turn, however many model steps it takes (spec 020)', function (): void {
    $settings = app(AiSettings::class);
    $settings->daily_call_budget = 2;
    $settings->save();

    chatTurn(threadFor());

    expect(AiUsage::query()->where('outcome', '!=', AiOutcome::Throttled)->count())->toBe(1);
});

it('keeps the booking usable by hand when the assistant is switched off (AC15)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();
    [, $property] = aiCustomer();
    $this->actingAs($property->user);

    chatTurn(threadFor($this->plumbing), 'Tap drips all night long')->assertSet('notes', 'Tap drips all night long')->assertSee('Continue to book');
    session()->forget(Thread::SESSION_KEY);
    manualReview($property);
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
        ->and(json_encode(assistant()->summaryFactsSeen()))->not->toContain('Private Lane')->not->toContain($customer->first_name)->not->toContain($property->public_id)
        ->and(AiUsage::query()->where('purpose', AiPurpose::Summarise)->sole()->service_job_id)->toBe($job->id);
});

it('discards a summary that breaks the rules and still lets the customer post (AC6, AC11)', function (?string $summary): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    assistant()->willSummarise($summary);

    $wizard = reviewStep($property);
    summaryCard()->call('load')->assertDontSee('Written with AI help')->assertDontSee('Job description for pros');

    $job = ServiceJob::query()->sole();
    expect($job->ai_summary)->toBeNull()
        ->and(AiUsage::query()->where('purpose', AiPurpose::Summarise)->sole()->outcome)->toBe(AiOutcome::Invalid);

    $wizard->call('confirmBooking')->assertHasNoErrors();
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

it('only asks again when the facts or notes change (AC5)', function (): void {
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
    $wizard->call('confirmBooking');

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
    $wizard->call('confirmBooking')->assertHasNoErrors();

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

it('shows the trade’s safety advice and a guidance note at review for safety-relevant trades only (AC9)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);

    reviewStep($property)
        ->assertSee('If water is flooding, close the main stopcock first.')
        ->assertSee('This is guidance, not a guarantee.');

    proNear(['painting'], 2);
    reviewStep($property, 'Peeling walls.', tradeOf('painting'))
        ->assertDontSee('This is guidance, not a guarantee.');
});

it('shows the guidance note for a registered-electrician trade even without advice (AC9)', function (): void {
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);
    $electrical = tradeOf('electrical');
    $electrical->update(['safety_advice' => []]);
    proNear(['electrical'], 2);

    reviewStep($property, 'Light trips the breaker.', $electrical)->assertSee('This is guidance, not a guarantee.');
});

it('shows safety advice at review even when the assistant is off (AC9)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();
    [$customer, $property] = aiCustomer();
    $this->actingAs($customer);

    manualReview($property)
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
    chatTurn(threadFor(), 'Secret detail about my geyser leaking');

    $usage = AiUsage::query()->sole();
    expect($usage->purpose)->toBe(AiPurpose::Chat)
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
    $job = ServiceJob::factory()->open()->create(['trade_id' => tradeOf('plumbing')->id]);
    $job->forceFill(['ai_summary' => 'Dripping tap under the sink.', 'ai_summary_source' => SummarySource::CustomerEdited, 'customer_notes' => 'My own words'])->save();
    $admin = User::factory()->create();
    $admin->assignRole($role->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])
        ->assertSee('Dripping tap under the sink.')->assertSee('Edited by the customer')->assertSee('My own words');
})->with([Role::AdminSuper, Role::AdminSupport]);

it('shows AI usage totals to admins only, without customer text (AC16)', function (): void {
    AiUsage::factory()->count(3)->create(['purpose' => AiPurpose::Chat, 'outcome' => AiOutcome::Ok, 'input_tokens' => 100, 'output_tokens' => 10]);
    AiUsage::factory()->create(['purpose' => AiPurpose::Summarise, 'outcome' => AiOutcome::Timeout]);

    $this->actingAs(User::factory()->customer()->create())->get('/admin/ai-usage')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $rows = (new AiUsageReport)->usage();
    $suggest = $rows->firstWhere('purpose', AiPurpose::Chat->value);
    expect($suggest->calls)->toBe(3)->and($suggest->input_tokens)->toBe(300)
        ->and($rows->firstWhere('outcome', AiOutcome::Timeout->value)->calls)->toBe(1);
    Livewire::test(AiUsageReport::class)->assertOk()->assertSee('Siya chat');
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
