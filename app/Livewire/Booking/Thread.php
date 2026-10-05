<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Assistant\Actions\ChatWithSiya;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Matching\Actions\JoinWaitlist;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Properties\Actions\SaveProperty;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Exceptions\PropertyLimitReached;
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
use App\Livewire\Concerns\SearchesAddresses;
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
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Booking in one Siya conversation (spec 017, Kandua-style): service → questions →
 * details → sign in → where → when → photos → summary → Confirm booking. Every
 * step is a card in the same thread; nothing is asked twice. Siya only books the
 * job when the customer taps Confirm booking. The thread lives in the session
 * (scrubbed text) and, once signed in, on the customer's draft job.
 */
#[Layout('components.layouts.app')]
final class Thread extends Component
{
    use SearchesAddresses;
    use WithFileUploads;

    public const string SESSION_KEY = 'booking.thread';

    /** Text typed into the account home's "What's going on at home?" box, read once. */
    public const string START_KEY = 'booking_start';

    /** The four steps of the progress bar (AC4). */
    public const array PROGRESS = ['describe' => 'Describe', 'where' => 'Where & when', 'photos' => 'Photos', 'confirm' => 'Confirm'];

    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 1000;

    /** Gas, sparks, smoke or water near electrics: show the stop-first card (spec 016 AC4). */
    private const string EMERGENCY = '/\b(gas|spark\w*|smok\w*|burning smell|electrocut\w*|shock\w*|flood\w*)\b/iu';

    /** State saved to the session after every action. */
    private const array PERSISTED = [
        'messages', 'stage', 'tradeId', 'serviceId', 'suggestedServiceId', 'answers', 'notes', 'detailsDone',
        'photosDone', 'propertyPublicId', 'preferredDate', 'chosenDate', 'timeWindow', 'jobPublicId', 'customerMessages',
        'failures', 'emergencyShown', 'returnToSummary', 'waitlistSuburbId',
    ];

    public string $message = '';

    /** @var list<array{role: 'customer'|'assistant', text: string, kind?: string, label?: string}> */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public string $stage = 'trade';

    #[Locked]
    public ?int $tradeId = null;

    #[Locked]
    public ?int $serviceId = null;

    #[Locked]
    public ?int $suggestedServiceId = null;

    /** @var array<string, mixed> raw answers keyed by question key */
    #[Locked]
    public array $answers = [];

    #[Locked]
    public string $notes = '';

    public string $notesDraft = '';

    #[Locked]
    public bool $detailsDone = false;

    #[Locked]
    public bool $photosDone = false;

    #[Locked]
    public ?string $propertyPublicId = null;

    /** Set by the calendar in the browser; checked on the server when a time is chosen. */
    public string $preferredDate = '';

    /** The day checked and confirmed by chooseWhen: the only date that is saved. */
    #[Locked]
    public string $chosenDate = '';

    #[Locked]
    public string $timeWindow = '';

    #[Locked]
    public ?string $jobPublicId = null;

    #[Locked]
    public int $customerMessages = 0;

    #[Locked]
    public int $failures = 0;

    #[Locked]
    public bool $emergencyShown = false;

    /** A "Change" from the summary: go back to the summary once that card is done. */
    #[Locked]
    public bool $returnToSummary = false;

    #[Locked]
    public ?int $waitlistSuburbId = null;

    public ?TemporaryUploadedFile $photoUpload = null;

    public string $newStreet = '';

    public string $newSuburbQuery = '';

    #[Locked]
    public ?string $newSuburb = null;

    public string $newType = '';

    public function mount(?Trade $trade = null, ?Service $service = null, ?ServiceJob $job = null): void
    {
        if ($job instanceof ServiceJob && $job->exists) {
            $this->resumeDraft($job);

            return;
        }

        if ($service instanceof Service && $service->exists) {
            abort_unless($trade instanceof Trade && $service->trade_id === $trade->id && $service->is_active && $trade->is_active, 404);
        } elseif ($trade instanceof Trade && $trade->exists) {
            abort_unless($trade->is_active, 404);
        }

        $this->restore();
        $start = $this->startText();

        $hasService = $service instanceof Service && $service->exists;
        $wantsService = $hasService && $service->id !== $this->serviceId;
        $wantsTrade = ! $hasService && $trade instanceof Trade && $trade->exists && $trade->id !== $this->tradeId;

        if ($this->messages === [] || $wantsService || $wantsTrade || $this->stage === 'posted' || $start !== null) {
            $this->startOver();

            if ($hasService) {
                // A home-page description arriving with a service link is the customer's first message (spec 007 AC4).
                if ($start !== null) {
                    $scrubbed = ChatWithSiya::scrub($start);
                    $this->messages[] = ['role' => 'customer', 'text' => $scrubbed];
                    $this->addToNotes($scrubbed);
                }

                $this->setService($service, extract: $start !== null);

                return;
            }

            if ($trade instanceof Trade && $trade->exists) {
                $this->pickTrade($trade->key);
            }
        }

        if ($start !== null && $this->serviceId === null) {
            $this->message = $start;
            $this->send(app(ChatWithSiya::class));

            return;
        }

        // Coming back from sign-in: save the draft and carry on at Where & when.
        if ($this->stage === 'signin' && $this->isCustomer()) {
            $this->autosave();
            $this->advance();
        }

        $this->persist();
    }

