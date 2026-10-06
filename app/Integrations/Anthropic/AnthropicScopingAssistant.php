<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Contracts\Data\AssistantUsage;
use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;
use App\Contracts\ScopingAssistant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
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
    public function summarise(string $tradeName, array $facts, string $description): ScopingSummaryReply
    {
        $response = $this->ask(new JobSummaryAgent, 'Trade: '.$this->json($tradeName)
            ."\nFacts: ".$this->json($facts)
            ."\n<customer_notes>".$this->json($description).'</customer_notes>');

        $summary = $this->structured($response, ['summary'])['summary'] ?? null;

        return new ScopingSummaryReply(is_string($summary) && trim($summary) !== '' ? trim($summary) : null, $this->usage($response));
    }

    public function chat(ChatRequest $request): ChatReply
    {
        $transcript = $request->transcript;
        $latest = array_pop($transcript);
        $toolbox = $request->toolbox;
        $state = $toolbox->digest();

        $prompt = '<booking_state>'.$this->json($state).'</booking_state>'
            ."\n<booking_stage>".$this->json($request->bookingStage).'</booking_stage>'
            ."\n<customer_message>".$this->json($latest['text'] ?? '').'</customer_message>'
            .($request->guardFeedback === null ? '' : "\n<reviewer_note>".$this->json($request->guardFeedback).'</reviewer_note>');

        $response = $this->ask(new SiyaAgent($toolbox, $transcript, $request->productFacts), $prompt, (int) config('sortd.ai.chat_timeout_seconds'));

        return new ChatReply(trim($response->text) === '' ? null : trim($response->text), $this->usage($response), max(1, $response->steps->count()), $response->toolCalls->count());
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
        $send = fn (): AgentResponse => $agent->prompt($prompt, provider: $this->provider(), model: $this->model(), timeout: $timeout ?? (int) config('sortd.ai.timeout_seconds'));

        try {
            try {
                return $send();
            } catch (ProviderOverloadedException) {
                // Demand spikes at the provider are usually brief; tool writes are idempotent, so one retry is safe.
                usleep(700_000);

                return $send();
            }
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
