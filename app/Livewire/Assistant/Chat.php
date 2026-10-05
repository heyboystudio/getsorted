<?php

declare(strict_types=1);

namespace App\Livewire\Assistant;

use App\Domain\Assistant\Actions\ChatWithSiya;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Livewire\Booking\Wizard;
use App\Models\ScopingQuestion;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Siya, the booking chat (spec 016). Siya works out the service and collects the
 * answers; the customer then continues in the normal booking flow for address,
 * photos, review and posting. Siya never posts a job. The chat lives in the
 * session only (scrubbed text), so it survives a refresh or sign-in.
 */
#[Layout('components.layouts.app')]
final class Chat extends Component
{
    public const string SESSION_KEY = 'siya.chat';

    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 1000;

    /** Gas, sparks, smoke or water near electrics: show the stop-first card (AC4). */
    private const string EMERGENCY = '/\b(gas|spark\w*|smok\w*|burning smell|electrocut\w*|shock\w*|flood\w*)\b/iu';

    public string $message = '';

    /** @var list<array{role: 'customer'|'assistant', text: string, kind?: string}> */
    #[Locked]
    public array $messages = [];

    #[Locked]
    public ?int $suggestedServiceId = null;

    #[Locked]
    public ?int $serviceId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $answers = [];

    #[Locked]
    public int $customerMessages = 0;

    #[Locked]
    public int $failures = 0;

    #[Locked]
    public bool $emergencyShown = false;

    public function mount(): void
    {
        /** @var array<string, mixed>|null $saved */
        $saved = session(self::SESSION_KEY);

        if (is_array($saved)) {
            foreach (['messages', 'suggestedServiceId', 'serviceId', 'answers', 'customerMessages', 'failures', 'emergencyShown'] as $field) {
                if (array_key_exists($field, $saved)) {
                    $this->{$field} = $saved[$field];
                }
            }
        }

        if ($this->messages === []) {
            $this->say(__("Hi, I'm Siya, Sortd's AI assistant. Tell me what's wrong at home and I'll help you find the right pro. For example: \"my geyser is leaking\"."));
        }
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

        if ($this->limitReached()) {
            return;
        }

        $this->message = '';
        $this->customerMessages++;
        $this->messages[] = ['role' => 'customer', 'text' => ChatWithSiya::scrub($text)];

        if (! $this->emergencyShown && preg_match(self::EMERGENCY, $text) === 1) {
            $this->emergencyShown = true;
            $this->messages[] = ['role' => 'assistant', 'kind' => 'emergency', 'text' => __('If you can smell gas, see sparks or smoke, or water is near electrics: switch off at the mains if it is safe, keep away, and call emergency services on 112 or the city on 080 131 3111 first.')];
        }

        $this->turn($siya);
    }

    public function confirmService(): void
    {
        $service = $this->suggestedService();
        abort_unless($service instanceof Service, 404);

        $this->serviceId = $service->id;
        $this->suggestedServiceId = null;
        $this->answers = [];

        foreach ($service->safety_advice as $advice) {
            $this->messages[] = ['role' => 'assistant', 'kind' => 'safety', 'text' => $advice];
        }

        $this->say($this->nextQuestion() instanceof ScopingQuestion
            ? __('Great, :service it is. A few quick questions so pros know what to expect.', ['service' => $service->name])
            : __('Great, :service it is.', ['service' => $service->name]));
    }

    public function rejectService(): void
    {
        $this->suggestedServiceId = null;
        $this->say(__('No problem. Tell me a bit more, or choose a service yourself from the list of trades.'));
    }

    /** A tapped answer chip for the current question: recorded directly, no AI call. */
    public function answer(string $questionKey, string $value): void
    {
        $question = $this->nextQuestion();
        abort_unless($question instanceof ScopingQuestion && $question->key === $questionKey, 404);

        $raw = $question->type === QuestionType::MultiChoice ? [$value] : $value;
        abort_unless(ScopingAnswers::check($question, $raw)['ok'], 422);

        $this->messages[] = ['role' => 'customer', 'kind' => 'answer', 'text' => $question->type === QuestionType::YesNo ? ($value === 'yes' ? __('Yes') : __('No')) : $value];
        $this->answers[$question->key] = $raw;
        $this->persist();
    }

