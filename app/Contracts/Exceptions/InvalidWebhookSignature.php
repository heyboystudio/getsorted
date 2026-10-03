<?php

declare(strict_types=1);

namespace App\Contracts\Exceptions;

use RuntimeException;

/** Thrown when a webhook's signature does not verify; the request must be rejected and logged. */
final class InvalidWebhookSignature extends RuntimeException {}
