<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Models\Property;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Booking wizard (spec 005): questions one per screen → notes → property →
 * when → review → post. Guests answer questions, then log in at the property
 * step; logged-in customers autosave a draft at every step.
 */
#[Layout('components.layouts.app')]
final class Wizard extends Component
{
    public const array STEPS = ['questions', 'notes', 'property', 'when', 'review'];

    private const string RESUME_KEY = 'booking.resume';

    #[Locked]
    public int $serviceId;

    #[Locked]
    public ?string $jobPublicId = null;

    #[Locked]
    public string $step = 'questions';

    #[Locked]
    public int $questionIndex = 0;

    /** @var array<string, mixed> raw answers keyed by question key */
    public array $answers = [];

    public string $notes = '';

    public ?string $propertyPublicId = null;

    public string $preferredDate = '';

    public string $timeWindow = '';

    public function mount(?Trade $trade = null, ?Service $service = null, ?ServiceJob $job = null): void
    {
        if ($job instanceof ServiceJob && $job->exists) {
            $this->resumeDraft($job);

            return;
        }

        abort_unless($trade instanceof Trade && $service instanceof Service && $service->trade_id === $trade->id && $service->is_active && $trade->is_active, 404);
        $this->serviceId = $service->id;

        /** @var array{service_id?: int, answers?: array<string, mixed>, notes?: string}|null $resume */
        $resume = session()->pull(self::RESUME_KEY);

        if (is_array($resume) && ($resume['service_id'] ?? null) === $service->id) {
            $this->answers = $resume['answers'] ?? [];
            $this->notes = $resume['notes'] ?? '';
            $this->step = 'property';
        }

        // Continue an existing draft for this service instead of starting another (drafts are capped).
        $existing = $this->user()?->hasRole(Role::Customer->value) === true ? ServiceJob::query()
            ->where('customer_id', $this->user()->id)->where('service_id', $service->id)
            ->where('status', ServiceJobStatus::Draft)->latest('updated_at')->first() : null;

        if ($existing instanceof ServiceJob && ! is_array($resume)) {
            $this->resumeDraft($existing);

            return;
        }

        if ($existing instanceof ServiceJob) {
            $this->jobPublicId = $existing->public_id;
        }

        $this->prepareMultiChoiceAnswers();

        // A draft is only created once the customer has answered something (or came back from login).
        if ($this->isCustomer() && is_array($resume)) {
            $this->autosave();
        }
    }

    /** Tap answers (single choice, yes/no) save and move on. */
    public function choose(string $value): void
    {
        $question = $this->currentQuestion();

        if (! $question instanceof ScopingQuestion) {
            return;
        }

        $this->answers[$question->key] = $value;

        // Urgent answers stay on screen so the safety advice is read before moving on (AC5).
        if (ScopingAnswers::triggersUrgent($question, $value) && $this->service()->safety_advice !== []) {
            $this->resetErrorBag();

            return;
        }

        $this->next();
    }

    public function next(): void
    {
        $this->resetErrorBag();

        match ($this->step) {
            'questions' => $this->nextQuestion(),
            'notes' => $this->leaveNotes(),
            'property' => $this->leaveProperty(),
            'when' => $this->leaveWhen(),
            default => null,
        };
    }

    public function back(): void
    {
        $this->resetErrorBag();

        if ($this->step === 'questions') {
            $this->questionIndex = max(0, $this->questionIndex - 1);

            return;
        }

        if ($this->step === 'notes' && $this->questions()->isNotEmpty()) {
            $this->step = 'questions';
            $this->questionIndex = $this->questions()->count() - 1;

            return;
        }

        $index = array_search($this->step, self::STEPS, true);
        $this->step = self::STEPS[max(0, (int) $index - 1)];
    }

    /** Jump back to a step from the review screen. */
    public function change(string $step): void
    {
        if (in_array($step, self::STEPS, true) && $step !== 'review') {
            $this->step = $step;
            $this->questionIndex = 0;
        }
    }

    public function selectProperty(string $publicId): void
    {
        if ($this->user()?->properties()->where('public_id', $publicId)->exists()) {
            $this->propertyPublicId = $publicId;
        }
    }

    /** Guests keep their answers across the login (AC2). */
    public function logInToContinue(): void
    {
        session()->put(self::RESUME_KEY, ['service_id' => $this->serviceId, 'answers' => $this->answers, 'notes' => $this->notes]);
        session()->put('url.intended', $this->bookingUrl());

        $this->redirectRoute('login');
    }

