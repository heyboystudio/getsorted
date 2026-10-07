<?php

declare(strict_types=1);

namespace App\Integrations\Twilio;

use App\Contracts\Data\OutgoingMessage;

/**
 * Plain-text bodies for GetSorted's message templates. Twilio's WhatsApp sandbox
 * and SMS accept free text; approved WhatsApp templates replace these once a
 * production sender exists (decision 040). Never include more than the template's parameters.
 */
final class MessageTexts
{
    public static function for(OutgoingMessage $message): string
    {
        $p = $message->parameters;
        $get = static fn (string $key): string => (string) ($p[$key] ?? '');

        return match ($message->template) {
            'otp_code' => "Your GetSorted code is {$get('code')}. It expires in 10 minutes. Don't share it with anyone.",
            'job_posted' => "GetSorted: your {$get('service')} request is posted. We're inviting local pros and will let you know when quotes arrive.",
            'job_invite' => "GetSorted: new {$get('service')} job in {$get('suburb')}. View it and quote: {$get('link')}",
            'job_expired' => "GetSorted: no quote was accepted for your {$get('service')} request in time. Post it again: {$get('link')}",
            'quote_received' => "GetSorted: {$get('pro')} sent a quote for your {$get('service')} job. Compare quotes: {$get('link')}",
            'quote_revised' => "GetSorted: {$get('pro')} updated their quote for your {$get('service')} job: {$get('link')}",
            'quote_withdrawn' => "GetSorted: {$get('pro')} withdrew their quote for your {$get('service')} job: {$get('link')}",
            'quote_accepted' => "GetSorted: your quote for the {$get('service')} job was accepted. See the details: {$get('link')}",
            'quote_not_chosen' => "GetSorted: the customer chose another pro for the {$get('service')} job. Thanks for quoting.",
            'chat_message' => "GetSorted: you have a new message about the {$get('service')} job. Read and reply: {$get('link')}",
            'pro_approved' => "GetSorted: hi {$get('first_name')}, you're approved! We'll WhatsApp you when jobs that fit come in.",
            'pro_changes_requested' => "GetSorted: hi {$get('first_name')}, please update your pro application. Sign in to see what's needed.",
            'pro_rejected' => "GetSorted: hi {$get('first_name')}, we couldn't approve your pro application. Sign in for details.",
            'pro_suspended' => "GetSorted: hi {$get('first_name')}, your pro account is paused. Sign in for details.",
            default => 'GetSorted: you have an update. Sign in to see it.',
        };
    }
}
