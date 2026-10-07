<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\State\BookingState;
use App\Domain\Assistant\Support\AssistantCalls;
use App\Domain\Assistant\Support\BookingToolbox;
use App\Domain\Assistant\Support\ProductFacts;
use App\Domain\Assistant\Support\Redactor;
use App\Domain\Assistant\Support\SummaryRules;
use App\Models\Trade;

/**
 * One Siya turn (spec 020). The model changes the booking state only through BookingToolbox, which validates every
 * proposal; this action then guards the reply text. A reply that breaks a rule is regenerated once, then replaced
 * by a deterministic reply built from the state, so a turn never fails because of wording and the state is never lost.
 */
final readonly class ChatWithSiya
{
    private const int MAX_REPLY_LENGTH = 600;

    public function __construct(private AssistantCalls $calls) {}

    public function available(): bool
    {
        return $this->calls->available();
    }

    /**
     * @param  list<array{role: 'customer'|'assistant', text: string}>  $transcript  the whole chat; the last entry is the customer's new message
     * @return array{outcome: AiOutcome, reply: ?string, state: BookingState, emergency: bool, nextStepOffered: bool, degraded: bool}
     */
    public function handle(BookingState $state, array $transcript, string $visitorKey, string $bookingStage = 'chat'): array
    {
        $trades = Trade::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'key')->all();
        $transcript = array_map(fn (array $turn): array => ['role' => $turn['role'], 'text' => self::scrub($turn['text'])], $transcript);

        // Work on a copy: the caller keeps the original unless the turn produced a usable state.
        $working = BookingState::fromArray($state->toArray());
        $working->turn++;
        $toolbox = new BookingToolbox($working, $trades, $transcript);
        $request = new ChatRequest($toolbox, $transcript, ProductFacts::all(), $bookingStage);

        [$outcome, $reply] = $this->calls->call(
            AiPurpose::Chat,
            'assistant:chat-rate:'.$visitorKey,
            (int) config('getsorted.ai.chat_messages_per_hour'),
            function (ScopingAssistant $assistant) use ($request, $toolbox, $transcript, $bookingStage): ChatReply {
                $reply = $assistant->chat($request);

                if (! self::acceptable($reply->reply)) {
                    $reply = $assistant->chat(new ChatRequest($toolbox, $transcript, ProductFacts::all(), $bookingStage,
                        'Your reply broke a rule: replies must be plain text under '.self::MAX_REPLY_LENGTH.' characters with no prices, phone numbers, emails, addresses or links. Rewrite it. Tool changes you already made are saved.'));
                }

                return $reply;
            },
            fn (ChatReply $reply): bool => self::acceptable($reply->reply),
        );

        // Tool writes were validated when made, so they are kept even when the wording or the provider failed.
        $kept = $toolbox->calls > 0 || $outcome === AiOutcome::Ok;
        $result = [
            'outcome' => $outcome,
            'reply' => null,
            'state' => $kept ? $working : $state,
            'emergency' => $toolbox->emergencyFlagged,
            'nextStepOffered' => $working->nextStepOffered,
            'degraded' => false,
        ];

        if ($outcome === AiOutcome::Ok && $reply instanceof ChatReply) {
            $result['reply'] = trim((string) $reply->reply);

            return $result;
        }

        // The reply broke a rule twice: say something true about the state instead of failing the turn.
        if ($outcome === AiOutcome::Invalid) {
            $result['outcome'] = AiOutcome::Ok;
            $result['reply'] = self::fallbackReply($working, $trades);
            $result['degraded'] = true;
        }

        return $result;
    }

    /** Scrubs a customer message before it is stored or sent (spec 016, AC13). */
    public static function scrub(string $message): string
    {
        return Redactor::strip(trim($message));
    }

    private static function acceptable(?string $reply): bool
    {
        $reply = $reply === null ? '' : trim($reply);

        // Reuse spec 007's output rules: no contact details, URLs or money amounts.
        return $reply !== '' && mb_strlen($reply) <= self::MAX_REPLY_LENGTH && SummaryRules::acceptable($reply);
    }

    /**
     * A plain, true statement of where the booking stands, used only when the model's wording is unusable.
     *
     * @param  array<string, string>  $trades
     */
    private static function fallbackReply(BookingState $state, array $trades): string
    {
        if ($state->isReady()) {
            return __('Thanks, I’ve noted that. Would you like to go ahead and book a pro?');
        }

        if ($state->tradeKey === null && $state->facts !== []) {
            return __('Thanks, I’ve noted that. Which kind of pro do you need: :trades?', ['trades' => implode(', ', array_map('mb_strtolower', array_values($trades)))]);
        }

        return __('Thanks. Tell me a bit more about what’s happening.');
    }
}
