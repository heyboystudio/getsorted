<?php

declare(strict_types=1);

namespace App\Domain\Matching\Exceptions;

use RuntimeException;

/** An invite cannot be created or changed right now; the message is safe to show. */
final class CannotInvite extends RuntimeException {}