    // ── Describe ────────────────────────────────────────────────────────

    public function pickTrade(string $key): void
    {
        abort_unless(in_array($this->stage, ['trade', 'describe'], true), 404);
        $trade = Trade::query()->where('key', $key)->where('is_active', true)->firstOrFail();

        $this->tradeId = $trade->id;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => $trade->name];
        $this->stage = 'service';
        $this->say(__('What’s the :trade problem? Tap one, or tell me in your own words.', ['trade' => mb_strtolower($trade->name)]));
    }

    public function pickService(string $key): void
    {
        abort_unless($this->stage === 'service', 404);
        $service = Service::query()->with(['questions', 'trade'])->where('key', $key)->where('is_active', true)
            ->when($this->tradeId !== null, fn ($query) => $query->where('trade_id', $this->tradeId))->firstOrFail();

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => $service->name];
        $this->setService($service);
    }

    /** "Other": the customer describes it and Siya suggests the service. */
    public function describeOther(): void
    {
        abort_unless($this->stage === 'service', 404);
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Something else')];
        $this->stage = 'describe';
        $this->say(__('Tell me what’s going on, in your own words.'));
    }

    public function send(ChatWithSiya $siya): void
    {
        $text = trim($this->message);

        if (mb_strtolower($text) === 'start over') {
            $this->restart();

            return;
        }

        if (mb_strlen($text) < self::MIN_LENGTH || mb_strlen($text) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['message' => __('Type a message of :min to :max characters.', ['min' => self::MIN_LENGTH, 'max' => self::MAX_LENGTH])]);
        }

        if ($this->customerMessages >= (int) config('sortd.ai.chat_messages_per_conversation')) {
            throw ValidationException::withMessages(['message' => __('This chat is full. Tap Restart to start again.')]);
        }

        $this->message = '';
        $this->customerMessages++;
        $scrubbed = ChatWithSiya::scrub($text);
        $this->messages[] = ['role' => 'customer', 'text' => $scrubbed];
        $this->addToNotes($scrubbed);

        if (! $this->emergencyShown && preg_match(self::EMERGENCY, $text) === 1) {
            $this->emergencyShown = true;
            $this->messages[] = ['role' => 'assistant', 'kind' => 'emergency', 'text' => __('If you can smell gas, see sparks or smoke, or water is near electrics: switch off at the mains if it is safe, keep away, and call emergency services on 112 or the city on 080 131 3111 first.')];
        }

        if (! in_array($this->stage, ['trade', 'service', 'describe', 'suggested', 'questions'], true)) {
            // After the questions, typing adds to the notes for the pro (AC9).
            $this->say(__('Got it, I’ve added that for your pro.'));

            return;
        }

        if (! $siya->available()) {
            $this->say(__('I can’t read messages right now, but I’ve kept what you wrote for your pro. Tap an option to carry on.'));

            return;
        }

        $this->turn($siya);
    }

    public function confirmService(): void
    {
        $service = $this->suggestedServiceId === null ? null
            : Service::query()->with(['questions', 'trade'])->where('is_active', true)->find($this->suggestedServiceId);
        abort_unless($service instanceof Service, 404);

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Yes')];
        $this->setService($service);
    }

    public function rejectService(): void
    {
        $this->suggestedServiceId = null;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Something else')];
        $this->stage = 'describe';
        $this->say(__('No problem. Tell me a bit more, or pick a trade below.'));
    }

    /** A tapped (or, for number/text questions, typed) answer to the current question: no AI call. */
    public function answer(string $questionKey, mixed $value): void
    {
        $question = $this->nextQuestion();
        abort_unless($this->stage === 'questions' && $question instanceof ScopingQuestion && $question->key === $questionKey, 404);

        $raw = $question->type === QuestionType::MultiChoice ? array_values(array_filter((array) $value, 'is_string')) : $value;

        if (! ScopingAnswers::check($question, $raw)['ok']) {
            throw ValidationException::withMessages(['answer' => __('Please choose a valid answer.')]);
        }

        $shown = match ($question->type) {
            QuestionType::YesNo => $raw === 'yes' ? __('Yes') : __('No'),
            QuestionType::MultiChoice => implode(', ', $raw),
            default => (string) $raw,
        };

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => $shown];
        $this->answers[$question->key] = $raw;

        if (ScopingAnswers::triggersUrgent($question, $raw) && ! $this->safetyShown()) {
            $this->showSafetyAdvice($this->service());
        }

        $this->autosave();
        $this->advance();
    }

    public function skipQuestion(): void
    {
        $question = $this->nextQuestion();
        abort_unless($this->stage === 'questions' && $question instanceof ScopingQuestion && ! $question->required, 404);

        $this->answers[$question->key] = null;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Skip')];
        $this->advance();
    }

    public function addDetails(): void
    {
        abort_unless($this->stage === 'details', 404);
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Add more details')];
        $this->say(__('Go ahead. What should your pro know? For example when you’re home, pets, or how to get in.'));
    }

    public function continueDetails(): void
    {
        abort_unless($this->stage === 'details', 404);
        $this->detailsDone = true;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Continue')];
        $this->autosave();
        $this->advance();
    }

    // ── Sign in ─────────────────────────────────────────────────────────

    /** Guests sign in before Where & when; everything is kept (AC10). */
    public function signIn(): void
    {
        $this->leaveForAuth('login');
    }

    public function signUp(): void
    {
        $this->leaveForAuth('register');
    }

    /** Signed in but the mobile number isn't verified yet (spec 001). */
    public function verifyPhone(): void
    {
        abort_unless($this->stage === 'signin' && $this->user() instanceof User, 404);
        $this->persist();
        session()->put('url.intended', $this->threadUrl());
        $this->redirectRoute('verification.phone', navigate: true);
    }

    // ── Where ───────────────────────────────────────────────────────────

    public function selectProperty(string $publicId): void
    {
        abort_unless(in_array($this->stage, ['where', 'add_property'], true) && $this->isCustomer(), 404);
        $property = $this->user()?->properties()->with('suburb')->where('public_id', $publicId)->first();
        abort_unless($property instanceof Property, 404);

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => $property->label.', '.$property->suburb->name];
        $this->countCoverageCheck();

        if (! app(EligibleProsQuery::class)->covers($this->service(), $property->suburb, $this->user())) {
            $this->propertyPublicId = null;
            $this->waitlistSuburbId = $property->suburb->id;
            $this->stage = 'waitlist';
            $this->say(__('Sorry, we don’t have pros for :service in :suburb yet. Want us to let you know when we do?', ['service' => $this->service()->name, 'suburb' => $property->suburb->name]));

            return;
        }

        $this->propertyPublicId = $property->public_id;
        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('Location confirmed'), 'text' => $property->street_address.', '.$property->suburb->name];
        $this->say(__('Good news, we have vetted pros for this in :suburb.', ['suburb' => $property->suburb->name]));
        $this->autosave();
        $this->advance();
    }

    public function addProperty(): void
    {
        abort_unless($this->stage === 'where' && $this->isCustomer(), 404);
        $this->reset(['newStreet', 'newSuburbQuery', 'newSuburb', 'newType', 'addressQuery', 'addressSuggestions', 'addressManual']);
        $this->forgetPickedAddress();
        $this->stage = 'add_property';
        $this->persist();
    }

    public function cancelAddProperty(): void
    {
        abort_unless($this->stage === 'add_property', 404);
        $this->stage = 'where';
        $this->persist();
    }

    public function selectNewSuburb(string $slug): void
    {
        $suburb = Suburb::query()->where('slug', $slug)->first();

        if ($suburb instanceof Suburb) {
            $this->newSuburb = $suburb->slug;
            $this->newSuburbQuery = $suburb->name;
        }
    }

    public function updatedNewSuburbQuery(): void
    {
        if ($this->newSuburb !== null && Suburb::query()->where('slug', $this->newSuburb)->value('name') !== $this->newSuburbQuery) {
            $this->newSuburb = null;
            $this->forgetPickedAddress();
        }
    }

    public function saveProperty(SaveProperty $saveProperty): void
    {
        abort_unless($this->stage === 'add_property' && $this->isCustomer(), 404);
        $this->newStreet = trim($this->newStreet);

        $this->validate([
            'newStreet' => ['required', 'string', 'max:200'],
            'newSuburb' => ['required', Rule::exists('suburbs', 'slug')],
            'newType' => ['required', Rule::enum(PropertyType::class)],
        ], [
            'newStreet.required' => __('Enter the street address.'),
            'newSuburb.required' => __('Choose your suburb from the list.'),
            'newType.required' => __('Choose the type of property.'),
        ]);

        /** @var User $user */
        $user = $this->user();
        $suburb = Suburb::query()->where('slug', $this->newSuburb)->firstOrFail();

        try {
            $property = $saveProperty->handle(
                $user,
                null,
                $user->properties()->exists() ? $suburb->name : __('Home'),
                $this->newStreet,
                $suburb,
                null,
                PropertyType::from($this->newType),
                $this->pickedLatitude !== null && $this->pickedLongitude !== null ? Point::makeGeodetic($this->pickedLatitude, $this->pickedLongitude) : null,
                $this->pickedPlaceId,
            );
        } catch (PropertyLimitReached) {
            throw ValidationException::withMessages(['newStreet' => __('You can save up to :count properties. Delete one in your account to add another.', ['count' => config('sortd.properties.max_per_customer')])]);
        }

        $this->stage = 'where';
        $this->selectProperty($property->public_id);
    }

    /** Spec 015: a picked address fills the street and suburb. */
    protected function addressPicked(GeocodedAddress $address, ?Suburb $suburb): void
    {
        $this->newStreet = mb_substr($address->streetLine ?? $address->formattedAddress, 0, 200);
        $this->newSuburb = $suburb?->slug;
        $this->newSuburbQuery = $suburb instanceof Suburb ? $suburb->name : '';
        $this->resetValidation(['newStreet', 'newSuburb']);
    }

    public function joinWaitlist(JoinWaitlist $joinWaitlist): void
    {
        abort_unless($this->stage === 'waitlist' && $this->waitlistSuburbId !== null, 404);
        /** @var User $user */
        $user = $this->user();
        $suburb = Suburb::query()->findOrFail($this->waitlistSuburbId);

        try {
            $joinWaitlist->handle($this->service(), $suburb, $suburb->name, $user->first_name, (string) $user->phone_e164, true, request()->ip());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['waitlist' => $exception->validator->errors()->first()]);
        }

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Yes, keep me updated')];
        $this->stage = 'closed';
        $this->say(__('Done. We’ll let you know when we have pros for this in :suburb. No job has been posted.', ['suburb' => $suburb->name]));
    }

    public function differentService(): void
    {
        abort_unless(in_array($this->stage, ['waitlist', 'closed'], true), 404);
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Choose a different service')];
        $this->clearService();
        $this->stage = 'trade';
        $this->say(__('Sure. What do you need help with?'));
    }

    public function noThanks(): void
    {
        abort_unless($this->stage === 'waitlist', 404);
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('No thanks')];
        $this->stage = 'closed';
        $this->say(__('No problem. Tap Restart if you need anything else.'));
    }

    // ── When ────────────────────────────────────────────────────────────

    public function chooseWhen(string $window): void
    {
        abort_unless($this->stage === 'when', 404);
        $allowed = $this->windows();
        $chosen = TimeWindow::tryFrom($window);

        if (! $chosen instanceof TimeWindow || ! in_array($chosen, $allowed, true)) {
            throw ValidationException::withMessages(['when' => __('Choose a time.')]);
        }

        if ($chosen === TimeWindow::Today) {
            $this->preferredDate = LocalTime::today()->toDateString();
        }

        $date = $this->parseDate($this->preferredDate);
        $last = LocalTime::today()->addDays((int) config('sortd.jobs.booking_days_ahead'));

        if (! $date instanceof CarbonImmutable || $date->lt(LocalTime::today()) || $date->gt($last)) {
            throw ValidationException::withMessages(['when' => __('Choose a day in the next :days days.', ['days' => config('sortd.jobs.booking_days_ahead')])]);
        }

        $this->timeWindow = $chosen->value;
        $this->chosenDate = $date->toDateString();
        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('Date confirmed'), 'text' => $this->whenLabel()];
        $this->autosave();
        $this->advance();
    }

    // ── Photos ──────────────────────────────────────────────────────────

    /** A chosen photo is added straight away: no separate "Add" button. */
    public function updatedPhotoUpload(): void
    {
        abort_unless($this->stage === 'photos' && $this->isCustomer(), 404);
        $this->validate(['photoUpload' => ['required', 'file', 'max:'.config('sortd.job_photos.max_kilobytes')]]);

        /** @var User $user */
        $user = $this->user();
        $job = $this->autosave();
        abort_unless($job instanceof ServiceJob && $this->photoUpload instanceof TemporaryUploadedFile, 404);
        app(StoreJobPhoto::class)->handle($user, $job, $this->photoUpload);
        $this->photoUpload->delete();
        $this->photoUpload = null;
        $this->resetErrorBag('photoUpload');
    }

    public function removePhoto(string $uuid, RemoveJobPhoto $removeJobPhoto): void
    {
        $job = $this->draft();
        abort_unless($job instanceof ServiceJob, 404);
        /** @var User $user */
        $user = $this->user();
        $removeJobPhoto->handle($user, $job, $uuid);
    }

    public function finishPhotos(): void
    {
        abort_unless($this->stage === 'photos', 404);
        $count = $this->draft()?->getMedia(ServiceJob::PHOTO_COLLECTION)->count() ?? 0;
        $this->photosDone = true;
        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('Photos'), 'text' => $count === 0 ? __('Skipped for now') : trans_choice(':count photo added|:count photos added', $count)];
        $this->advance();
    }

    // ── Summary and confirm ─────────────────────────────────────────────

    /** "Change" on a summary section: back to that card, then straight back to the summary. */
    public function change(string $section): void
    {
        abort_unless($this->stage === 'summary', 404);
        $this->returnToSummary = true;

        match ($section) {
            'service' => $this->changeService(),
            'answers' => $this->changeAnswers(),
            'notes' => $this->changeNotes(),
            'where' => $this->changeStage('where', __('Where do you need the work done?')),
            'when' => $this->changeStage('when', __('When do you need help?')),
            'photos' => $this->changeStage('photos', __('Add or remove photos.')),
            default => abort(404),
        };

        $this->persist();
    }

    public function saveNotes(): void
    {
        abort_unless($this->stage === 'notes', 404);
        $this->notesDraft = trim($this->notesDraft);
        $this->validate(['notesDraft' => ['nullable', 'string', 'max:'.config('sortd.jobs.notes_max_length')]]);
        $this->notes = $this->notesDraft;
        $this->autosave();
        $this->advance();
    }

    /** Siya books the job only when the customer taps Confirm booking on the summary (AC16). */
    public function confirmBooking(PostServiceJob $postServiceJob): void
    {
        $user = $this->user();
        abort_unless($user instanceof User && $this->isCustomer(), 404);

        $existing = $this->jobPublicId === null ? null : ServiceJob::query()->where('public_id', $this->jobPublicId)->where('customer_id', $user->id)->first();

        // A second tap after a successful post just shows the posted message again.
        if ($existing instanceof ServiceJob && $existing->status !== ServiceJobStatus::Draft) {
            $this->stage = 'posted';

            return;
        }

        abort_unless($this->stage === 'summary', 404);
        $job = $this->autosave();

        try {
            $postServiceJob->handle($user, $job);
        } catch (NoEligiblePros) {
            $this->waitlistSuburbId = $job->property?->suburb_id;
            $this->propertyPublicId = null;
            $this->stage = 'waitlist';
            $this->say(__('Sorry, we don’t have pros for :service there yet. Want us to let you know when we do?', ['service' => $this->service()->name]));

            return;
        } catch (CannotPostServiceJob $exception) {
            throw ValidationException::withMessages(['post' => $exception->getMessage()]);
        }

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Confirm booking')];
        $this->stage = 'posted';
        $this->say(__('Your job is booked. I’m sharing it with vetted pros near you now, and you’ll get up to 3 quotes to compare. We’ll WhatsApp you as they come in.'));
    }

    public function restart(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->startOver();
        $this->persist();
    }

    public function render(): View
    {
        $service = $this->service();
        $question = $this->stage === 'questions' ? $this->nextQuestion() : null;
        $draft = in_array($this->stage, ['photos', 'summary'], true) ? $this->draft() : null;
        $photos = $draft?->getMedia(ServiceJob::PHOTO_COLLECTION) ?? collect();

        return view('livewire.booking.thread', [
            'available' => app(ChatWithSiya::class)->available(),
            'progress' => $this->progressStep(),
            'trades' => in_array($this->stage, ['trade', 'describe'], true) ? $this->activeTrades() : new Collection,
            'trade' => $this->tradeId === null ? null : Trade::query()->find($this->tradeId),
            'tradeServices' => $this->stage === 'service' && $this->tradeId !== null
                ? Service::query()->where('trade_id', $this->tradeId)->where('is_active', true)->orderBy('sort')->get() : new Collection,
            'suggested' => $this->stage === 'suggested' && $this->suggestedServiceId !== null
                ? Service::query()->with('trade')->where('is_active', true)->find($this->suggestedServiceId) : null,
            'service' => $service,
            'question' => $question,
            'properties' => in_array($this->stage, ['where', 'add_property'], true) && $this->isCustomer()
                ? $this->user()?->properties()->with('suburb')->get() ?? new Collection : new Collection,
            'propertyTypes' => PropertyType::cases(),
            'newSuburbSuggestions' => $this->stage === 'add_property' && $this->newSuburb === null && mb_strlen(trim($this->newSuburbQuery)) >= 2
                ? app(SuburbSearchQuery::class)->handle($this->newSuburbQuery) : new Collection,
            'days' => $this->stage === 'when' ? $this->calendarDays() : [],
            'windows' => $this->stage === 'when' ? $this->windows() : [],
            'photos' => $photos,
            'photoUrls' => $draft === null ? [] : $photos->mapWithKeys(fn ($photo): array => [$photo->uuid => $draft->photoUrl($photo)])->all(),
            'summary' => $this->stage === 'summary' ? $this->summary($draft) : null,
            'isGuest' => ! $this->user() instanceof User,
            'needsPhone' => $this->user()?->hasRole(Role::Customer->value) === true && $this->user()->phone_verified_at === null,
            'limitReached' => $this->customerMessages >= (int) config('sortd.ai.chat_messages_per_conversation'),
        ])->title(__('Book a pro'));
    }

    // ── Internals ───────────────────────────────────────────────────────

    /** One Siya turn on typed text: suggest a service, or map answers for the confirmed one. */
    private function turn(ChatWithSiya $siya): void
    {
        $result = $siya->handle($this->transcript(), $this->service(), $this->knownAnswers(), (string) (auth()->id() ?? request()->ip()));

        if ($result['outcome'] !== AiOutcome::Ok || $result['reply'] === null) {
            $this->failures++;
            $this->messages[] = ['role' => 'assistant', 'kind' => 'error', 'text' => $result['outcome'] === AiOutcome::Throttled
                ? __('Siya is busy right now. Tap an option to carry on.')
                : __('Sorry, something went wrong. Try again, or tap an option.')];
            $this->persist();

            return;
        }

        $this->failures = 0;

        if ($this->serviceId === null) {
            if ($result['suggested'] instanceof Service) {
                // One confirmation only: our card, not the model's own "is that right?" (AC6).
                $this->suggestedServiceId = $result['suggested']->id;
                $this->stage = 'suggested';
                $this->messages[] = ['role' => 'assistant', 'kind' => 'suggest', 'text' => $result['suggested']->name, 'label' => $result['suggested']->trade->name];
                $this->persist();

                return;
            }

            $this->say($result['reply']);

            return;
        }

        $this->answers = array_merge($this->answers, $result['answers']);
        $this->autosave();

        // Siya's reply already asks the next question in its own words.
        $this->say($result['reply']);
        $this->advance(speak: $this->nextQuestion() === null);
    }

    private function setService(Service $service, bool $extract = true): void
    {
        $service->loadMissing(['questions', 'trade']);

        if ($service->id !== $this->serviceId) {
            $this->answers = [];
            $this->jobPublicId = null;
        }

        $this->serviceId = $service->id;
        $this->tradeId = $service->trade_id;
        $this->suggestedServiceId = null;
        $this->serviceCache = $service;
        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('You selected'), 'text' => $service->name.' · '.$service->trade->name];

        if ($service->safety_advice !== []) {
            $this->showSafetyAdvice($service);
        }

        // What the customer already typed answers some questions: those are skipped (AC7).
        $siya = app(ChatWithSiya::class);
        if ($extract && $this->hasTypedText() && $siya->available() && $this->nextQuestion() instanceof ScopingQuestion) {
            $this->turn($siya);

            return;
        }

        // No draft yet: it starts with the first answer, so opening a service link never uses up a draft.
        $this->advance();
    }

    /**
     * Moves to the first missing item and, when $speak, says that card's line.
     */
    private function advance(bool $speak = true): void
    {
        $before = $this->stage;
        $question = $this->serviceId === null ? null : $this->nextQuestion();

        $this->stage = match (true) {
            $this->serviceId === null => $this->tradeId === null ? 'trade' : 'service',
            $question instanceof ScopingQuestion => 'questions',
            ! $this->detailsDone => 'details',
            ! $this->isCustomer() => 'signin',
            $this->propertyPublicId === null || ! $this->selectedProperty() instanceof Property => 'where',
            $this->timeWindow === '' => 'when',
            $this->returnToSummary, $this->photosDone => 'summary',
            default => 'photos',
        };

        if ($this->stage === 'summary') {
            $this->returnToSummary = false;
            $this->photosDone = true;
        }

        if ($speak) {
            $line = match ($this->stage) {
                'questions' => $question?->prompt,
                'details' => $before === 'details' ? null : __('Got it, I have what I need to find you the right pro. Anything else your pro should know?'),
                'signin' => $before === 'signin' ? null : __('Please sign in to book. Everything you’ve told me is kept.'),
                'where' => __('Where do you need the work done?'),
                'when' => __('When do you need help?'),
                'photos' => __('Almost there. Photos help pros quote more accurately and arrive prepared.'),
                'summary' => __('Here’s a summary of your booking. Check it, then tap Confirm booking.'),
                default => null,
            };

            if ($line !== null) {
                $this->say($line);

                return;
            }
        }

        $this->persist();
    }

    private function changeService(): void
    {
        $this->returnToSummary = false;
        $this->clearService();
        $this->stage = 'trade';
        $this->say(__('Sure. What do you need help with? Your answers for the old service will be dropped.'));
    }

    private function changeAnswers(): void
    {
        $this->answers = [];
        $this->advance();
    }

    private function changeNotes(): void
    {
        $this->notesDraft = $this->notes;
        $this->stage = 'notes';
        $this->say(__('Edit your notes for the pro.'));
    }

    private function changeStage(string $stage, string $line): void
    {
        if ($stage === 'where') {
            $this->propertyPublicId = null;
        }

        $this->stage = $stage;
        $this->say($line);
    }

    private function clearService(): void
    {
        $this->serviceId = null;
        $this->serviceCache = null;
        $this->tradeId = null;
        $this->suggestedServiceId = null;
        $this->answers = [];
        $this->detailsDone = false;
        $this->jobPublicId = null;
    }

    private function showSafetyAdvice(Service $service): void
    {
        foreach ($service->safety_advice as $advice) {
            $this->messages[] = ['role' => 'assistant', 'kind' => 'safety', 'text' => $advice];
        }
    }

    private function hasTypedText(): bool
    {
        return collect($this->messages)->contains(fn (array $message): bool => $message['role'] === 'customer' && ! isset($message['kind']));
    }

    private function safetyShown(): bool
    {
        return collect($this->messages)->contains(fn (array $message): bool => ($message['kind'] ?? null) === 'safety');
    }

    private function leaveForAuth(string $route): void
    {
        abort_unless($this->stage === 'signin' && ! $this->user() instanceof User, 404);
        $this->persist();
        session()->put('url.intended', $this->threadUrl());
        $this->redirectRoute($route, navigate: true);
    }

    private function startOver(): void
    {
        $this->reset([...self::PERSISTED, 'message', 'notesDraft', 'photoUpload', 'newStreet', 'newSuburbQuery', 'newSuburb', 'newType']);
        $this->serviceCache = null;
        $this->say(__('Hi, I’m Siya, Sortd’s AI assistant. What do you need help with? Tap a trade, or tell me what’s wrong, for example "my geyser is leaking".'));
    }

    private function restore(): void
    {
        /** @var array<string, mixed>|null $saved */
        $saved = session(self::SESSION_KEY);

        if (! is_array($saved)) {
            return;
        }

        foreach (self::PERSISTED as $field) {
            if (array_key_exists($field, $saved)) {
                $this->{$field} = $saved[$field];
            }
        }

        // A draft saved for someone else (another sign-in on this browser) is not ours.
        if ($this->jobPublicId !== null && ! $this->draft() instanceof ServiceJob) {
            $this->jobPublicId = null;
        }
    }

    private function persist(): void
    {
        session()->put(self::SESSION_KEY, collect(self::PERSISTED)->mapWithKeys(fn (string $field): array => [$field => $this->{$field}])->all());
    }

    private function say(string $text): void
    {
        $this->messages[] = ['role' => 'assistant', 'text' => $text];
        $this->persist();
    }

    private function addToNotes(string $text): void
    {
        $notes = trim($this->notes === '' ? $text : $this->notes."\n".$text);
        $this->notes = mb_substr($notes, 0, (int) config('sortd.jobs.notes_max_length'));
    }

    /** Text from the account home box or the public home (spec 007), used once as the first message. */
    private function startText(): ?string
    {
        $start = session()->pull(self::START_KEY);
        /** @var array{text?: string, expires_at?: int}|null $description */
        $description = session()->pull(Welcome::DESCRIPTION_KEY);

        if (is_string($start) && trim($start) !== '') {
            return mb_substr(trim($start), 0, self::MAX_LENGTH);
        }

        if (is_array($description) && ($description['expires_at'] ?? 0) > now()->getTimestamp() && trim((string) ($description['text'] ?? '')) !== '') {
            return mb_substr(trim((string) $description['text']), 0, self::MAX_LENGTH);
        }

        return null;
    }

    /** @return list<array{role: 'customer'|'assistant', text: string}> */
    private function transcript(): array
    {
        return array_values(array_map(
            fn (array $message): array => ['role' => $message['role'], 'text' => $message['text']],
            array_filter($this->messages, fn (array $message): bool => ! isset($message['kind'])),
        ));
    }

    /** @return array<string, mixed> */
    private function knownAnswers(): array
    {
        return array_filter($this->answers, fn (mixed $answer): bool => ! ScopingAnswers::isBlank($answer));
    }

    private function nextQuestion(): ?ScopingQuestion
    {
        return $this->service()?->questions->first(fn (ScopingQuestion $question): bool => ! array_key_exists($question->key, $this->answers));
    }

    /** Saves the draft for a signed-in customer and returns it (a guest's thread stays in the session). */
    private function autosave(): ?ServiceJob
    {
        $user = $this->user();
        $service = $this->service();

        if (! $user instanceof User || ! $this->isCustomer() || ! $service instanceof Service) {
            return null;
        }

        $job = $this->draft();

        // Pick up an unfinished draft for the same service rather than start another, keeping its notes.
        if (! $job instanceof ServiceJob) {
            $job = ServiceJob::query()->where('customer_id', $user->id)->where('service_id', $service->id)
                ->where('status', ServiceJobStatus::Draft)->latest('updated_at')->first();
            $earlier = trim((string) $job?->customer_notes);

            if ($earlier !== '' && ! str_contains($this->notes, $earlier)) {
                $this->notes = mb_substr(trim($earlier."\n".$this->notes), 0, (int) config('sortd.jobs.notes_max_length'));
            }
        }

        try {
            $job = app(SaveBookingDraft::class)->handle($user, $service, $job, new BookingData(
                answers: $this->checkedAnswers(),
                notes: $this->notes === '' ? null : $this->notes,
                propertyPublicId: $this->propertyPublicId,
                preferredDate: $this->timeWindow === '' ? null : $this->parseDate($this->chosenDate),
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
        $service = $this->service();

        if (! $service instanceof Service) {
            return $checked;
        }

        foreach ($service->questions as $question) {
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

        $this->startOver();
        $service = Service::query()->with(['questions', 'trade'])->findOrFail($job->service_id);
        $this->serviceId = $service->id;
        $this->tradeId = $service->trade_id;
        $this->serviceCache = $service;
        $this->jobPublicId = $job->public_id;
        $this->notes = (string) $job->customer_notes;
        $this->propertyPublicId = $job->property?->public_id;
        $this->preferredDate = (string) $job->preferred_date?->toDateString();
        $this->chosenDate = $this->preferredDate;
        $this->timeWindow = (string) $job->time_window?->value;

        foreach ($job->scoping_answers as $key => $stored) {
            $this->answers[$key] = $stored['answer'];
        }

        $complete = ScopingAnswers::missingRequired($service, $job->scoping_answers) === [];

        // Once the required answers are in, optional questions left out count as skipped.
        foreach ($complete ? $service->questions : [] as $question) {
            if (! array_key_exists($question->key, $this->answers)) {
                $this->answers[$question->key] = null;
            }
        }

        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('You selected'), 'text' => $service->name.' · '.$service->trade->name];
        $this->detailsDone = $complete;
        $this->photosDone = $this->detailsDone && $this->timeWindow !== '';
        $this->advance();
    }

    private function draft(): ?ServiceJob
    {
        $user = $this->user();

        if ($this->jobPublicId === null || ! $user instanceof User) {
            return null;
        }

        return ServiceJob::query()->with(['service', 'property.suburb'])->where('public_id', $this->jobPublicId)
            ->where('customer_id', $user->id)->where('status', ServiceJobStatus::Draft)->first();
    }

    /**
     * @return array{key: ?string, answers: list<array{prompt: string, answer: string}>, property: ?Property, when: string, urgent: bool, advice: list<string>, guidance: bool}
     */
    private function summary(?ServiceJob $draft): array
    {
        $service = $this->service();
        $checked = $this->checkedAnswers();

        return [
            'key' => $draft instanceof ServiceJob ? JobSummaryInput::hash($draft) : null,
            'answers' => array_values(array_map(fn (array $answer): array => [
                'prompt' => $answer['prompt'],
                'answer' => is_array($answer['answer']) ? implode(', ', $answer['answer']) : ($answer['type'] === 'yes_no' ? __(ucfirst((string) $answer['answer'])) : (string) $answer['answer']),
            ], $checked)),
            'property' => $this->selectedProperty(),
            'when' => $this->whenLabel(),
            'urgent' => $this->timeWindow === TimeWindow::Today->value || ($service instanceof Service && ScopingAnswers::isUrgent($service, $checked)),
            'advice' => $service instanceof Service ? $service->safety_advice : [],
            'guidance' => $service instanceof Service && ($service->safety_advice !== [] || $service->requires_registration === RegistrationType::ElectricalRegisteredPerson),
        ];
    }

    private function whenLabel(): string
    {
        $window = TimeWindow::tryFrom($this->timeWindow);

        if ($window === TimeWindow::Today) {
            return $window->label();
        }

        $date = $this->parseDate($this->chosenDate)?->translatedFormat('D j M');

        return trim(($date ?? '').($window instanceof TimeWindow ? ' · '.$window->label() : ''), ' ·');
    }

    /** @return list<TimeWindow> */
    private function windows(): array
    {
        return $this->service()?->emergency_capable === true ? TimeWindow::cases() : [TimeWindow::Morning, TimeWindow::Afternoon, TimeWindow::Flexible];
    }

    /**
     * The calendar: today and the next booking_days_ahead days, padded to start on a Sunday.
     *
     * @return list<array{date: ?string, day: ?int, label: ?string}>
     */
    private function calendarDays(): array
    {
        $today = LocalTime::today();
        $days = array_fill(0, $today->dayOfWeek, ['date' => null, 'day' => null, 'label' => null]);

        for ($offset = 0; $offset <= (int) config('sortd.jobs.booking_days_ahead'); $offset++) {
            $date = $today->addDays($offset);
            $days[] = ['date' => $date->toDateString(), 'day' => $date->day, 'label' => $date->translatedFormat('l j F')];
        }

        return $days;
    }

    /** A tampered or half-typed date is treated as "not chosen yet" rather than an error page. */
    private function parseDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value, LocalTime::timezone());
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date->startOfDay() : null;
    }

    private function progressStep(): string
    {
        return match ($this->stage) {
            'signin', 'where', 'add_property', 'waitlist', 'when' => 'where',
            'photos' => 'photos',
            'summary', 'notes', 'posted' => 'confirm',
            default => 'describe',
        };
    }

    /** @return Collection<int, Trade> */
    private function activeTrades(): Collection
    {
        return Trade::query()->where('is_active', true)
            ->whereHas('services', fn ($query) => $query->where('is_active', true))->orderBy('sort')->get();
    }

    private function countCoverageCheck(): void
    {
        $key = 'coverage:check:'.hash_hmac('sha256', (string) request()->ip(), (string) config('app.key'));

        if (RateLimiter::tooManyAttempts($key, (int) config('sortd.waitlist.checks_per_hour'))) {
            throw ValidationException::withMessages(['where' => __('Please try again later.')]);
        }

        RateLimiter::hit($key, 3600);
    }

    private function threadUrl(): string
    {
        $service = $this->service();

        return $service instanceof Service ? route('booking.start', [$service->trade, $service], false) : route('book', [], false);
    }

    private ?Service $serviceCache = null;

    private function service(): ?Service
    {
        if ($this->serviceId === null) {
            return null;
        }

        return $this->serviceCache ??= Service::query()->with(['questions', 'trade'])->find($this->serviceId);
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
}
