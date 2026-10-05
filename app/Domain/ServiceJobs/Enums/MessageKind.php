<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

/** What a job chat message shows (spec 018). */
enum MessageKind: string
{
    case Text = 'text';
    case Photos = 'photos';
    /** A pro sent or revised their estimate. */
    case QuoteCard = 'quote_card';
}
