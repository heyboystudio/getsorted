<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Contracts\Data\AssistantUsage;
use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\Data\ScopingSuggestion;
use App\Contracts\Data\ScopingSuggestionReply;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\ConversationIntent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * ScopingAssistant through the Laravel AI SDK (spec 007, 016): the Anthropic API,
 * Amazon Bedrock (EU, decision 043) or the Google Gemini API (decision 049), per
 * `sortd.ai.provider`. The class name predates the other providers.
 * Bound only when a provider is configured; the domain still keeps it idle
 * until the `ai.enabled` setting is on (founder decision 1).
 */
final class AnthropicScopingAssistant implements ScopingAssistant
{
    public function suggestService(string $description, array $catalogue): ScopingSuggestionReply
    {
        $response = $this->ask(new ServiceSuggestionAgent, 'Catalogue: '.$this->json($catalogue)
            ."\n<customer_description>".$this->json($description).'</customer_description>');

        $data = $this->structured($response, ['trade_key', 'service_key', 'confidence']);
        $trade = $data['trade_key'] ?? null;
        $service = $data['service_key'] ?? null;
        $confidence = $data['confidence'] ?? null;

        $suggestion = is_string($trade) && is_string($service) && $trade !== '' && $service !== ''
            && is_numeric($confidence) && $confidence >= 0 && $confidence <= 1
            ? new ScopingSuggestion($trade, $service, (float) $confidence)
            : null;

        return new ScopingSuggestionReply($suggestion, $this->usage($response));
    }

    public function summarise(string $serviceKey, array $answers, string $description): ScopingSummaryReply
    {
        $response = $this->ask(new JobSummaryAgent, 'Service: '.$this->json($serviceKey)
            ."\nAnswers: ".$this->json($answers)
            ."\n<customer_notes>".$this->json($description).'</customer_notes>');

        $summary = $this->structured($response, ['summary'])['summary'] ?? null;

        return new ScopingSummaryReply(is_string($summary) && trim($summary) !== '' ? trim($summary) : null, $this->usage($response));
    }

    public function chat(ChatRequest $request): ChatReply
    {
        $response = $this->ask(new SiyaAgent, $this->json([
            'catalogue' => $request->catalogue,
            'confirmed_service' => $request->confirmedServiceKey,
            'questions' => $request->questions,
            'answers' => $request->answers,
            'transcript' => $request->transcript,
            'booking_stage' => $request->bookingStage,
            'pending_question_key' => $request->pendingQuestionKey,
            'product_facts' => $request->productFacts,
            'service_questions' => $request->serviceQuestions,
        ]), (int) config('sortd.ai.chat_timeout_seconds'));

        $data = $this->structured($response, ['reply', 'trade_key', 'service_key', 'answers', 'intent', 'question_key', 'job_notes']);
        if (array_diff(['reply', 'trade_key', 'service_key', 'answers', 'intent', 'question_key', 'job_notes'], array_keys($data)) !== [] || ! is_array($data['job_notes'] ?? null) || count(array_filter($data['job_notes'], is_string(...))) !== count($data['job_notes'])) {
            $data = [];
        }
        $answers = [];

        foreach ((array) ($data['answers'] ?? []) as $item) {
            $key = data_get($item, 'question_key');
            $values = array_values(array_filter((array) data_get($item, 'values', []), is_string(...)));

            if (is_string($key) && $key !== '' && $values !== []) {
                $answers[$key] = $values;
            }
        }

        $text = fn (string $field): ?string => is_string($data[$field] ?? null) && trim($data[$field]) !== '' ? trim($data[$field]) : null;

        return new ChatReply($text('reply'), $text('trade_key'), $text('service_key'), $answers, $this->usage($response),
            ConversationIntent::tryFrom($text('intent') ?? ''), $text('question_key'),
            is_array($data['job_notes'] ?? null) ? array_values($data['job_notes']) : null);
    }

    /**
     * The structured reply, or nothing when it has keys outside the schema (spec 007, AC11).
     *
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function structured(AgentResponse $response, array $allowed): array
    {
        if (! $response instanceof StructuredAgentResponse || array_diff(array_keys($response->structured), $allowed) !== []) {
            return [];
        }

        return $response->structured;
    }

    /** @throws AssistantUnavailable */
    private function ask(Agent $agent, string $prompt, ?int $timeout = null): AgentResponse
    {
        try {
            return $agent->prompt($prompt, provider: $this->provider(), model: $this->model(), timeout: $timeout ?? (int) config('sortd.ai.timeout_seconds'));
        } catch (AiException|ConnectionException|RequestException $exception) {
            throw new AssistantUnavailable($this->timedOut($exception), $exception);
        }
    }

    private function timedOut(Throwable $exception): bool
    {
        for ($cause = $exception; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            if (str_contains($cause->getMessage(), 'timed out') || str_contains($cause->getMessage(), 'cURL error 28')) {
                return true;
            }
        }

        return false;
    }

    private function usage(AgentResponse $response): AssistantUsage
    {
        return new AssistantUsage($this->provider(), $this->model(), $response->usage->inputTokens, $response->usage->outputTokens);
    }

    private function provider(): string
    {
        return match (config('sortd.ai.provider')) {
            'bedrock' => 'bedrock',
            'gemini' => 'gemini',
            default => 'anthropic',
        };
    }

    private function model(): string
    {
        return (string) config('sortd.ai.model');
    }

    /** JSON with <, > and & escaped so customer text cannot close the data tags. */
    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
