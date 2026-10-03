<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

/** Two sign-ups raced for the same number; the second one is turned away politely. */
final class PhoneAlreadyRegistered extends RuntimeException {}
