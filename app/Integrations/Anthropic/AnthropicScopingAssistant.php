<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic;

use App\Contracts\Data\AssistantUsage;
use App\Contracts\Data\ScopingSuggestion;
use App\Contracts\Data\ScopingSuggestionReply;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;
use App\Contracts\ScopingAssistant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * ScopingAssistant on Anthropic through the Laravel AI SDK (spec 007). Bound only
 * outside local/testing and only when an API key is configured; the domain still
 * keeps it idle until the `ai.enabled` setting is on (founder decision 1).
 */
final class AnthropicScopingAssistant implements ScopingAssistant
{
    private const string PROVIDER = 'anthropic';

    public function suggestService(string $description, array $catalogue): ScopingSuggestionReply
    {
        $response = $this->ask(new ServiceSuggestionAgent, 'Catalogue: '.$this->json($catalogue)
            ."\n<customer_description>".$this->json($description).'</customer_description>');

        $data = $response instanceof StructuredAgentResponse ? $response->structured : [];
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

        $summary = $response instanceof StructuredAgentResponse ? ($response->structured['summary'] ?? null) : null;

        return new ScopingSummaryReply(is_string($summary) && trim($summary) !== '' ? trim($summary) : null, $this->usage($response));
    }

    /** @throws AssistantUnavailable */
    private function ask(Agent $agent, string $prompt): AgentResponse
    {
        try {
            return $agent->prompt($prompt, provider: self::PROVIDER, model: $this->model(), timeout: (int) config('sortd.ai.timeout_seconds'));
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
        return new AssistantUsage(self::PROVIDER, $this->model(), $response->usage->inputTokens, $response->usage->outputTokens);
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
