<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

/** The mobile number is already linked to another account (spec 014, AC5). */
final class PhoneAlreadyRegistered extends RuntimeException {}
