<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum OtpPurpose: string
{
    /** Spec 001's phone sign-in; kept only so older rows still load. */
    case Login = 'login';

    /** Verifying a signed-in user's mobile number (spec 014). */
    case VerifyPhone = 'verify_phone';
}