    public function post(PostServiceJob $postServiceJob): void
    {
        $user = $this->user();

        if (! $user instanceof User || ! $this->isCustomer()) {
            $this->step = 'property';

            return;
        }

        $existing = $this->jobPublicId === null ? null : ServiceJob::query()->where('public_id', $this->jobPublicId)->where('customer_id', $user->id)->first();

        // A second tap after a successful post just goes to the job.
        if ($existing instanceof ServiceJob && $existing->status !== ServiceJobStatus::Draft) {
            $this->redirectRoute('jobs.show', $existing);

            return;
        }

        $job = $this->autosave();

        try {
            $postServiceJob->handle($user, $job);
        } catch (CannotPostServiceJob $exception) {
            throw ValidationException::withMessages(['post' => $exception->getMessage()]);
        }

        session()->flash('job_posted', true);
        $this->redirectRoute('jobs.show', $job);
    }

    public function render(): View
    {
        $service = $this->service();
        $question = $this->currentQuestion();

        return view('livewire.booking.wizard', [
            'service' => $service,
            'question' => $question,
            'questionCount' => $this->questions()->count(),
            'stepNumber' => $this->stepNumber(),
            'stepTotal' => $this->questions()->count() + count(self::STEPS) - 1,
            'urgentAdvice' => $question instanceof ScopingQuestion && ScopingAnswers::triggersUrgent($question, $this->answers[$question->key] ?? null),
            'properties' => $this->user()?->properties()->with('suburb')->get() ?? new Collection,
            'selectedProperty' => $this->selectedProperty(),
            'windows' => $service->emergency_capable ? TimeWindow::cases() : [TimeWindow::Morning, TimeWindow::Afternoon, TimeWindow::Flexible],
            'isGuest' => ! $this->user() instanceof User,
            'isCustomer' => $this->isCustomer(),
            'bookingUrl' => $this->bookingUrl(),
            'minDate' => LocalTime::today()->toDateString(),
            'maxDate' => LocalTime::today()->addDays((int) config('sortd.jobs.booking_days_ahead'))->toDateString(),
            'preferredDateLabel' => $this->validPreferredDate()?->translatedFormat('D j M'),
            'isUrgent' => $this->timeWindow === TimeWindow::Today->value || ScopingAnswers::isUrgent($service, $this->checkedAnswers()),
            'reviewAnswers' => $this->checkedAnswers(),
        ])->title($service->name);
    }

    private function nextQuestion(): void
    {
        $question = $this->currentQuestion();

        if ($question instanceof ScopingQuestion) {
            $raw = $this->answers[$question->key] ?? null;

            if (ScopingAnswers::isBlank($raw)) {
                if ($question->required) {
                    throw ValidationException::withMessages(['answer' => __('Please answer this question.')]);
                }

                unset($this->answers[$question->key]);
            } elseif (! ScopingAnswers::check($question, $raw)['ok']) {
                throw ValidationException::withMessages(['answer' => __('Please choose a valid answer.')]);
            }
        }

        if ($this->questionIndex < $this->questions()->count() - 1) {
            $this->questionIndex++;
        } else {
            $this->step = 'notes';
        }

        $this->autosaveIfCustomer();
    }

    private function leaveNotes(): void
    {
        $this->notes = trim($this->notes);
        $this->validate(['notes' => ['nullable', 'string', 'max:'.config('sortd.jobs.notes_max_length')]]);
        $this->step = 'property';
        $this->autosaveIfCustomer();
    }

    private function leaveProperty(): void
    {
        if (! $this->selectedProperty() instanceof Property) {
            throw ValidationException::withMessages(['property' => __('Choose where the work is needed.')]);
        }

        if (! $this->selectedProperty()->suburb->is_active) {
            throw ValidationException::withMessages(['property' => __("Sortd isn't in :suburb yet.", ['suburb' => $this->selectedProperty()->suburb->name])]);
        }

        $this->step = 'when';
        $this->autosaveIfCustomer();
    }

    private function leaveWhen(): void
    {
        if ($this->timeWindow === TimeWindow::Today->value) {
            $this->preferredDate = LocalTime::today()->toDateString();
        }

        $this->validate([
            'timeWindow' => ['required', 'in:'.implode(',', array_map(fn (TimeWindow $w): string => $w->value, $this->service()->emergency_capable ? TimeWindow::cases() : [TimeWindow::Morning, TimeWindow::Afternoon, TimeWindow::Flexible]))],
            'preferredDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.LocalTime::today()->toDateString(), 'before_or_equal:'.LocalTime::today()->addDays((int) config('sortd.jobs.booking_days_ahead'))->toDateString()],
        ], [
            'timeWindow.required' => __('Choose a time.'),
            'preferredDate.required' => __('Choose a day.'),
            'preferredDate.*' => __('Choose a day in the next :days days.', ['days' => config('sortd.jobs.booking_days_ahead')]),
        ]);

        $this->step = 'review';
        $this->autosaveIfCustomer();
    }

