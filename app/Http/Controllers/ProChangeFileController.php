<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Models\ProChangeRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Signed, authorized access to the file of a change request: its pro or a vetting admin (spec 021, AC27). */
final class ProChangeFileController extends Controller
{
    public function __invoke(Request $request, ProChangeRequest $change): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('view', $change), 404);
        abort_if($user->isAdmin() && $request->session()->get(AdminLogin::SESSION_KEY) !== $user->id, 403);

        $file = $change->file();
        abort_unless($file instanceof Media, 404);
        $pdf = $file->getCustomProperty('mime') === 'application/pdf';

        return Storage::disk('media')->response($file->getPathRelativeToRoot(), $pdf ? 'document.pdf' : null, [
            'Content-Type' => $pdf ? 'application/pdf' : 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $pdf ? 'attachment' : 'inline');
    }
}
