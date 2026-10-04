<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class RemoveJobPhoto
{
    public function handle(User $user, ServiceJob $job, string $uuid): void
    {
        DB::transaction(function () use ($user, $job, $uuid): void {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            abort_unless($locked->customer_id === $user->id && $locked->status === ServiceJobStatus::Draft, 404);
            $photo = $locked->getMedia(ServiceJob::PHOTO_COLLECTION)->firstWhere('uuid', $uuid);
            abort_unless($photo instanceof Media, 404);
            $photo->delete();
        });
    }
}
