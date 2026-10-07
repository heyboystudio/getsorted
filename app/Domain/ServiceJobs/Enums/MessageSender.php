<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

/** Who wrote a job chat message (spec 018). */
enum MessageSender: string
{
    case Customer = 'customer';
    case Pro = 'pro';
    /** GetSorted's own notes in the chat, such as "the customer chose another pro". */
    case System = 'system';
}
