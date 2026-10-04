<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\MessageReceipt;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Support\PhoneNumbers;
use Closure;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Records messages instead of sending them. In local development it also logs
 * that a message was sent, with the phone number masked and no parameter values.
 */
final class FakeMessagingChannel implements MessagingChannel
{
    /** @var list<OutgoingMessage> */
    private array $sent = [];

    private bool $failNext = false;

    /** Makes the next send throw, to test what happens when the provider is down. */
    public function failNextSend(): self
    {
        $this->failNext = true;

        return $this;
    }

    public function send(OutgoingMessage $message): MessageReceipt
    {
        if ($this->failNext) {
            $this->failNext = false;

            throw new RuntimeException('Fake messaging provider is unavailable.');
        }

        $this->sent[] = $message;

        if (app()->environment(['local', 'preview'])) {
            // Logs never contain full phone numbers, codes or tokens (security baseline §6).
            Log::info('[fake messaging] '.$message->channel->value.' '.$message->template.' to '.PhoneNumbers::maskForLogs($message->phoneE164), [
                'parameters' => array_keys($message->parameters),
            ]);
        }

        return new MessageReceipt('fake_msg_'.count($this->sent), $message->channel);
    }

    /** @return list<OutgoingMessage> */
    public function sent(): array
    {
        return $this->sent;
    }

    /** @param  (Closure(OutgoingMessage): bool)|null  $matches */
    public function assertSent(string $template, ?Closure $matches = null, int $times = 1): void
    {
        $count = count(array_filter(
            $this->sent,
            fn (OutgoingMessage $message): bool => $message->template === $template && (! $matches instanceof Closure || $matches($message)),
        ));

        Assert::assertSame($times, $count, "Expected [{$template}] to be sent {$times} time(s), sent {$count}.");
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sent, 'Unexpected messages were sent.');
    }
}
