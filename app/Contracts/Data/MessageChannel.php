<?php

declare(strict_types=1);

namespace App\Contracts\Data;

enum MessageChannel: string
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
}
