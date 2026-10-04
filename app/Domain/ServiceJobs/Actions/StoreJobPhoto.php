<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\User;
use App\Support\Images\HeicUnsupported;
use App\Support\Images\ImageReencoder;
use App\Support\Images\UnreadableImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Validates and re-encodes an uploaded image before it reaches private storage. */
final class StoreJobPhoto
{
    public function __construct(private ImageReencoder $images) {}

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

        try {
            return $this->images->toWebp($path);
        } catch (HeicUnsupported) {
            throw ValidationException::withMessages(['photoUpload' => __('HEIC photos cannot be processed on this server yet. Please choose a JPEG, PNG or WebP photo.')]);
        } catch (UnreadableImage) {
            throw $this->invalidImage();
        }
    }

    private function invalidImage(): ValidationException
    {
        return ValidationException::withMessages(['photoUpload' => __('Choose a valid JPEG, PNG, WebP or HEIC photo.')]);
    }
}