    private function autosaveIfCustomer(): void
    {
        if ($this->isCustomer()) {
            $this->autosave();
        }
    }

    private function autosave(): ServiceJob
    {
        /** @var User $user */
        $user = $this->user();
        $job = $this->jobPublicId === null ? null
            : ServiceJob::query()->where('public_id', $this->jobPublicId)->where('customer_id', $user->id)->firstOrFail();

        try {
            $job = app(SaveBookingDraft::class)->handle($user, $this->service(), $job, new BookingData(
                answers: $this->checkedAnswers(),
                notes: $this->notes === '' ? null : $this->notes,
                propertyPublicId: $this->propertyPublicId,
                preferredDate: $this->validPreferredDate(),
                timeWindow: TimeWindow::tryFrom($this->timeWindow),
            ));
        } catch (CannotPostServiceJob $exception) {
            throw ValidationException::withMessages(['post' => $exception->getMessage()]);
        }

        $this->jobPublicId = $job->public_id;

        return $job;
    }

    /**
     * Valid answers only, in the stored form.
     *
     * @return array<string, array{prompt: string, type: string, answer: string|int|list<string>}>
     */
    private function checkedAnswers(): array
    {
        $checked = [];

        foreach ($this->questions() as $question) {
            $raw = $this->answers[$question->key] ?? null;

            if (! ScopingAnswers::isBlank($raw)) {
                $result = ScopingAnswers::check($question, $raw);

                if ($result['ok']) {
                    $checked[$question->key] = $result['value'];
                }
            }
        }

        return $checked;
    }

    private function resumeDraft(ServiceJob $job): void
    {
        abort_unless($this->user()?->can('update', $job) === true && $job->status === ServiceJobStatus::Draft, 404);

        $this->serviceId = $job->service_id;
        $this->jobPublicId = $job->public_id;
        $this->notes = (string) $job->customer_notes;
        $this->propertyPublicId = $job->property?->public_id;
        $this->preferredDate = (string) $job->preferred_date?->toDateString();
        $this->timeWindow = (string) $job->time_window?->value;

        foreach ($job->scoping_answers as $key => $stored) {
            $this->answers[$key] = $stored['answer'];
        }

        $this->prepareMultiChoiceAnswers();

        $this->step = match (true) {
            ScopingAnswers::missingRequired($this->service(), $job->scoping_answers) !== [] => 'questions',
            $job->property_id === null => 'property',
            $job->time_window === null => 'when',
            default => 'review',
        };
    }

    /** Checkbox groups need an array to bind to, or the browser sends `true` (spec 005 review). */
    private function prepareMultiChoiceAnswers(): void
    {
        foreach ($this->questions() as $question) {
            if ($question->type === QuestionType::MultiChoice && ! is_array($this->answers[$question->key] ?? null)) {
                $this->answers[$question->key] = [];
            }
        }
    }

    private function bookingUrl(): string
    {
        if ($this->jobPublicId !== null) {
            return route('booking.continue', $this->jobPublicId, false);
        }

        $service = $this->service();

        return route('booking.start', [$service->trade, $service], false);
    }

    /** A tampered or half-typed date is treated as "not chosen yet" rather than an error page. */
    private function validPreferredDate(): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->preferredDate) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $this->preferredDate, LocalTime::timezone());
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }

    private function stepNumber(): int
    {
        return match ($this->step) {
            'questions' => $this->questionIndex + 1,
            default => $this->questions()->count() + (int) array_search($this->step, self::STEPS, true),
        };
    }

    private function currentQuestion(): ?ScopingQuestion
    {
        return $this->step === 'questions' ? $this->questions()->get($this->questionIndex) : null;
    }

    /** @return Collection<int, ScopingQuestion> */
    private function questions(): Collection
    {
        return $this->service()->questions->values();
    }

    private ?Service $serviceCache = null;

    private function service(): Service
    {
        return $this->serviceCache ??= Service::query()->with(['questions', 'trade'])->findOrFail($this->serviceId);
    }

    private function selectedProperty(): ?Property
    {
        return $this->propertyPublicId === null ? null
            : $this->user()?->properties()->with('suburb')->where('public_id', $this->propertyPublicId)->first();
    }

    private function isCustomer(): bool
    {
        return $this->user()?->hasRole(Role::Customer->value) === true && $this->user()->phone_verified_at !== null;
    }

    private function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** For the view: input type per question. */
    public static function inputFor(ScopingQuestion $question): string
    {
        return match ($question->type) {
            QuestionType::SingleChoice, QuestionType::YesNo => 'tap',
            QuestionType::MultiChoice => 'multi',
            QuestionType::Number => 'number',
            QuestionType::Text => 'text',
        };
    }
}
