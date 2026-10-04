<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

/** Sign-up with an email that already has an account; the user is told to sign in instead. */
final class EmailAlreadyRegistered extends RuntimeException {}
