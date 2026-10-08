<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Exceptions;

use RuntimeException;

final class CannotFinishJob extends RuntimeException {}
