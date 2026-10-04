<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Enums;

enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case YesNo = 'yes_no';
    case Number = 'number';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Pick one',
            self::MultiChoice => 'Pick several',
            self::YesNo => 'Yes / no',
            self::Number => 'Number',
            self::Text => 'Free text',
        };
    }

    /** Choice questions need at least two options; other types have none. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultiChoice], true);
    }

    /**
     * Answers that may mark a job urgent.
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    public function urgentCandidates(array $options): array
    {
        return match ($this) {
            self::SingleChoice, self::MultiChoice => $options,
            self::YesNo => ['yes', 'no'],
            self::Number, self::Text => [],
        };
    }
}
