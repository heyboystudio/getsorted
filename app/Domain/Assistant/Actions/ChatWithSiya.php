<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\Enums\ConversationIntent;
use App\Domain\Assistant\Support\AssistantCalls;
use App\Domain\Assistant\Support\ProductFacts;
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
    private const int MAX_REPLY_LENGTH = 600;

    public function __construct(private AssistantCalls $calls) {}

    public function available(): bool
    {
        return $this->calls->available();
    }

    /**
     * @param  list<array{role: 'customer'|'assistant', text: string}>  $transcript  customer text already scrubbed
     * @param  array<string, mixed>  $answers
     * @return array{outcome: AiOutcome, reply: ?string, suggested: ?Service, answers: array<string, mixed>, intent: ?ConversationIntent, questionKey: ?string, jobNotes: ?list<string>}
     */
    public function handle(array $transcript, ?Service $confirmed, array $answers, string $visitorKey, string $bookingStage = 'describe', ?string $pendingQuestionKey = null): array
    {
        $services = $this->activeServices();
        $transcript = array_map(fn (array $turn): array => ['role' => $turn['role'], 'text' => self::scrub($turn['text'])], $transcript);
        $answers = array_map(fn (mixed $answer): mixed => is_string($answer) ? self::scrub($answer) : (is_array($answer) ? array_map(fn (mixed $value): mixed => is_string($value) ? self::scrub($value) : $value, $answer) : $answer), $answers);
        $questions = $confirmed instanceof Service ? $confirmed->questions : new Collection;

        $request = new ChatRequest(
            $this->catalogue($services),
            $confirmed?->key,
            $questions->map(fn (ScopingQuestion $question): array => [
                'key' => $question->key, 'prompt' => $question->prompt, 'type' => $question->type->value,
                'options' => $question->options, 'required' => $question->required,
            ])->values()->all(),
            $answers,
            $transcript,
            $bookingStage,
            $pendingQuestionKey,
            ProductFacts::all(),
            $services->mapWithKeys(fn (Service $service): array => [$service->trade->key.':'.$service->key => $this->questionData($service->questions)])->all(),
        );

        $result = ['outcome' => AiOutcome::Error, 'reply' => null, 'suggested' => null, 'answers' => [], 'intent' => null, 'questionKey' => null, 'jobNotes' => null];

        [$outcome] = $this->calls->call(
            AiPurpose::Chat,
            'assistant:chat-rate:'.$visitorKey,
            (int) config('sortd.ai.chat_messages_per_hour'),
            fn (ScopingAssistant $assistant): ChatReply => $assistant->chat($request),
            function (ChatReply $reply) use ($services, $confirmed, $questions, $transcript, $answers, &$result): bool {
                $text = $reply->reply === null ? null : trim($reply->reply);

                // Reuse spec 007's output rules: no contact details, URLs or money amounts.
                if ($text === null || $text === '' || mb_strlen($text) > self::MAX_REPLY_LENGTH || ! SummaryRules::acceptable($text)) {
                    return false;
                }

                if (! $reply->intent instanceof ConversationIntent) {
                    return false;
                }

                $proposed = $reply->serviceKey === null && $reply->tradeKey === null ? null : $services->first(
                    fn (Service $service): bool => $service->key === $reply->serviceKey && $service->trade->key === $reply->tradeKey,
                );

                if ($reply->intent === ConversationIntent::HomeProblem && ($reply->serviceKey !== null || $reply->tradeKey !== null) && ! $proposed instanceof Service) {
                    return false;
                }

                $isJob = $reply->intent === ConversationIntent::HomeProblem;
                $answerQuestions = $proposed instanceof Service ? $proposed->questions : $questions;
                $valid = $isJob ? $this->validAnswers($answerQuestions, $reply->answers) : [];
                $known = $proposed !== null && $proposed->id !== $confirmed?->id ? $valid : array_merge($answers, $valid);
                if ($reply->questionKey !== null && $isJob && ! $answerQuestions->contains(fn (ScopingQuestion $question): bool => $question->key === $reply->questionKey && $question->required && ! array_key_exists($question->key, $known))) {
                    return false;
                }
                $notes = null;

                if ($isJob && $reply->jobNotes !== null) {
                    $customerText = array_column(array_filter($transcript, fn (array $turn): bool => $turn['role'] === 'customer'), 'text');
                    $notes = [];

                    foreach ($reply->jobNotes as $note) {
                        if (trim($note) === '' || ! collect($customerText)->contains(fn (string $source): bool => str_contains($source, $note))) {
                            return false;
                        }
                        $notes[] = self::scrub($note);
                    }

                    if (mb_strlen(implode("\n", $notes)) > (int) config('sortd.jobs.notes_max_length')) {
                        return false;
                    }
                }

                $result['reply'] = $text;
                $result['intent'] = $reply->intent;
                $result['suggested'] = $isJob ? $proposed : null;
                $result['answers'] = $valid;
                $result['questionKey'] = $isJob && $answerQuestions->contains('key', $reply->questionKey) && ! array_key_exists((string) $reply->questionKey, $valid) ? $reply->questionKey : null;
                $result['jobNotes'] = $notes;

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
        return Service::query()->with(['trade', 'questions'])
            ->where('is_active', true)->whereHas('trade', fn ($trade) => $trade->where('is_active', true))
            ->orderBy('sort')->get();
    }

    /**
     * @param  Collection<int, ScopingQuestion>  $questions
     * @return list<array{key: string, prompt: string, type: string, options: list<string>, required: bool}>
     */
    private function questionData(Collection $questions): array
    {
        return $questions->map(fn (ScopingQuestion $question): array => [
            'key' => $question->key, 'prompt' => $question->prompt, 'type' => $question->type->value,
            'options' => $question->options, 'required' => $question->required,
        ])->values()->all();
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
            $catalogue[$service->trade->key]['services'][$service->key] = $service->name.': '.$service->description;
        }

        return $catalogue;
    }
}
