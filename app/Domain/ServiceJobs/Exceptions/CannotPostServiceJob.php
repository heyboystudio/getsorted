<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Exceptions;

use RuntimeException;

/** A posting guard failed; the message is safe to show to the customer. */
final class CannotPostServiceJob extends RuntimeException {}
