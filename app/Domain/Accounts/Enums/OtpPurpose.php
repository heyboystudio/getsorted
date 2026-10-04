<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
}
