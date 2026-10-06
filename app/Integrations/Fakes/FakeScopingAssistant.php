<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\AssistantUsage;
use App\Contracts\Data\ChatReply;
use App\Contracts\Data\ChatRequest;
use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\Exceptions\AssistantUnavailable;
use App\Contracts\ScopingAssistant;
use Closure;
use PHPUnit\Framework\Assert;

/**
 * Deterministic stand-in for the AI assistant. A scripted chat turn is a closure that receives the request and
 * drives the real BookingToolbox exactly as the model would, then returns the reply text; with nothing scripted
 * Siya just asks for more. Tests can also script an outage to check fallbacks.
 */
final class FakeScopingAssistant implements ScopingAssistant
{
    private ?string $summary = null;

    private ?AssistantUnavailable $failure = null;

    private ?Closure $whileSummarising = null;

    /** @var list<string> */
    private array $descriptionsSeen = [];

    /** @var list<array{trade: string, facts: list<string>}> */
    private array $summaryRequests = [];

    /** @var list<Closure(ChatRequest): ?string> */
    private array $chatScripts = [];

    /** @var list<ChatRequest> */
    private array $chatRequests = [];

    /**
     * Queue a scripted Siya turn. The closure may call `$request->toolbox` methods, then returns the reply text.
     *
     * @param  Closure(ChatRequest): ?string  $script
     */
    public function willChat(Closure $script): self
    {
        $this->chatScripts[] = $script;

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

        $script = array_shift($this->chatScripts);

        return new ChatReply($script instanceof Closure ? $script($request) : __('Thanks! Tell me a bit more about the problem.'), $this->usage(), 2, $request->toolbox->calls);
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

    public function summarise(string $tradeName, array $facts, string $description): ScopingSummaryReply
    {
        $this->descriptionsSeen[] = $description;
        $this->summaryRequests[] = ['trade' => $tradeName, 'facts' => $facts];

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
     * Facts passed with each summary request, so tests can prove personal data was stripped.
     *
     * @return list<list<string>>
     */
    public function summaryFactsSeen(): array
    {
        return array_column($this->summaryRequests, 'facts');
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
