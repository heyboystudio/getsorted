<?php

declare(strict_types=1);

namespace App\Contracts\Data;

final readonly class MessageReceipt
{
    public function __construct(
        public string $providerMessageId,
        public MessageChannel $channel,
    ) {}
}
