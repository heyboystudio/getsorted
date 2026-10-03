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
 * Records messages instead of sending them. In local development it also logs
 * that a message was sent, with the phone number masked and no parameter values.
 */
final class FakeMessagingChannel implements MessagingChannel
{
    /** @var list<OutgoingMessage> */
    private array $sent = [];

    public function send(OutgoingMessage $message): MessageReceipt
    {
        $this->sent[] = $message;

        if (app()->environment('local')) {
            // Logs never contain full phone numbers, codes or tokens (security baseline §6).
            Log::info('[fake messaging] '.$message->channel->value.' '.$message->template.' to '.self::maskPhone($message->phoneE164), [
                'parameters' => array_keys($message->parameters),
            ]);
        }

        return new MessageReceipt('fake_msg_'.count($this->sent), $message->channel);
    }

    /** +27821234567 → +2782*****67 */
    public static function maskPhone(string $phoneE164): string
    {
        $length = mb_strlen($phoneE164);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return mb_substr($phoneE164, 0, 4).str_repeat('*', $length - 6).mb_substr($phoneE164, -2);
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
