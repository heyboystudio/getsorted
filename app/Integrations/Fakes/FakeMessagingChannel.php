<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\MessageReceipt;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use Closure;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Assert;

/**
 * Records messages instead of sending them. In local development it also writes
 * them to the log so OTP codes can be read without a WhatsApp/SMS provider.
 */
final class FakeMessagingChannel implements MessagingChannel
{
    /** @var list<OutgoingMessage> */
    private array $sent = [];

    public function send(OutgoingMessage $message): MessageReceipt
    {
        $this->sent[] = $message;

        if (app()->environment('local')) {
            Log::info('[fake messaging] '.$message->channel->value.' '.$message->template.' to '.$message->phoneE164, $message->parameters);
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