    /** Hands the confirmed service, answers and the customer's own words to the booking flow (AC6). */
    public function continueBooking(): void
    {
        $service = $this->service();
        abort_unless($service instanceof Service && ! $this->hasMissingAnswers($service), 404);

        $notes = collect($this->messages)->where('role', 'customer')->whereNull('kind')->pluck('text')->implode("\n");

        session()->put(Wizard::RESUME_KEY, [
            'service_id' => $service->id,
            'answers' => $this->answers,
            'notes' => mb_substr($notes, 0, (int) config('sortd.jobs.notes_max_length')),
            'from_assistant' => true,
        ]);

        $this->redirectRoute('booking.start', ['trade' => $service->trade, 'service' => $service->key]);
    }

    public function restart(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->reset(['messages', 'suggestedServiceId', 'serviceId', 'answers', 'customerMessages', 'failures', 'emergencyShown', 'message']);
        $this->mount();
    }

    public function render(): View
    {
        $service = $this->service();

        return view('livewire.assistant.chat', [
            'available' => app(ChatWithSiya::class)->available(),
            'suggested' => $this->suggestedService(),
            'service' => $service,
            'question' => $this->nextQuestion(),
            'ready' => $service instanceof Service && ! $this->hasMissingAnswers($service),
            'limitReached' => $this->customerMessages >= (int) config('sortd.ai.chat_messages_per_conversation'),
        ])->title(__('Get help with a job'));
    }

    private function turn(ChatWithSiya $siya): void
    {
        $transcript = array_values(array_map(
            fn (array $message): array => ['role' => $message['role'], 'text' => $message['text']],
            array_filter($this->messages, fn (array $message): bool => ! isset($message['kind'])),
        ));

        $result = $siya->handle($transcript, $this->service(), $this->answers, (string) (auth()->id() ?? request()->ip()));

        if ($result['outcome'] !== AiOutcome::Ok || $result['reply'] === null) {
            $this->failures++;
            $this->messages[] = ['role' => 'assistant', 'kind' => 'error', 'text' => $result['outcome'] === AiOutcome::Throttled
                ? __('Siya is busy right now. You can still book directly.')
                : __('Sorry, something went wrong. Try again.')];
            $this->persist();

            return;
        }

        $this->failures = 0;
        $this->answers = array_merge($this->answers, $result['answers']);
        $this->say($result['reply']);

        if ($this->serviceId === null && $result['suggested'] instanceof Service) {
            $this->suggestedServiceId = $result['suggested']->id;
            $this->persist();
        }
    }

    private function limitReached(): bool
    {
        if ($this->customerMessages < (int) config('sortd.ai.chat_messages_per_conversation')) {
            return false;
        }

        throw ValidationException::withMessages(['message' => __('This chat is full. Start over, or book directly.')]);
    }

    private function say(string $text): void
    {
        $this->messages[] = ['role' => 'assistant', 'text' => $text];
        $this->persist();
    }

    private function persist(): void
    {
        session()->put(self::SESSION_KEY, [
            'messages' => $this->messages,
            'suggestedServiceId' => $this->suggestedServiceId,
            'serviceId' => $this->serviceId,
            'answers' => $this->answers,
            'customerMessages' => $this->customerMessages,
            'failures' => $this->failures,
            'emergencyShown' => $this->emergencyShown,
        ]);
    }

    private function nextQuestion(): ?ScopingQuestion
    {
        return $this->service()?->questions->first(fn (ScopingQuestion $question): bool => ! array_key_exists($question->key, $this->answers) && $question->required)
            ?? $this->service()?->questions->first(fn (ScopingQuestion $question): bool => ! array_key_exists($question->key, $this->answers));
    }

    private function hasMissingAnswers(Service $service): bool
    {
        return $service->questions->contains(fn (ScopingQuestion $question): bool => $question->required && ! array_key_exists($question->key, $this->answers));
    }

    private ?Service $serviceCache = null;

    private function service(): ?Service
    {
        if ($this->serviceId === null) {
            return null;
        }

        return $this->serviceCache ??= Service::query()->with(['questions', 'trade'])->where('is_active', true)->find($this->serviceId);
    }

    private function suggestedService(): ?Service
    {
        return $this->suggestedServiceId === null ? null
            : Service::query()->with('trade')->where('is_active', true)->find($this->suggestedServiceId);
    }
}
