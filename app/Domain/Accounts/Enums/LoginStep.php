<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum LoginStep: string
{
    case Phone = 'phone';
    case Code = 'code';
    case Profile = 'profile';
}
