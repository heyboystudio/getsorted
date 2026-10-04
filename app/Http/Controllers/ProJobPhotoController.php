<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A job photo for the invited pro, while their invite is open (spec 009, AC8, AC10). */
final class ProJobPhotoController extends Controller
{
    public function __invoke(Request $request, ServiceJobInvite $invite, string $photo): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('view', $invite) && $invite->isAvailable(), 404);

        $job = ServiceJob::query()->findOrFail($invite->service_job_id);
        $media = $job->getMedia(ServiceJob::PHOTO_COLLECTION)->firstWhere('uuid', $photo);
        abort_unless($media instanceof Media, 404);

        return Storage::disk('media')->response($media->getPathRelativeToRoot(), null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
