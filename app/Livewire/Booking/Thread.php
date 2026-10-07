<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Contracts\Data\GeocodedAddress;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Assistant\Actions\ChatWithSiya;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\State\BookingState;
use App\Domain\Assistant\Support\EmergencyGuidance;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Matching\Actions\JoinWaitlist;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\Properties\Actions\SaveProperty;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Exceptions\PropertyLimitReached;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\RemoveJobPhoto;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Actions\StoreJobPhoto;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Exceptions\NoEligiblePros;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Livewire\Concerns\SearchesAddresses;
use App\Livewire\Welcome;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use App\Support\BookingStart;
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
use LogicException;
use Throwable;

/**
 * Booking in one Siya conversation (spec 017, 020). The customer describes the problem in their own words; Siya
 * records the trade and short facts through validated tools; the secure controls (sign in, address, date, photos)
 * follow, then Confirm booking. The stage is derived from what is known, never stored as a script. Siya never posts
 * a job: it is posted only when the customer taps Confirm booking. The thread lives in the session
 * (scrubbed text) and, once signed in, on the customer's draft job.
 */
#[Layout('components.layouts.app', ['brand' => 'GetSorted', 'gs' => true])]
final class Thread extends Component
{
    use SearchesAddresses;
    use WithFileUploads;

    public const string SESSION_KEY = 'booking.thread';

    /** Text typed into the account home's "What's going on at home?" box, read once. */
    public const string START_KEY = 'booking_start';

    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 1000;

    /** State saved to the session after every action. */
    private const array PERSISTED = [
        'messages', 'stage', 'tradeId', 'facts', 'urgent', 'parked', 'bookingTurn', 'nextStepOffered', 'notes',
        'photosDone', 'propertyPublicId', 'preferredDate', 'chosenDate', 'timeWindow', 'jobPublicId', 'customerMessages',
        'failures', 'emergencyShown', 'returnToSummary', 'waitlistPropertyPublicId', 'stageBeforeEmergency', 'retryPending',
        'showTradeShortcuts', 'bookingRequested',
    ];

    public string $message = '';

    /** @var list<array{role: 'customer'|'assistant', text: string, kind?: string, label?: string}> */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public string $stage = 'chat';

    #[Locked]
    public ?int $tradeId = null;

    /** @var list<array{id: string, text: string, turn: int}> facts Siya extracted; shown highlighted to pros */
    #[Locked]
    public array $facts = [];

    #[Locked]
    public bool $urgent = false;

    /** @var list<string> other jobs the customer mentioned, to book separately */
    #[Locked]
    public array $parked = [];

    #[Locked]
    public int $bookingTurn = 0;

    #[Locked]
    public bool $nextStepOffered = false;

    #[Locked]
    public string $notes = '';

    public string $notesDraft = '';

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

    #[Locked]
    public ?string $stageBeforeEmergency = null;

    #[Locked]
    public bool $retryPending = false;

    #[Locked]
    public bool $showTradeShortcuts = false;

    /** The customer wants to go ahead: the secure booking controls follow. */
    #[Locked]
    public bool $bookingRequested = false;

    /** A "Change" from the summary: go back to the summary once that card is done. */
    #[Locked]
    public bool $returnToSummary = false;

    /** The saved address with no pros near it, while the waitlist is offered. */
    #[Locked]
    public ?string $waitlistPropertyPublicId = null;

    public ?TemporaryUploadedFile $photoUpload = null;

    /** Address picked through Places while adding a property. */
    #[Locked]
    public string $newStreet = '';

    #[Locked]
    public ?string $newArea = null;

    #[Locked]
    public string $newPostal = '';

    public string $newType = '';

    public function mount(?Trade $trade = null, ?ServiceJob $job = null): void
    {
        BookingStart::clear();

        if ($job instanceof ServiceJob && $job->exists) {
            $this->resumeDraft($job);

            return;
        }

        if ($trade instanceof Trade && $trade->exists) {
            abort_unless($trade->is_active, 404);
        }

        $this->restore();
        $start = $this->startText();

        $wantsTrade = $trade instanceof Trade && $trade->exists && $trade->id !== $this->tradeId;

        if ($this->messages === [] || $wantsTrade || $this->stage === 'posted' || $start !== null) {
            $this->startOver();

            if ($trade instanceof Trade && $trade->exists) {
                $this->pickTrade($trade->key);
            }
        }

        if ($start !== null) {
            $this->message = $start;
            $this->send(app(ChatWithSiya::class));

            return;
        }

        // Coming back from sign-in: save the draft and carry on at the address.
        if ($this->stage === 'signin' && $this->isCustomer()) {
            $this->autosave();
            $this->advance();
        }

        $this->persist();
    }

