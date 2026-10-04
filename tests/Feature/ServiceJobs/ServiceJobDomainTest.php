<?php

declare(strict_types=1);

use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\ServiceJobs\Actions\CancelServiceJob;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Exceptions\TransitionNotAllowed;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Integrations\Fakes\FakeMessagingChannel;
use App\Models\Property;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\Suburb;
use App\Models\User;
use App\Settings\JobTimers;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->customer = User::factory()->customer()->create();
    $this->property = Property::factory()->for($this->customer)->create([
        'suburb_id' => Suburb::query()->where('slug', 'morningside')->value('id'),
    ]);
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
});

function checkedAnswers(Service $service, array $raw): array
{
    $stored = [];

    foreach ($service->questions as $question) {
        if (array_key_exists($question->key, $raw)) {
            $stored[$question->key] = ScopingAnswers::check($question, $raw[$question->key])['value'];
        }
    }

    return $stored;
}

/** Answers every question with its first valid option. */
function completeAnswers(Service $service): array
{
    $raw = [];

    foreach ($service->questions as $question) {
        $raw[$question->key] = match ($question->type) {
            QuestionType::SingleChoice => $question->options[0],
            QuestionType::MultiChoice => [$question->options[0]],
            QuestionType::YesNo => 'no',
            QuestionType::Number => '1',
            QuestionType::Text => 'Details',
        };
    }

    return checkedAnswers($service, $raw);
}

function draftFor(User $customer, Service $service, array $overrides = []): ServiceJob
{
    return app(SaveBookingDraft::class)->handle($customer, $service, null, new BookingData(
        answers: $overrides['answers'] ?? checkedAnswers($service, ['leak_location' => 'Tap', 'severity' => 'Dripping']),
        notes: $overrides['notes'] ?? 'Under the kitchen sink.',
        propertyPublicId: array_key_exists('property', $overrides) ? $overrides['property'] : test()->property->public_id,
        preferredDate: array_key_exists('date', $overrides) ? $overrides['date'] : CarbonImmutable::today()->addDays(2),
        timeWindow: array_key_exists('window', $overrides) ? $overrides['window'] : TimeWindow::Morning,
    ));
}

// --- State machine (AC16) -------------------------------------------------------

it('allows exactly the transitions in the lifecycle map', function (): void {
    $machine = new ServiceJobStateMachine;

    foreach (ServiceJobStatus::cases() as $from) {
        foreach (ServiceJobStatus::cases() as $to) {
            $allowed = in_array($to, ServiceJobStateMachine::transitions()[$from->value], true);
            expect($machine->canTransition($from, $to))->toBe($allowed, "{$from->value} → {$to->value}");
        }
    }

    expect(array_keys(ServiceJobStateMachine::transitions()))->toEqualCanonicalizing(array_map(fn (ServiceJobStatus $s): string => $s->value, ServiceJobStatus::cases()));
});

it('refuses transitions that are not in the map and records nothing', function (): void {
    $job = draftFor($this->customer, $this->leak);

    expect(fn (): ServiceJobEvent => (new ServiceJobStateMachine)->transition($job, ServiceJobStatus::Completed, 'x', ActorType::System, null))
        ->toThrow(TransitionNotAllowed::class);

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft)->and(ServiceJobEvent::query()->count())->toBe(0);
});

