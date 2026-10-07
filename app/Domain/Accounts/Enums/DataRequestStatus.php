<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum DataRequestStatus: string
{
    case Open = 'open';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Done => __('Done'),
        };
    }
}