    // ── Chat ────────────────────────────────────────────────────────────

    public function showTrades(): void
    {
        abort_unless($this->stage === 'chat', 404);
        $this->showTradeShortcuts = true;
        $this->retryPending = false;
        $this->persist();
    }

    /** An optional shortcut: the trade is set directly and Siya asks what is going on. */
    public function pickTrade(string $key): void
    {
        abort_unless($this->stage === 'chat', 404);
        $trade = Trade::query()->where('key', $key)->where('is_active', true)->firstOrFail();

        $this->tradeId = $trade->id;
        $this->showTradeShortcuts = false;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => $trade->name];

        if (trim($this->notes) === '') {
            $this->say(__('What’s the :trade problem? Tell me in your own words.', ['trade' => mb_strtolower($trade->name)]));

            return;
        }

        // The customer already described the problem (saved as notes while Siya could not read it): never ask again.
        $this->say(__('Thanks, your description is saved for the pros. Add more detail or carry on to book.'));

        if (self::soundsActive($this->notes) && ! $this->safetyShown()) {
            $this->showSafetyAdvice();
        }

        $this->advance(speak: false);
    }

    /** Words that suggest a problem happening right now, so the trade's safety tip should not wait for the "when" step. */
    private static function soundsActive(string $text): bool
    {
        return preg_match('/\b(?:leak\w*|burst\w*|flood\w*|drip\w*|pouring|overflow\w*|gushing|no water|tripp\w*|no power|smell\w*)\b/u', mb_strtolower($text)) === 1;
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

        if ($this->customerMessages >= (int) config('getsorted.ai.chat_messages_per_conversation') && ! EmergencyGuidance::required($text)) {
            throw ValidationException::withMessages(['message' => __('This chat is full. Tap Restart to start again.')]);
        }

        $this->message = '';
        $this->customerMessages++;
        $scrubbed = ChatWithSiya::scrub($text);
        $this->messages[] = ['role' => 'customer', 'text' => $scrubbed];
        $this->retryPending = false;

        if (EmergencyGuidance::required($text)) {
            $this->pauseForEmergency();

            return;
        }

        if ($this->stage === 'emergency') {
            $this->say(__('Please contact emergency services first. Use “Discuss a later repair” only when you want to plan repair work, not emergency help.'));

            return;
        }

        abort_unless(! in_array($this->stage, ['posted', 'closed'], true), 404);

        if (! $siya->available()) {
            // Without the assistant the customer's own words still reach the pro, as notes they can see and edit.
            $this->addToNotes($scrubbed);
            $this->say($this->tradeId === null
                ? __('I can’t read messages right now, but your message is saved with the job. Choose a trade to carry on.')
                : __('I can’t read messages right now, but your message is saved with the job. You can carry on to book.'));
            $this->showTradeShortcuts = $this->tradeId === null;
            $this->advance(speak: false);

            return;
        }

        $this->turn($siya);
    }

    /** Runs the same turn again after a failure. The customer's message is already in the thread, so nothing is duplicated. */
    public function retry(ChatWithSiya $siya): void
    {
        abort_unless($this->retryPending && ! in_array($this->stage, ['posted', 'closed', 'emergency'], true), 404);
        $this->turn($siya);
    }

    /** "Continue to book": the customer has what they described and wants the secure booking controls. */
    public function startBooking(): void
    {
        abort_unless($this->stage === 'chat' && $this->isReady(), 404);
        $this->bookingRequested = true;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Continue to book')];
        $this->autosave();
        $this->advance();
    }

    public function continueAfterEmergency(): void
    {
        abort_unless($this->stage === 'emergency', 404);
        $this->stage = $this->stageBeforeEmergency ?? 'chat';
        $this->stageBeforeEmergency = null;
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Discuss a later repair')];
        $this->say(__('We can plan a later repair here. This does not mean the situation is safe; follow the emergency services’ advice.'));
    }

    private function pauseForEmergency(): void
    {
        if ($this->stage !== 'emergency') {
            $this->stageBeforeEmergency = $this->stage;
        }
        $this->stage = 'emergency';
        $this->emergencyShown = true;
        $this->retryPending = false;
        $this->messages[] = ['role' => 'assistant', 'kind' => 'emergency', 'text' => EmergencyGuidance::message()];
        $this->persist();
    }

    /** A fact the customer removes from "What I've got so far", or from the summary. */
    public function removeFact(string $id): void
    {
        abort_unless(! in_array($this->stage, ['posted', 'closed', 'emergency'], true), 404);
        $this->facts = array_values(array_filter($this->facts, fn (array $fact): bool => $fact['id'] !== $id));
        $this->autosave();

        // Stay in the details editor while the customer is still tidying up.
        $this->stage === 'notes' ? $this->persist() : $this->advance(speak: false);
    }

    // ── Sign in ─────────────────────────────────────────────────────────

    /** Guests sign in before the address step; everything is kept (AC10). */
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
        $property = $this->user()?->properties()->where('public_id', $publicId)->first();
        abort_unless($property instanceof Property && $property->location instanceof Point, 404);

        $this->messages[] = ['role' => 'customer', 'kind' => 'private', 'text' => $property->label.($property->area_label === null ? '' : ', '.$property->area_label)];
        $this->countCoverageCheck();

        if (! app(EligibleProsQuery::class)->covers($this->trade() ?? throw new LogicException('No trade chosen.'), $property->location, $this->user())) {
            $this->propertyPublicId = null;
            $this->waitlistPropertyPublicId = $property->public_id;
            $this->stage = 'waitlist';
            $this->say(__('Sorry, we don’t have :trade pros near :area yet. Want us to let you know when we do?', ['trade' => mb_strtolower($this->trade()->name), 'area' => $property->area_label ?? __('you')]));

            return;
        }

        $this->waitlistPropertyPublicId = null;
        $this->propertyPublicId = $property->public_id;
        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('Location confirmed'), 'text' => $property->street_address.($property->area_label === null ? '' : ', '.$property->area_label)];
        $this->say(__('Thanks, I’ll look for vetted pros near you.'));
        $this->autosave();
        $this->advance();
    }

    public function addProperty(): void
    {
        abort_unless($this->stage === 'where' && $this->isCustomer(), 404);
        $this->reset(['newStreet', 'newArea', 'newPostal', 'newType', 'addressQuery', 'addressSuggestions', 'addressUnavailable']);
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

    /** Spec 015, 020: a picked address fills the street and area. */
    protected function addressPicked(GeocodedAddress $address): void
    {
        $this->newStreet = mb_substr($address->streetLine ?? $address->formattedAddress, 0, 200);
        $this->newArea = $address->areaLabel();
        $this->newPostal = $address->postalCode ?? '';
        $this->resetValidation(['addressQuery']);
    }

    public function saveProperty(SaveProperty $saveProperty): void
    {
        abort_unless($this->stage === 'add_property' && $this->isCustomer(), 404);

        $this->validate([
            'newType' => ['required', Rule::enum(PropertyType::class)],
        ], ['newType.required' => __('Choose the type of property.')]);

        if ($this->pickedPlaceId === null || $this->pickedLatitude === null || $this->pickedLongitude === null || $this->newStreet === '') {
            throw ValidationException::withMessages(['addressQuery' => __('Search for your address and choose it from the list.')]);
        }

        /** @var User $user */
        $user = $this->user();

        try {
            $property = $saveProperty->handle(
                $user,
                null,
                $user->properties()->exists() ? ($this->newArea ?? __('Home')) : __('Home'),
                $this->newStreet,
                $this->newArea,
                $this->newPostal === '' ? null : $this->newPostal,
                PropertyType::from($this->newType),
                Point::makeGeodetic($this->pickedLatitude, $this->pickedLongitude),
                $this->pickedPlaceId,
            );
        } catch (PropertyLimitReached) {
            throw ValidationException::withMessages(['addressQuery' => __('You can save up to :count properties. Delete one in your account to add another.', ['count' => config('getsorted.properties.max_per_customer')])]);
        }

        $this->stage = 'where';
        $this->selectProperty($property->public_id);
    }

    public function joinWaitlist(JoinWaitlist $joinWaitlist): void
    {
        abort_unless($this->stage === 'waitlist' && $this->waitlistPropertyPublicId !== null && $this->trade() instanceof Trade, 404);
        /** @var User $user */
        $user = $this->user();
        $property = $user->properties()->where('public_id', $this->waitlistPropertyPublicId)->first();
        abort_unless($property instanceof Property && $property->location instanceof Point, 404);

        try {
            $joinWaitlist->handle($this->trade(), $property->area_label ?? __('Durban'), $property->location, $user->first_name, (string) $user->phone_e164, true, request()->ip());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['waitlist' => $exception->validator->errors()->first()]);
        }

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Yes, keep me updated')];
        $this->stage = 'closed';
        $this->say(__('Done. We’ll let you know when we have pros for this near :area. No job has been posted.', ['area' => $property->area_label ?? __('you')]));
    }

    public function differentTrade(): void
    {
        abort_unless(in_array($this->stage, ['waitlist', 'closed'], true), 404);
        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Choose a different trade')];
        $this->tradeId = null;
        $this->waitlistPropertyPublicId = null;
        $this->bookingRequested = false;
        $this->stage = 'chat';
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
        $chosen = TimeWindow::tryFrom($window);

        if (! $chosen instanceof TimeWindow) {
            throw ValidationException::withMessages(['when' => __('Choose a time.')]);
        }

        if ($chosen === TimeWindow::Today) {
            $this->preferredDate = LocalTime::today()->toDateString();
        }

        $date = $this->parseDate($this->preferredDate);
        $last = LocalTime::today()->addDays((int) config('getsorted.jobs.booking_days_ahead'));

        if (! $date instanceof CarbonImmutable || $date->lt(LocalTime::today()) || $date->gt($last)) {
            throw ValidationException::withMessages(['when' => __('Choose a day in the next :days days.', ['days' => config('getsorted.jobs.booking_days_ahead')])]);
        }

        $this->timeWindow = $chosen->value;
        $this->chosenDate = $date->toDateString();
        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('Date confirmed'), 'text' => $this->whenLabel()];

        if ($chosen === TimeWindow::Today && ! $this->safetyShown()) {
            $this->showSafetyAdvice();
        }

        $this->autosave();
        $this->advance();
    }

    // ── Photos ──────────────────────────────────────────────────────────

    /** A chosen photo is added straight away: no separate "Add" button. */
    public function updatedPhotoUpload(): void
    {
        abort_unless($this->stage === 'photos' && $this->isCustomer(), 404);
        $this->validate(['photoUpload' => ['required', 'file', 'max:'.config('getsorted.job_photos.max_kilobytes')]]);

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
            'trade' => $this->changeTrade(),
            'details' => $this->changeDetails(),
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
        $this->validate(['notesDraft' => ['nullable', 'string', 'max:'.config('getsorted.jobs.notes_max_length')]]);
        $this->notes = ChatWithSiya::scrub($this->notesDraft);
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
        abort_unless($job instanceof ServiceJob, 404);

        try {
            $postServiceJob->handle($user, $job);
        } catch (NoEligiblePros) {
            $this->waitlistPropertyPublicId = $this->propertyPublicId;
            $this->propertyPublicId = null;
            $this->stage = 'waitlist';
            $this->say(__('Sorry, we don’t have :trade pros near you yet. Want us to let you know when we do?', ['trade' => mb_strtolower($this->trade()->name ?? '')]));

            return;
        } catch (CannotPostServiceJob $exception) {
            throw ValidationException::withMessages(['post' => $exception->getMessage()]);
        }

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => __('Confirm booking')];
        $this->stage = 'posted';
        $parked = $this->parked !== [] ? ' '.__('Once this one is sorted, we can book “:job” too.', ['job' => $this->parked[0]]) : '';
        $this->say(__('Your job is booked. I’m sharing it with vetted pros near you now, and up to 5 of them can send you quotes to compare. We’ll WhatsApp you as they come in.').$parked);
    }

    /** After a job is posted: carry on with the next job the customer mentioned, starting a fresh conversation for it. */
    public function startNextJob(): void
    {
        abort_unless($this->stage === 'posted' && $this->parked !== [], 404);

        $next = $this->parked[0];
        $remaining = array_slice($this->parked, 1);
        session()->forget(self::SESSION_KEY);
        $this->startOver();
        $this->parked = $remaining;
        $this->say(__('Let’s sort out: :job. Tell me a bit more about it.', ['job' => $next]));
    }

    public function restart(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->startOver();
        $this->persist();
    }

    public function render(): View
    {
        $trade = $this->trade();
        $navigationTrades = $this->activeTrades()->sortBy(fn (Trade $trade): int => ['plumbing' => 0, 'electrical' => 1, 'painting' => 2, 'tiling' => 3][$trade->key] ?? 4)->values();
        $draft = in_array($this->stage, ['photos', 'summary'], true) ? $this->draft() : null;
        $photos = $draft?->getMedia(ServiceJob::PHOTO_COLLECTION) ?? collect();
        $available = app(ChatWithSiya::class)->available();

        return view('livewire.booking.thread', [
            'available' => $available,
            'trades' => $this->stage === 'chat' && $trade === null && ($this->showTradeShortcuts || $this->retryPending || ! $available) ? $navigationTrades : new Collection,
            'trade' => $trade,
            'ready' => $this->isReady(),
            'properties' => in_array($this->stage, ['where', 'add_property'], true) && $this->isCustomer()
                ? $this->user()?->properties()->latest()->get() ?? new Collection : new Collection,
            'propertyTypes' => PropertyType::cases(),
            'minDate' => LocalTime::today()->toDateString(),
            'maxDate' => LocalTime::today()->addDays((int) config('getsorted.jobs.booking_days_ahead'))->toDateString(),
            'windows' => $this->stage === 'when' ? TimeWindow::cases() : [],
            'photos' => $photos,
            'photoUrls' => $draft === null ? [] : $photos->mapWithKeys(fn ($photo): array => [$photo->uuid => $draft->photoUrl($photo)])->all(),
            'summary' => $this->stage === 'summary' ? $this->summary($draft) : null,
            'isGuest' => ! $this->user() instanceof User,
            'needsPhone' => $this->user()?->hasRole(Role::Customer->value) === true && $this->user()->phone_verified_at === null,
            'limitReached' => $this->customerMessages >= (int) config('getsorted.ai.chat_messages_per_conversation'),
        ])->title(__('Book a pro'));
    }

    // ── Internals ───────────────────────────────────────────────────────

    /** One Siya turn: the model changes the booking state through validated tools, then replies. */
    private function turn(ChatWithSiya $siya): void
    {
        $result = $siya->handle($this->bookingState(), $this->transcript(), (string) (auth()->id() ?? request()->ip()), $this->stage);

        // What the tools validated is kept even if the reply or the provider failed.
        $this->applyState($result['state']);

        if ($result['outcome'] !== AiOutcome::Ok || $result['reply'] === null) {
            $this->failures++;
            $this->retryPending = true;
            $this->messages[] = ['role' => 'assistant', 'kind' => 'error', 'text' => match ($result['outcome']) {
                AiOutcome::Throttled => __('Siya is busy right now. Your message is saved; try again in a moment, or choose a trade.'),
                AiOutcome::Timeout => __('That took too long. Your message is saved. Tap Try again.'),
                default => __('Sorry, I couldn’t read that just now. Your message is saved. Tap Try again.'),
            }];
            $this->persist();

            return;
        }

        $this->failures = 0;
        $this->retryPending = false;

        if ($result['emergency']) {
            $this->pauseForEmergency();

            return;
        }

        $this->say($result['reply']);

        if ($this->urgent && ! $this->safetyShown()) {
            $this->showSafetyAdvice();
        }

        if ($result['nextStepOffered']) {
            $this->bookingRequested = true;
        }

        $this->autosave();
        $this->advance(speak: false);
    }

    private function bookingState(): BookingState
    {
        return new BookingState($this->trade()?->key, $this->facts, $this->urgent, $this->parked, $this->bookingTurn, $this->nextStepOffered);
    }

    private function applyState(BookingState $state): void
    {
        $this->facts = $state->facts;
        $this->urgent = $state->urgent;
        $this->parked = $state->parked;
        $this->bookingTurn = $state->turn;
        $this->nextStepOffered = $state->nextStepOffered;

        if ($state->tradeKey !== null) {
            $trade = Trade::query()->where('key', $state->tradeKey)->where('is_active', true)->first();

            if ($trade instanceof Trade && $trade->id !== $this->tradeId) {
                $this->tradeId = $trade->id;
                $this->tradeCache = $trade;
                $this->recheckProperty();
            }
        }
    }

    /** A new trade keeps the draft and photos; the saved address is checked again before posting. */
    private function recheckProperty(): void
    {
        if ($this->waitlistPropertyPublicId !== null) {
            $this->waitlistPropertyPublicId = null;
        }
    }

    /**
     * Moves to the first missing item and, when $speak, says that card's line.
     */
    private function advance(bool $speak = true): void
    {
        $before = $this->stage;

        $this->stage = match (true) {
            ! $this->isReady(), ! $this->bookingRequested => 'chat',
            $this->waitlistPropertyPublicId !== null && $this->propertyPublicId === null => 'waitlist',
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
                'waitlist' => __('We don’t currently have pros for this near your address. Choose another trade or join the waitlist.'),
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

    private function changeTrade(): void
    {
        $this->returnToSummary = false;
        $this->tradeId = null;
        $this->tradeCache = null;
        $this->bookingRequested = false;
        $this->stage = 'chat';
        $this->showTradeShortcuts = true;
        $this->say(__('Sure. Which kind of pro do you need? Everything you told me is kept.'));
    }

    private function changeDetails(): void
    {
        $this->notesDraft = $this->notes;
        $this->stage = 'notes';
        $this->say(__('Check what pros will see. Remove anything that’s wrong, or add a note.'));
    }

    private function changeStage(string $stage, string $line): void
    {
        if ($stage === 'where') {
            $this->propertyPublicId = null;
        }

        $this->stage = $stage;
        $this->say($line);
    }

    private function showSafetyAdvice(): void
    {
        foreach ($this->trade()->safety_advice ?? [] as $advice) {
            $this->messages[] = ['role' => 'assistant', 'kind' => 'safety', 'text' => $advice];
        }
    }

    private function safetyShown(): bool
    {
        return collect($this->messages)->contains(fn (array $message): bool => ($message['kind'] ?? null) === 'safety');
    }

    private function isReady(): bool
    {
        return $this->tradeId !== null && ($this->facts !== [] || trim($this->notes) !== '');
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
        $this->reset([...self::PERSISTED, 'message', 'notesDraft', 'photoUpload', 'newStreet', 'newArea', 'newPostal', 'newType']);
        $this->tradeCache = null;
        $this->say(__('Hi, I’m Siya, GetSorted’s AI assistant. Tell me what’s happening at home, or ask me about GetSorted.'));
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
            $this->startOver();
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

    /** The customer's own scrubbed words, kept as notes when Siya can't read them. */
    private function addToNotes(string $text): void
    {
        if ($text === '' || str_contains($this->notes, $text)) {
            return;
        }

        $this->notes = mb_substr(trim($this->notes === '' ? $text : $this->notes."\n".$text), 0, (int) config('getsorted.jobs.notes_max_length'));
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
            array_filter($this->messages, fn (array $message): bool => ! isset($message['kind']) || ($message['role'] === 'customer' && $message['kind'] === 'answer')),
        ));
    }

    /** Saves the draft for a signed-in customer and returns it (a guest's thread stays in the session). */
    private function autosave(): ?ServiceJob
    {
        $user = $this->user();
        $trade = $this->trade();

        if (! $user instanceof User || ! $this->isCustomer() || ! $trade instanceof Trade) {
            return null;
        }

        $job = $this->draft();

        // Pick up an unfinished draft for the same trade rather than start another, keeping its notes.
        if (! $job instanceof ServiceJob) {
            $job = ServiceJob::query()->where('customer_id', $user->id)->where('trade_id', $trade->id)
                ->where('status', ServiceJobStatus::Draft)->latest('updated_at')->first();
            $earlier = trim((string) $job?->customer_notes);

            if ($earlier !== '' && ! str_contains($this->notes, $earlier)) {
                $this->notes = mb_substr(trim($earlier."\n".$this->notes), 0, (int) config('getsorted.jobs.notes_max_length'));
            }
        }

        try {
            $job = app(SaveBookingDraft::class)->handle($user, $trade, $job, new BookingData(
                facts: $this->facts,
                notes: $this->notes === '' ? null : $this->notes,
                propertyPublicId: $this->propertyPublicId,
                preferredDate: $this->timeWindow === '' ? null : $this->parseDate($this->chosenDate),
                timeWindow: TimeWindow::tryFrom($this->timeWindow),
                urgent: $this->urgent,
            ));
        } catch (CannotPostServiceJob $exception) {
            throw ValidationException::withMessages(['post' => $exception->getMessage()]);
        }

        $this->jobPublicId = $job->public_id;

        return $job;
    }

    private function resumeDraft(ServiceJob $job): void
    {
        abort_unless($this->user()?->can('update', $job) === true && $job->status === ServiceJobStatus::Draft, 404);

        $saved = session(self::SESSION_KEY);
        if (is_array($saved) && ($saved['jobPublicId'] ?? null) === $job->public_id) {
            $this->restore();
            if ($this->stage === 'signin' && $this->isCustomer()) {
                $this->advance();
            }

            return;
        }

        $this->startOver();
        $trade = Trade::query()->findOrFail($job->trade_id);
        $this->tradeId = $trade->id;
        $this->tradeCache = $trade;
        $this->jobPublicId = $job->public_id;
        $this->facts = $job->facts;
        $this->urgent = $job->urgency === Urgency::Urgent;
        $this->notes = (string) $job->customer_notes;
        $this->propertyPublicId = $job->property?->public_id;
        $this->preferredDate = (string) $job->preferred_date?->toDateString();
        $this->chosenDate = $this->preferredDate;
        $this->timeWindow = (string) $job->time_window?->value;
        $this->bookingRequested = true;
        $this->photosDone = $this->timeWindow !== '';

        $this->messages[] = ['role' => 'assistant', 'kind' => 'done', 'label' => __('You’re booking'), 'text' => $trade->name];
        $this->advance();
    }

    private function draft(): ?ServiceJob
    {
        $user = $this->user();

        if ($this->jobPublicId === null || ! $user instanceof User) {
            return null;
        }

        return ServiceJob::query()->with(['trade', 'property'])->where('public_id', $this->jobPublicId)
            ->where('customer_id', $user->id)->where('status', ServiceJobStatus::Draft)->first();
    }

    /**
     * @return array{key: ?string, facts: list<string>, property: ?Property, when: string, urgent: bool, advice: list<string>, guidance: bool}
     */
    private function summary(?ServiceJob $draft): array
    {
        $trade = $this->trade();
        $urgent = $this->timeWindow === TimeWindow::Today->value || $this->urgent;

        return [
            'key' => $draft instanceof ServiceJob ? JobSummaryInput::hash($draft) : null,
            'facts' => array_map(fn (array $fact): string => $fact['text'], $this->facts),
            'property' => $this->selectedProperty(),
            'when' => $this->whenLabel(),
            'urgent' => $urgent,
            'advice' => $trade instanceof Trade ? $trade->safety_advice : [],
            'guidance' => $trade instanceof Trade && ($trade->safety_advice !== [] || $trade->registration === RegistrationType::ElectricalRegisteredPerson),
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

    /** @return Collection<int, Trade> */
    private function activeTrades(): Collection
    {
        return Trade::query()->where('is_active', true)->orderBy('sort')->get();
    }

    private function countCoverageCheck(): void
    {
        $key = 'coverage:check:'.hash_hmac('sha256', (string) request()->ip(), (string) config('app.key'));

        if (RateLimiter::tooManyAttempts($key, (int) config('getsorted.waitlist.checks_per_hour'))) {
            throw ValidationException::withMessages(['where' => __('Please try again later.')]);
        }

        RateLimiter::hit($key, 3600);
    }

    private function threadUrl(): string
    {
        $trade = $this->trade();

        return $trade instanceof Trade ? route('book.trade', $trade, false) : route('book', [], false);
    }

    private ?Trade $tradeCache = null;

    private function trade(): ?Trade
    {
        if ($this->tradeId === null) {
            return null;
        }

        return $this->tradeCache ??= Trade::query()->find($this->tradeId);
    }

    private function selectedProperty(): ?Property
    {
        return $this->propertyPublicId === null ? null
            : $this->user()?->properties()->where('public_id', $this->propertyPublicId)->first();
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