it('never lets job events be changed or deleted', function (): void {
    $job = draftFor($this->customer, $this->leak);
    app(PostServiceJob::class)->handle($this->customer, $job);
    $event = ServiceJobEvent::query()->sole();

    expect(fn () => $event->update(['event_type' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

// --- Posting (AC9–AC11) ----------------------------------------------------------

it('posts a draft: open, timestamps, one event, message after commit (AC9, AC11)', function (): void {
    $this->freezeTime();
    $job = draftFor($this->customer, $this->leak);

    app(PostServiceJob::class)->handle($this->customer, $job);

    $job->refresh();
    expect($job->status)->toBe(ServiceJobStatus::Open)
        ->and($job->posted_at->getTimestamp())->toBe(now()->getTimestamp())
        ->and($job->quote_window_ends_at->getTimestamp())->toBe(now()->addHours(72)->getTimestamp());

    $event = ServiceJobEvent::query()->sole();
    expect($event->from_status)->toBe(ServiceJobStatus::Draft)
        ->and($event->to_status)->toBe(ServiceJobStatus::Open)
        ->and($event->actor_type)->toBe(ActorType::Customer)
        ->and($event->actor_id)->toBe($this->customer->id);

    /** @var FakeMessagingChannel $messaging */
    $messaging = app(MessagingChannel::class);
    $messaging->assertSent('job_posted', fn (OutgoingMessage $message): bool => $message->phoneE164 === $this->customer->phone_e164);
});

it('uses the quote window from settings', function (): void {
    $this->freezeTime();
    app(JobTimers::class)->fill(['quote_window_hours' => 48])->save();
    $job = draftFor($this->customer, $this->leak);

    app(PostServiceJob::class)->handle($this->customer, $job);

    expect($job->fresh()->quote_window_ends_at->getTimestamp())->toBe(now()->addHours(48)->getTimestamp());
});

it('refuses to post when a guard fails and leaves the draft untouched (AC10)', function (Closure $arrange, string $message): void {
    $job = $arrange($this);

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, $job))->toThrow(CannotPostServiceJob::class, $message);

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft)->and(ServiceJobEvent::query()->count())->toBe(0);
    app(MessagingChannel::class)->assertNothingSent();
})->with([
    'phone not verified' => [function ($test): ServiceJob {
        $job = draftFor($test->customer, $test->leak);
        $test->customer->forceFill(['phone_verified_at' => null])->save();

        return $job;
    }, 'verify your phone'],
    'required answer missing' => [fn ($test): ServiceJob => draftFor($test->customer, $test->leak, ['answers' => checkedAnswers($test->leak, ['leak_location' => 'Tap'])]), 'required questions'],
    'no property' => [fn ($test): ServiceJob => draftFor($test->customer, $test->leak, ['property' => null]), 'one of your properties'],
    'inactive suburb' => [function ($test): ServiceJob {
        $test->property->suburb->update(['is_active' => false]);

        return draftFor($test->customer, $test->leak);
    }, "Sortd isn't in"],
    'deleted property' => [function ($test): ServiceJob {
        $job = draftFor($test->customer, $test->leak);
        $test->property->delete();

        return $job;
    }, 'one of your properties'],
    'inactive service' => [function ($test): ServiceJob {
        $job = draftFor($test->customer, $test->leak);
        $test->leak->update(['is_active' => false]);

        return $job;
    }, 'not available'],
    'no date' => [fn ($test): ServiceJob => draftFor($test->customer, $test->leak, ['date' => null]), 'choose when'],
    'date too far ahead' => [fn ($test): ServiceJob => draftFor($test->customer, $test->leak, ['date' => CarbonImmutable::today()->addDays(31)]), 'next 30 days'],
    'date in the past' => [fn ($test): ServiceJob => draftFor($test->customer, $test->leak, ['date' => CarbonImmutable::today()->subDay()]), 'next 30 days'],
    'urgent today on a non-emergency service' => [function ($test): ServiceJob {
        $coc = Service::query()->where('key', 'electrical_coc')->sole();

        return draftFor($test->customer, $coc, ['answers' => completeAnswers($coc), 'date' => CarbonImmutable::today(), 'window' => TimeWindow::Today]);
    }, 'emergency services'],
]);

it("never posts another customer's job or another customer's property", function (): void {
    $job = draftFor($this->customer, $this->leak);
    $stranger = User::factory()->customer()->create();

    expect(fn () => app(PostServiceJob::class)->handle($stranger, $job))->toThrow(AuthorizationException::class);

    $theirs = Property::factory()->create();
    $sneaky = draftFor($this->customer, $this->leak, ['property' => $theirs->public_id]);
    expect($sneaky->property_id)->toBeNull();
});

it('cannot post the same job twice', function (): void {
    $job = draftFor($this->customer, $this->leak);
    app(PostServiceJob::class)->handle($this->customer, $job);

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, $job))->toThrow(CannotPostServiceJob::class, 'already been posted');
    expect(ServiceJobEvent::query()->count())->toBe(1);
});

