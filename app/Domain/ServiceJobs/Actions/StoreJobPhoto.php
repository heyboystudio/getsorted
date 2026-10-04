<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/** Validates and re-encodes an uploaded image before it reaches private storage. */
final class StoreJobPhoto
{
    public function handle(User $user, ServiceJob $job, UploadedFile $upload): Media
    {
        abort_unless($user->can('update', $job), 404);

        if ($upload->getSize() === false || $upload->getSize() > (int) config('sortd.job_photos.max_kilobytes') * 1024) {
            throw ValidationException::withMessages(['photoUpload' => __('Each photo must be 10 MB or smaller.')]);
        }

        $processed = $this->reencode($upload);

        $hash = hash('sha256', $processed);

        return DB::transaction(function () use ($user, $job, $processed, $hash): Media {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            abort_unless($locked->customer_id === $user->id && $locked->status === ServiceJobStatus::Draft, 404);

            $existing = $locked->getMedia(ServiceJob::PHOTO_COLLECTION)
                ->first(fn (Media $photo): bool => $photo->getCustomProperty('sha256') === $hash);

            if ($existing instanceof Media) {
                return $existing;
            }

            if ($locked->getMedia(ServiceJob::PHOTO_COLLECTION)->count() >= (int) config('sortd.job_photos.max_count')) {
                throw ValidationException::withMessages(['photoUpload' => __('You can add up to 5 photos.')]);
            }

            return $locked->addMediaFromString($processed)
                ->usingFileName('photo-'.(string) str()->uuid().'.webp')
                ->usingName(__('Job photo'))
                ->withCustomProperties(['sha256' => $hash])
                ->toMediaCollection(ServiceJob::PHOTO_COLLECTION, 'media');
        });
    }

    private function reencode(UploadedFile $upload): string
    {
        $path = $upload->getRealPath();

        if ($path === false) {
            throw $this->invalidImage();
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            return $this->reencodeHeic($path);
        }

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw $this->invalidImage();
        }

        $dimensions = @getimagesize($path);

        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 24_000_000) {
            throw $this->invalidImage();
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof \GdImage) {
            throw $this->invalidImage();
        }

        try {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            $ok = imagewebp($image, null, 85);
            $bytes = ob_get_clean();

            if (! $ok || ! is_string($bytes) || $bytes === '') {
                throw $this->invalidImage();
            }

            return $bytes;
        } finally {
            imagedestroy($image);
        }
    }

    private function reencodeHeic(string $path): string
    {
        if (! class_exists(\Imagick::class) || \Imagick::queryFormats('HEIC') === []) {
            throw ValidationException::withMessages(['photoUpload' => __('HEIC photos cannot be processed on this server yet. Please choose a JPEG, PNG or WebP photo.')]);
        }

        try {
            $image = new \Imagick;
            $image->pingImage($path);

            if ($image->getImageWidth() * $image->getImageHeight() > 24_000_000) {
                throw $this->invalidImage();
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
                throw $this->invalidImage();
            }

            return $bytes;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->invalidImage();
        }
    }

    private function invalidImage(): ValidationException
    {
        return ValidationException::withMessages(['photoUpload' => __('Choose a valid JPEG, PNG, WebP or HEIC photo.')]);
    }
}
