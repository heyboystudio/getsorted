<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\Support\AssistantCalls;
use App\Domain\Assistant\Support\Redactor;
use App\Domain\Assistant\Support\SummaryRules;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Models\ScopingQuestion;
use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

/**
 * One Siya turn (spec 016): sends the scrubbed chat to the assistant and keeps
 * only what checks out: a reply without contact details or prices, an active
 * catalogue service, and answers that are valid for the confirmed service's questions.
 */
final readonly class ChatWithSiya
{
    private const int TRANSCRIPT_TURNS = 20;

    private const int MAX_REPLY_LENGTH = 600;

    public function __construct(private AssistantCalls $calls) {}

    public function available(): bool
    {
        return $this->calls->available();
    }

    /**
     * @param  list<array{role: 'customer'|'assistant', text: string}>  $transcript  customer text already scrubbed
     * @param  array<string, mixed>  $answers
     * @return array{outcome: AiOutcome, reply: ?string, suggested: ?Service, answers: array<string, mixed>}
     */
    public function handle(array $transcript, ?Service $confirmed, array $answers, string $visitorKey): array
    {
        $services = $this->activeServices();
        $questions = $confirmed instanceof Service ? $confirmed->questions : new Collection;

        $request = new ChatRequest(
            $this->catalogue($services),
            $confirmed?->key,
            $questions->map(fn (ScopingQuestion $question): array => [
                'key' => $question->key, 'prompt' => $question->prompt, 'type' => $question->type->value,
                'options' => $question->options, 'required' => $question->required,
            ])->values()->all(),
            $answers,
            array_slice($transcript, -self::TRANSCRIPT_TURNS),
        );

        $result = ['outcome' => AiOutcome::Error, 'reply' => null, 'suggested' => null, 'answers' => []];

        [$outcome] = $this->calls->call(
            AiPurpose::Chat,
            'assistant:chat-rate:'.$visitorKey,
            (int) config('sortd.ai.chat_messages_per_hour'),
            fn (ScopingAssistant $assistant): ChatReply => $assistant->chat($request),
            function (ChatReply $reply) use ($services, $confirmed, $questions, &$result): bool {
                $text = $reply->reply === null ? null : trim($reply->reply);

                // Reuse spec 007's output rules: no contact details, URLs or money amounts.
                if ($text === null || $text === '' || mb_strlen($text) > self::MAX_REPLY_LENGTH || ! SummaryRules::acceptable($text)) {
                    return false;
                }

                $result['reply'] = $text;
                $result['suggested'] = $confirmed instanceof Service ? null : $services->first(
                    fn (Service $service): bool => $service->key === $reply->serviceKey && $service->trade->key === $reply->tradeKey,
                );
                $result['answers'] = $confirmed instanceof Service ? $this->validAnswers($questions, $reply->answers) : [];

                return true;
            },
        );

        $result['outcome'] = $outcome;

        return $result;
    }

    /** Scrubs a customer message before it is stored or sent (spec 016, AC13). */
    public static function scrub(string $message): string
    {
        return Redactor::strip(trim($message));
    }

    /**
     * @param  Collection<int, ScopingQuestion>  $questions
     * @param  array<string, mixed>  $proposed
     * @return array<string, mixed>
     */
    private function validAnswers(Collection $questions, array $proposed): array
    {
        $valid = [];

        foreach ($questions as $question) {
            $values = $proposed[$question->key] ?? null;

            if (! is_array($values) || $values === []) {
                continue;
            }

            $raw = $question->type === QuestionType::MultiChoice ? array_values($values) : $values[0];

            if (ScopingAnswers::check($question, $raw)['ok']) {
                $valid[$question->key] = $raw;
            }
        }

        return $valid;
    }

    /** @return Collection<int, Service> */
    private function activeServices(): Collection
    {
        return Service::query()->with('trade')
            ->where('is_active', true)->whereHas('trade', fn ($trade) => $trade->where('is_active', true))
            ->orderBy('sort')->get();
    }

    /**
     * @param  Collection<int, Service>  $services
     * @return array<string, array{name: string, services: array<string, string>}>
     */
    private function catalogue(Collection $services): array
    {
        $catalogue = [];

        foreach ($services as $service) {
            $catalogue[$service->trade->key]['name'] = $service->trade->name;
            $catalogue[$service->trade->key]['services'][$service->key] = $service->name;
        }

        return $catalogue;
    }
}