it('limits posts per day and drafts per customer', function (): void {
    foreach (range(1, 5) as $ignored) {
        draftFor($this->customer, $this->leak);
    }

    expect(fn (): ServiceJob => draftFor($this->customer, $this->leak))->toThrow(CannotPostServiceJob::class, 'too many unfinished');

    RateLimiter::hit('post-job:'.$this->customer->id, 86400);
    foreach (range(1, 9) as $ignored) {
        RateLimiter::hit('post-job:'.$this->customer->id, 86400);
    }

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, ServiceJob::query()->first()))->toThrow(CannotPostServiceJob::class, 'try again tomorrow');
});

// --- Answers and urgency (AC3, AC5, rules) ----------------------------------------

it('checks answers against the question type and options', function (string $key, mixed $raw, bool $ok): void {
    $question = ScopingQuestion::query()->where('key', $key)->firstOrFail();

    expect(ScopingAnswers::check($question, $raw)['ok'])->toBe($ok);
})->with([
    'single ok' => ['severity', 'Flooding', true],
    'single not an option' => ['severity', 'Tsunami', false],
    'single array' => ['severity', ['Flooding'], false],
    'multi ok' => ['damp_where', ['Ceiling', 'Bathroom'], true],
    'multi with unknown' => ['damp_where', ['Ceiling', 'Garage'], false],
    'multi empty' => ['damp_where', [], false],
    'yes/no ok' => ['overflowing', 'yes', true],
    'yes/no other' => ['overflowing', 'maybe', false],
    'number ok' => ['count', '4', true],
    'number negative' => ['count', '-1', false],
    'number text' => ['count', 'four', false],
]);

it('stores answers with the prompt as asked, unaffected by later catalogue edits', function (): void {
    $job = draftFor($this->customer, $this->leak);
    $this->leak->questions()->where('key', 'severity')->update(['prompt' => 'Changed later?']);

    expect($job->fresh()->scoping_answers['severity'])->toEqual(['prompt' => 'How bad is it?', 'type' => 'single_choice', 'answer' => 'Dripping']);
});

it('marks a job urgent from an urgent answer or the today window (AC5, AC7)', function (): void {
    $flooding = draftFor($this->customer, $this->leak, ['answers' => checkedAnswers($this->leak, ['leak_location' => 'Pipe', 'severity' => 'Flooding'])]);
    $today = draftFor($this->customer, $this->leak, ['date' => CarbonImmutable::today(), 'window' => TimeWindow::Today]);
    $normal = draftFor($this->customer, $this->leak);

    expect($flooding->urgency)->toBe(Urgency::Urgent)->and($today->urgency)->toBe(Urgency::Urgent)->and($normal->urgency)->toBe(Urgency::Normal);
});

// --- Stale drafts (AC12) -----------------------------------------------------------

it('cancels drafts untouched for 7 days, safely when run twice', function (): void {
    $old = draftFor($this->customer, $this->leak);
    $recent = draftFor($this->customer, $this->leak);
    $posted = draftFor($this->customer, $this->leak);
    app(PostServiceJob::class)->handle($this->customer, $posted);
    ServiceJob::query()->whereKey([$old->id, $posted->id])->update(['updated_at' => now()->subDays(8)]);

    $this->artisan('sortd:cancel-stale-drafts')->assertSuccessful();
    $this->artisan('sortd:cancel-stale-drafts')->assertSuccessful();

    expect($old->fresh()->status)->toBe(ServiceJobStatus::Cancelled)
        ->and($recent->fresh()->status)->toBe(ServiceJobStatus::Draft)
        ->and($posted->fresh()->status)->toBe(ServiceJobStatus::Open)
        ->and(ServiceJobEvent::query()->where('service_job_id', $old->id)->sole())
        ->actor_type->toBe(ActorType::System)
        ->to_status->toBe(ServiceJobStatus::Cancelled);
});

it('refuses notes over the limit when saving a draft', function (): void {
    expect(fn (): ServiceJob => draftFor($this->customer, $this->leak, ['notes' => str_repeat('x', 1001)]))->toThrow(CannotPostServiceJob::class, 'up to 1000 characters');
});

it('does not expire a draft the customer touched after it was picked', function (): void {
    $job = draftFor($this->customer, $this->leak);

    $cancelled = app(CancelServiceJob::class)->handle($job, ActorType::System, null, 'Draft expired', now()->subDays(7)->toImmutable());

    expect($cancelled)->toBeNull()->and($job->fresh()->status)->toBe(ServiceJobStatus::Draft);
});
