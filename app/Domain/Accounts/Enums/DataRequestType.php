<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

/** What a customer can ask us to do with their personal data (POPIA, spec 021 AC16). */
enum DataRequestType: string
{
    case Download = 'download';
    case Deletion = 'deletion';

    public function label(): string
    {
        return match ($this) {
            self::Download => __('Download my data'),
            self::Deletion => __('Delete my account'),
        };
    }
}
