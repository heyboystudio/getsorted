<?php

declare(strict_types=1);

namespace App\Domain\Pros\Exceptions;

use RuntimeException;

/** A status change or vetting step is not allowed right now; the message is safe to show to admins and pros. */
final class CannotChangeApplication extends RuntimeException {}
