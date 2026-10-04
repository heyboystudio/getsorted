<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed, authorized access to a processed job photo. */
final class JobPhotoController extends Controller
{
    public function __invoke(Request $request, ServiceJob $job, string $photo): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('view', $job), 404);
        abort_if($user->isAdmin() && $request->session()->get(AdminLogin::SESSION_KEY) !== $user->id, 403);

        $media = $job->getMedia(ServiceJob::PHOTO_COLLECTION)->firstWhere('uuid', $photo);
        abort_unless($media instanceof Media, 404);

        return Storage::disk('media')->response($media->getPathRelativeToRoot(), null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
