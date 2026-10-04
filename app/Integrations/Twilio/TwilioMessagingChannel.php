<?php

declare(strict_types=1);

namespace App\Integrations\Twilio;

use App\Contracts\Data\MessageChannel;
use App\Contracts\Data\MessageReceipt;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp and SMS through Twilio's Messages API (Q5, decision 040). Uses the
 * HTTP client directly, so no SDK dependency. Failures throw, so callers can
 * report them (codes) or retry them (queued notifications).
 */
final readonly class TwilioMessagingChannel implements MessagingChannel
{
    public function __construct(
        private string $accountSid,
        private string $authToken,
        private string $smsFrom,
        private string $whatsAppFrom,
    ) {}

    public function send(OutgoingMessage $message): MessageReceipt
    {
        $whatsApp = $message->channel === MessageChannel::WhatsApp;

        $response = Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'To' => ($whatsApp ? 'whatsapp:' : '').$message->phoneE164,
                'From' => $whatsApp ? 'whatsapp:'.$this->whatsAppFrom : $this->smsFrom,
                'Body' => MessageTexts::for($message),
            ]);

        if (! $response->successful() || ! is_string($response->json('sid'))) {
            // Twilio's error body can echo the recipient number, so only the status and code are kept.
            throw new RuntimeException('Twilio rejected the message (HTTP '.$response->status().', code '.(string) $response->json('code').').');
        }

        return new MessageReceipt($response->json('sid'), $message->channel);
    }
}
