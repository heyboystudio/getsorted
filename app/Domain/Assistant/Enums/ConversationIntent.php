<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum ConversationIntent: string
{
    case HomeProblem = 'home_problem';
    case Clarify = 'clarify';
    case ProductQuestion = 'product_question';
    case Unsupported = 'unsupported';
    case Emergency = 'emergency';
}
