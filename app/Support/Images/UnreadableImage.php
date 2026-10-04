<?php

declare(strict_types=1);

namespace App\Support\Images;

use RuntimeException;

/** The upload is not an image we can decode safely. */
final class UnreadableImage extends RuntimeException {}
