<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/** A pre-approved template message (WhatsApp first, SMS fallback), e.g. `otp_code` or `job_posted`. */
final readonly class OutgoingMessage
{
    /**
     * @param  string  $phoneE164  e.g. +27821234567
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public string $phoneE164,
        public string $template,
        public array $parameters,
        public MessageChannel $channel = MessageChannel::WhatsApp,
    ) {}
}
