<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\MessageReceipt;
use App\Contracts\Data\OutgoingMessage;

/** WhatsApp templates with SMS fallback, including OTP delivery (provider chosen in Phase 1). */
interface MessagingChannel
{
    public function send(OutgoingMessage $message): MessageReceipt;
}
