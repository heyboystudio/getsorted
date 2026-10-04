<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Matching\Actions\JoinWaitlist;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Properties\Queries\SuburbSearchQuery;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\RemoveJobPhoto;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Actions\StoreJobPhoto;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Exceptions\NoEligiblePros;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Livewire\Welcome;
use App\Models\Property;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\Suburb;
use App\Models\Trade;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Booking wizard: questions one per screen → notes → photos → property →
 * when → review → post. Guests answer questions, then log in at the property
 * step; logged-in customers autosave a draft at every step.
 */
#[Layout('components.layouts.app')]
final class Wizard extends Component
{
    use WithFileUploads;

    public const array STEPS = ['coverage', 'questions', 'notes', 'photos', 'property', 'when', 'review'];

    private const string RESUME_KEY = 'booking.resume';

    #[Locked]
    public int $serviceId;

    #[Locked]
    public ?string $jobPublicId = null;

    #[Locked]
    public string $step = 'coverage';

    public string $suburbQuery = '';

    #[Locked]
    public ?string $suburb = null;

    public string $waitlistFirstName = '';

    public string $waitlistPhone = '';

    public bool $waitlistConsent = false;

    #[Locked]
    public int $questionIndex = 0;

    /** @var array<string, mixed> raw answers keyed by question key */
    public array $answers = [];

    public string $notes = '';

    public ?TemporaryUploadedFile $photoUpload = null;

    public ?string $propertyPublicId = null;

    #[Locked]
    public ?string $pendingPropertySuburb = null;

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

        /** @var array{service_id?: int, answers?: array<string, mixed>, notes?: string, step?: string, suburb?: string|null, suburb_query?: string}|null $resume */
        $resume = session()->pull(self::RESUME_KEY);

        if (is_array($resume) && ($resume['service_id'] ?? null) === $service->id) {
            $this->answers = $resume['answers'] ?? [];
            $this->notes = $resume['notes'] ?? '';
            $this->suburb = $resume['suburb'] ?? null;
            $this->suburbQuery = $resume['suburb_query'] ?? '';
            $this->step = in_array($resume['step'] ?? '', ['photos', 'property'], true) ? $resume['step'] : 'coverage';
        }

        $description = $this->homeDescription();

        if ($this->notes === '' && $description !== null) {
            $this->notes = $description;
        }

        // Continue an existing draft for this service instead of starting another (drafts are capped).
        $existing = $this->user()?->hasRole(Role::Customer->value) === true ? ServiceJob::query()
            ->where('customer_id', $this->user()->id)->where('service_id', $service->id)
            ->where('status', ServiceJobStatus::Draft)->latest('updated_at')->first() : null;

