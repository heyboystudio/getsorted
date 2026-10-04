<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A quoting pro's profile photo, for the customer of that job only (spec 010, AC7). */
final class QuoteProPhotoController extends Controller
{
    public function __invoke(Request $request, Quote $quote): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        $user = $request->user();
        abort_unless($user instanceof User && ServiceJob::query()->whereKey($quote->service_job_id)->value('customer_id') === $user->id, 404);
        abort_unless(in_array($quote->status, [QuoteStatus::Submitted, QuoteStatus::Accepted], true), 404);

        $file = Pro::query()->with('documents.media')->findOrFail($quote->pro_id)->document(DocumentType::ProfilePhoto)?->file();
        abort_unless($file instanceof Media, 404);

        return Storage::disk('media')->response($file->getPathRelativeToRoot(), null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
