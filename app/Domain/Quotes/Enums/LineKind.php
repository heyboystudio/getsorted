<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Enums;

enum LineKind: string
{
    case Labour = 'labour';
    case Materials = 'materials';
    case Callout = 'callout';

    public function label(): string
    {
        return match ($this) {
            self::Labour => __('Labour'),
            self::Materials => __('Materials'),
            self::Callout => __('Call-out'),
        };
    }

    /** Commission applies to labour and call-out, not materials (Q1/Q2 defaults, spec 010 decision 3). */
    public function earnsCommission(): bool
    {
        return $this !== self::Materials;
    }
}
