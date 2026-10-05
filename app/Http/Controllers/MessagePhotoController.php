<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Models\JobMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed access to a chat photo for the two people in the chat, or a signed-in admin (spec 018). */
final class MessagePhotoController extends Controller
{
    public function __invoke(Request $request, JobMessage $message, string $photo): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = $request->user();
        abort_unless($user instanceof User && $message->deleted_at === null, 404);

        $conversation = $message->conversation;

        if ($user->isAdmin()) {
            abort_unless($request->session()->get(AdminLogin::SESSION_KEY) === $user->id, 403);
        } else {
            abort_unless(JobChat::sideOf($user, $conversation->serviceJob, $conversation->pro) instanceof MessageSender, 404);
        }

        $media = $message->getMedia(JobMessage::PHOTO_COLLECTION)->firstWhere('uuid', $photo);
        abort_unless($media instanceof Media, 404);

        return Storage::disk('media')->response($media->getPathRelativeToRoot(), null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
