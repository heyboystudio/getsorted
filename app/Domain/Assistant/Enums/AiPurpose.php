<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AiPurpose: string
{
    case SuggestService = 'suggest_service';
    case Summarise = 'summarise';
    case Chat = 'chat';

    public function label(): string
    {
        return match ($this) {
            self::SuggestService => __('Service suggestions'),
            self::Summarise => __('Job summaries'),
            self::Chat => __('Siya chat'),
        };
    }
}