        if ($existing instanceof ServiceJob && ! is_array($resume)) {
            $this->resumeDraft($existing);

            // A draft without notes takes the home-page description, shown on the notes step to check (spec 007, AC4).
            if ($this->notes === '' && $description !== null) {
                $this->notes = $description;
                $this->step = 'notes';
            }

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
            'coverage' => $this->leaveCoverage(),
            'questions' => $this->nextQuestion(),
            'notes' => $this->leaveNotes(),
            'photos' => $this->leavePhotos(),
            'property' => $this->leaveProperty(),
            'when' => $this->leaveWhen(),
            default => null,
        };
    }

    public function back(): void
    {
        $this->resetErrorBag();

        if ($this->step === 'questions') {
            if ($this->questionIndex === 0) {
                $this->step = 'coverage';

                return;
            }
            $this->questionIndex = max(0, $this->questionIndex - 1);

            return;
        }

        if (in_array($this->step, ['waitlist', 'waitlist_done'], true)) {
            $this->step = 'coverage';

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
            $this->pendingPropertySuburb = null;
        }
    }

    public function confirmPropertySuburb(): void
    {
        $property = $this->selectedProperty();
        abort_unless($property instanceof Property && $this->pendingPropertySuburb === $property->suburb->slug, 404);
        $this->pendingPropertySuburb = null;
        $this->suburb = $property->suburb->slug;
        $this->suburbQuery = $property->suburb->name;
        $this->checkPropertyCoverage($property);
    }

    /** Guests keep their answers across the login (AC2). */
    public function logInToContinue(): void
    {
        session()->put(self::RESUME_KEY, ['service_id' => $this->serviceId, 'answers' => $this->answers, 'notes' => $this->notes, 'step' => $this->step, 'suburb' => $this->suburb, 'suburb_query' => $this->suburbQuery]);
        session()->put('url.intended', $this->bookingUrl());

        $this->redirectRoute('login');
    }

    public function addPhoto(StoreJobPhoto $storeJobPhoto): void
    {
        $user = $this->user();
        abort_unless($user instanceof User && $this->isCustomer(), 403);

        $this->validate(['photoUpload' => ['required', 'file', 'max:'.config('sortd.job_photos.max_kilobytes')]]);
        $job = $this->autosave();
        $storeJobPhoto->handle($user, $job, $this->photoUpload);
        $this->photoUpload->delete();
        $this->photoUpload = null;
        $this->resetErrorBag('photoUpload');
    }

    public function removePhoto(string $uuid, RemoveJobPhoto $removeJobPhoto): void
    {
        $user = $this->user();
        abort_unless($user instanceof User && $this->isCustomer() && $this->jobPublicId !== null, 404);
        $job = ServiceJob::query()->where('public_id', $this->jobPublicId)->where('customer_id', $user->id)->firstOrFail();
        $removeJobPhoto->handle($user, $job, $uuid);
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
        } catch (NoEligiblePros $exception) {
            $this->suburb = $job->property?->suburb?->slug;
            $this->suburbQuery = $job->property instanceof Property ? $job->property->suburb->name : '';
            $this->step = 'waitlist';
            throw ValidationException::withMessages(['post' => $exception->getMessage()]);
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
        $photoJob = $this->jobPublicId === null ? null : ServiceJob::query()->where('public_id', $this->jobPublicId)
            ->where('customer_id', $this->user()?->id)->first();
        $photos = $photoJob?->getMedia(ServiceJob::PHOTO_COLLECTION) ?? collect();

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
            'photos' => $photos,
            'photoUrls' => $photoJob === null ? [] : $photos->mapWithKeys(fn ($photo): array => [$photo->uuid => $photoJob->photoUrl($photo)])->all(),
            'summary' => $this->step === 'review' ? $this->summaryView() : null,
            'suburbSuggestions' => RateLimiter::tooManyAttempts($this->coverageRateKey('search'), (int) config('sortd.waitlist.searches_per_hour'))
                ? new Collection : app(SuburbSearchQuery::class)->handle($this->suburbQuery),
        ])->title($service->name);
    }

    public function selectSuburb(string $slug): void
    {
        $suburb = Suburb::query()->where('slug', $slug)->first();

        if ($suburb instanceof Suburb) {
            $this->suburb = $suburb->slug;
            $this->suburbQuery = $suburb->name;
        }
    }

    public function updatedSuburbQuery(): void
    {
        RateLimiter::hit($this->coverageRateKey('search'), 3600);
        if ($this->chosenSuburb()?->name !== $this->suburbQuery) {
            $this->suburb = null;
        }
    }

    public function joinWaitlist(JoinWaitlist $joinWaitlist): void
    {
        if ($this->step === 'waitlist_done') {
            return;
        }

        abort_unless($this->step === 'waitlist', 404);
        $joinWaitlist->handle($this->service(), $this->chosenSuburb(), $this->suburbQuery, $this->waitlistFirstName, $this->waitlistPhone, $this->waitlistConsent, request()->ip());
        $this->step = 'waitlist_done';
    }

    private function leaveCoverage(): void
    {
        $this->countCoverageCheck();
        $this->suburbQuery = trim($this->suburbQuery);

        if ($this->suburbQuery === '' || mb_strlen($this->suburbQuery) > 120) {
            throw ValidationException::withMessages(['suburbQuery' => __('Enter a suburb.')]);
        }

        $suburb = $this->chosenSuburb();
        $this->step = $suburb instanceof Suburb && app(EligibleProsQuery::class)->exists($this->service(), $suburb, $this->user()) ? 'questions' : 'waitlist';
        if ($this->waitlistFirstName === '' && $this->user() instanceof User) {
            $this->waitlistFirstName = $this->user()->first_name;
        }
        if ($this->waitlistPhone === '' && $this->user() instanceof User) {
            $this->waitlistPhone = (string) $this->user()->phone_e164;
        }
    }

    private function chosenSuburb(): ?Suburb
    {
        return $this->suburb === null ? null : Suburb::query()->where('slug', $this->suburb)->first();
    }

    private function coverageRateKey(string $kind): string
    {
        return 'coverage:'.$kind.':'.hash_hmac('sha256', (string) request()->ip(), (string) config('app.key'));
    }

    private function countCoverageCheck(): void
    {
        $key = $this->coverageRateKey('check');

        if (RateLimiter::tooManyAttempts($key, (int) config('sortd.waitlist.checks_per_hour'))) {
            throw ValidationException::withMessages(['suburbQuery' => __('Please try checking again later.')]);
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * The review step's job-description card key (remounts it when details change) and safety guidance (spec 007, AC5, AC9).
     *
     * @return array{key: string, advice: list<string>, guidance: bool}|null
     */
    private function summaryView(): ?array
    {
        $job = $this->reviewDraft();

        if (! $job instanceof ServiceJob) {
            return null;
        }

        $service = $this->service();

        return [
            'key' => JobSummaryInput::hash($job),
            'advice' => $service->safety_advice,
            'guidance' => $service->safety_advice !== [] || $service->requires_registration === RegistrationType::ElectricalRegisteredPerson,
        ];
    }

    private function reviewDraft(): ?ServiceJob
    {
        $user = $this->user();

        if ($this->step !== 'review' || $this->jobPublicId === null || ! $user instanceof User) {
            return null;
        }

        return ServiceJob::query()->with('service')->where('public_id', $this->jobPublicId)
            ->where('customer_id', $user->id)->where('status', ServiceJobStatus::Draft)->first();
    }

    /** A description typed on the home page in the last 30 minutes, used once to start the notes (spec 007, AC4). */
    private function homeDescription(): ?string
    {
        /** @var array{text?: string, expires_at?: int}|null $description */
        $description = session()->pull(Welcome::DESCRIPTION_KEY);

        if (! is_array($description) || ($description['expires_at'] ?? 0) <= now()->getTimestamp()) {
            return null;
        }

        $text = mb_substr(trim((string) ($description['text'] ?? '')), 0, (int) config('sortd.jobs.notes_max_length'));

        return $text === '' ? null : $text;
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
        $this->step = 'photos';
        $this->autosaveIfCustomer();
    }

    private function leavePhotos(): void
    {
        if ($this->photoUpload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages(['photoUpload' => __('Add your selected photo before continuing, or choose another file.')]);
        }

        $this->step = 'property';
        $this->autosaveIfCustomer();
    }

    private function leaveProperty(): void
    {
        if (! $this->selectedProperty() instanceof Property) {
            throw ValidationException::withMessages(['property' => __('Choose where the work is needed.')]);
        }

        $property = $this->selectedProperty();

        if ($property->suburb->slug !== $this->suburb) {
            $this->pendingPropertySuburb = $property->suburb->slug;

            return;
        }

        $this->checkPropertyCoverage($property);
    }

    private function checkPropertyCoverage(Property $property): void
    {
        $this->countCoverageCheck();
        if (! app(EligibleProsQuery::class)->exists($this->service(), $property->suburb, $this->user())) {
            $this->step = 'waitlist';

            return;
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
        $this->suburb = $job->property?->suburb?->slug;
        $this->suburbQuery = $job->property instanceof Property ? $job->property->suburb->name : '';
        $this->preferredDate = (string) $job->preferred_date?->toDateString();
        $this->timeWindow = (string) $job->time_window?->value;

        foreach ($job->scoping_answers as $key => $stored) {
            $this->answers[$key] = $stored['answer'];
        }

        $this->prepareMultiChoiceAnswers();

        $this->step = match (true) {
            ScopingAnswers::missingRequired($this->service(), $job->scoping_answers) !== [] => 'questions',
            $job->property_id === null => 'coverage',
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
            'coverage' => 1,
            'questions' => $this->questionIndex + 2,
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
