<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Enums;

enum IntroductionKind: string
{
    /** No charge: the fee is off, or this is inside the pro's free allowance. */
    case Free = 'free';

    /** The fee was taken from the pro's prepaid credit. */
    case Credit = 'credit';
}
