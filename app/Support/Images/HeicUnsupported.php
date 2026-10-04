<?php

declare(strict_types=1);

namespace App\Support\Images;

use RuntimeException;

/** HEIC needs PHP Imagick with a HEIC codec, which this server lacks (decision 031). */
final class HeicUnsupported extends RuntimeException {}
