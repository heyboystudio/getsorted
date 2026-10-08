<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Exceptions;

use RuntimeException;

/** The pro has to top up introduction credit before sending another estimate (spec 023); the screen offers the top-up. */
final class NeedsCredit extends RuntimeException {}
