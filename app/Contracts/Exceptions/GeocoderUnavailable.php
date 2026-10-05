<?php

declare(strict_types=1);

namespace App\Contracts\Exceptions;

use RuntimeException;

/** The address provider failed or timed out; callers fall back to manual entry. */
final class GeocoderUnavailable extends RuntimeException {}
