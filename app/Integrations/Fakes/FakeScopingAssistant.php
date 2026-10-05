<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\AssistantUsage;
use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\Data\ScopingSuggestion;
use App\Contracts\Data\ScopingSuggestionReply;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;
use App\Contracts\ScopingAssistant;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * Deterministic stand-in for the AI assistant. Returns nothing unless a test
 * scripts a response, so callers' "no suggestion" fallback is the default path.
 * Tests can script invalid output (unknown keys, hostile text) or an outage to check fallbacks.
 */
final class FakeScopingAssistant implements ScopingAssistant
{
    private ?ScopingSuggestion $suggestion = null;

    private ?string $summary = null;

    private ?AssistantUnavailable $failure = null;

    private ?Closure $whileSummarising = null;

    /** @var list<string> */
    private array $descriptionsSeen = [];

    /** @var list<array{service: string, answers: array<string, string|list<string>>}> */
    private array $summaryRequests = [];

    /** @var list<ChatReply> */
    private array $chatReplies = [];

    /** @var list<ChatRequest> */
    private array $chatRequests = [];

    /**
     * Queue Siya replies, used in order; with none queued Siya just says hello.
     *
     * @param  array<string, mixed>  $answers
     */
    public function willChat(?string $reply, ?string $tradeKey = null, ?string $serviceKey = null, array $answers = []): self
    {
        $this->chatReplies[] = new ChatReply($reply, $tradeKey, $serviceKey, $answers, $this->usage());

        return $this;
    }

    /** @return list<ChatRequest> */
    public function chatRequests(): array
    {
        return $this->chatRequests;
    }

    public function chat(ChatRequest $request): ChatReply
    {
        $this->chatRequests[] = $request;

        if ($this->failure instanceof AssistantUnavailable) {
            throw $this->failure;
        }

        return array_shift($this->chatReplies) ?? new ChatReply(__('Thanks! Tell me a bit more about the problem.'), null, null, [], $this->usage());
    }

    public function willSuggest(?ScopingSuggestion $suggestion): self
    {
        $this->suggestion = $suggestion;

        return $this;
    }

    public function willSummarise(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function willFail(bool $timedOut = false): self
    {
        $this->failure = new AssistantUnavailable($timedOut);

        return $this;
    }

    /** Runs during a summary request, so tests can change the draft while the "model" is thinking. */
    public function whileSummarising(Closure $callback): self
    {
        $this->whileSummarising = $callback;

        return $this;
    }

    public function suggestService(string $description, array $catalogue): ScopingSuggestionReply
    {
        $this->descriptionsSeen[] = $description;

        if ($this->failure instanceof AssistantUnavailable) {
            throw $this->failure;
        }

        return new ScopingSuggestionReply($this->suggestion, $this->usage());
    }

    public function summarise(string $serviceKey, array $answers, string $description): ScopingSummaryReply
    {
        $this->descriptionsSeen[] = $description;
        $this->summaryRequests[] = ['service' => $serviceKey, 'answers' => $answers];

        if ($this->failure instanceof AssistantUnavailable) {
            throw $this->failure;
        }

        if ($this->whileSummarising instanceof Closure) {
            ($this->whileSummarising)();
        }

        return new ScopingSummaryReply($this->summary, $this->usage());
    }

    /**
     * Every description passed to the assistant, so tests can prove personal data was stripped.
     *
     * @return list<string>
     */
    public function descriptionsSeen(): array
    {
        return $this->descriptionsSeen;
    }

    /**
     * Answers passed with each summary request, so tests can prove personal data was stripped.
     *
     * @return list<array<string, string|list<string>>>
     */
    public function summaryAnswersSeen(): array
    {
        return array_column($this->summaryRequests, 'answers');
    }

    public function assertSummaryRequests(int $count): void
    {
        Assert::assertCount($count, $this->summaryRequests, 'Unexpected number of summary requests.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->descriptionsSeen, 'The assistant was called.');
    }

    private function usage(): AssistantUsage
    {
        return new AssistantUsage('fake', 'fake-model', 120, 40);
    }
}
