<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AiOutcome: string
{
    case Ok = 'ok';
    case Invalid = 'invalid';
    case Timeout = 'timeout';
    case Error = 'error';
    case Throttled = 'throttled';

    public function label(): string
    {
        return match ($this) {
            self::Ok => __('Used'),
            self::Invalid => __('Discarded (invalid)'),
            self::Timeout => __('Timed out'),
            self::Error => __('Provider error'),
            self::Throttled => __('Throttled or over budget'),
        };
    }
}
