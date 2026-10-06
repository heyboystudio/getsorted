<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\ProDocument;
use App\Models\User;
use App\Support\Images\HeicUnsupported;
use App\Support\Images\ImageReencoder;
use App\Support\Images\UnreadableImage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Adds or replaces one of a pro's vetting documents (spec 008, AC2). Images are
 * re-encoded without metadata; PDFs are checked by content and kept as they
 * are. Every file lands on the private `media` disk.
 */
final readonly class StoreProDocument
{
    public const int MAX_KILOBYTES = 10_240;

    public function __construct(private ImageReencoder $images) {}

    public function handle(User $user, Pro $pro, DocumentType $type, UploadedFile $upload): ProDocument
    {
        Gate::forUser($user)->authorize('update', $pro);

        $limitKey = 'pro-uploads:'.$user->id;

        if (RateLimiter::tooManyAttempts($limitKey, (int) config('sortd.pros.uploads_per_hour'))) {
            throw ValidationException::withMessages(['upload' => __('You have uploaded a lot of files. Please try again later.')]);
        }

        RateLimiter::hit($limitKey, 3600);

        if ($upload->getSize() === false || $upload->getSize() > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages(['upload' => __('Each file must be 10 MB or smaller.')]);
        }

        [$bytes, $extension, $mime] = $this->process($type, $upload);

        return DB::transaction(function () use ($user, $pro, $type, $bytes, $extension, $mime): ProDocument {
            $locked = Pro::query()->with('trades')->lockForUpdate()->findOrFail($pro->id);
            Gate::forUser($user)->authorize('update', $locked);

            if ($type->isRegistration() && ! in_array($type, $locked->offeredRegistrations(), true)) {
                throw ValidationException::withMessages(['upload' => __('None of your chosen trades has this registration.')]);
            }

            $document = $locked->documents()->firstOrNew(['type' => $type]);

            // After changes are requested, only flagged documents reopen (AC6). The flag
            // message stays until resubmission so the pro can fix a wrong upload again.
            if ($locked->status === ProStatus::ChangesRequested && $document->flag_message === null) {
                throw new AuthorizationException;
            }

            $document->forceFill(['status' => DocumentStatus::Pending, 'verified_at' => null, 'verified_by' => null, 'expires_at' => null]);

            if ($locked->status !== ProStatus::ChangesRequested) {
                $document->flag_message = null;
            }

            $document->save();
            $document->addMediaFromString($bytes)
                ->usingFileName('document.'.$extension)
                ->usingName($type->label())
                ->withCustomProperties(['mime' => $mime])
                ->toMediaCollection(ProDocument::FILE_COLLECTION, 'media');

            $locked->forceFill(['last_activity_at' => now()])->save();

            return $document->refresh();
        });
    }

    /** @return array{string, string, string} bytes, extension, MIME type */
    private function process(DocumentType $type, UploadedFile $upload): array
    {
        $path = $upload->getRealPath();

        if ($path === false || $path === '') {
            throw $this->invalid($type);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($mime === 'application/pdf' && $type->acceptsPdf()) {
            $bytes = (string) file_get_contents($path);

            // Vetting admins open these locally: refuse PDFs that can run scripts or carry other files.
            if (! str_starts_with($bytes, '%PDF-') || preg_match('#/(JavaScript|JS|Launch|EmbeddedFile)\b#', $bytes) === 1) {
                throw $this->invalid($type);
            }

            return [$bytes, 'pdf', 'application/pdf'];
        }

        try {
            return [$this->images->toWebp($path), 'webp', 'image/webp'];
        } catch (HeicUnsupported) {
            throw ValidationException::withMessages(['upload' => __('HEIC photos cannot be processed on this server yet. Please choose a JPEG, PNG or WebP photo.')]);
        } catch (UnreadableImage) {
            throw $this->invalid($type);
        }
    }

    private function invalid(DocumentType $type): ValidationException
    {
        return ValidationException::withMessages(['upload' => $type->acceptsPdf()
            ? __('Choose a photo (JPEG, PNG, WebP or HEIC) or a PDF.')
            : __('Choose a JPEG, PNG, WebP or HEIC photo.')]);
    }
}
