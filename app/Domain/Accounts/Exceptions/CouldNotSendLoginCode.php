<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

final class CouldNotSendLoginCode extends RuntimeException {}
