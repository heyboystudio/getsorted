<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Exceptions;

use RuntimeException;

/** A quote cannot be sent, changed or accepted right now; the message is safe to show. */
final class CannotQuote extends RuntimeException {}
