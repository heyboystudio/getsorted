<?php

declare(strict_types=1);

namespace App\Support\Images;

use Throwable;

/**
 * Decodes an uploaded image and re-encodes it as WebP, which drops location
 * and other metadata and anything that is not image data (spec 012, decision 031).
 */
final class ImageReencoder
{
    /**
     * @throws UnreadableImage
     * @throws HeicUnsupported
     */
    public function toWebp(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            return $this->heicToWebp($path);
        }

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new UnreadableImage;
        }

        $dimensions = @getimagesize($path);

        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 24_000_000) {
            throw new UnreadableImage;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof \GdImage) {
            throw new UnreadableImage;
        }

        try {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            $ok = imagewebp($image, null, 85);
            $bytes = ob_get_clean();

            if (! $ok || ! is_string($bytes) || $bytes === '') {
                throw new UnreadableImage;
            }

            return $bytes;
        } finally {
            imagedestroy($image);
        }
    }

    private function heicToWebp(string $path): string
    {
        if (! class_exists(\Imagick::class) || \Imagick::queryFormats('HEIC') === []) {
            throw new HeicUnsupported;
        }

        try {
            $image = new \Imagick;
            $image->pingImage($path);

            if ($image->getImageWidth() * $image->getImageHeight() > 24_000_000) {
                throw new UnreadableImage;
            }

            $image->clear();
            $image->readImage($path.'[0]');
            $image->autoOrient();
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(85);
            $bytes = $image->getImageBlob();
            $image->clear();

            if ($bytes === '') {
                throw new UnreadableImage;
            }

            return $bytes;
        } catch (UnreadableImage $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new UnreadableImage;
        }
    }
}
