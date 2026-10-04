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

        if ($upload->getSize() === false || $upload->getSize() > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages(['upload' => __('Each file must be 10 MB or smaller.')]);
        }

        [$bytes, $extension, $mime] = $this->process($type, $upload);

        return DB::transaction(function () use ($user, $pro, $type, $bytes, $extension, $mime): ProDocument {
            $locked = Pro::query()->with('services')->lockForUpdate()->findOrFail($pro->id);
            Gate::forUser($user)->authorize('update', $locked);

            if ($type->isRegistration() && ! in_array($type, $locked->requiredRegistrations(), true)) {
                throw ValidationException::withMessages(['upload' => __('None of your chosen services needs this registration.')]);
            }

            $document = $locked->documents()->firstOrNew(['type' => $type]);

            // After changes are requested, only flagged documents reopen (AC6).
            if ($locked->status === ProStatus::ChangesRequested && $document->status !== DocumentStatus::Flagged) {
                throw new AuthorizationException;
            }

            $document->forceFill(['status' => DocumentStatus::Pending, 'flag_message' => null, 'verified_at' => null, 'verified_by' => null])->save();
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
        $mime = $path === false ? null : (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($path !== false && $mime === 'application/pdf' && $type->acceptsPdf()) {
            $bytes = (string) file_get_contents($path);

            if (! str_starts_with($bytes, '%PDF-')) {
                throw $this->invalid($type);
            }

            return [$bytes, 'pdf', 'application/pdf'];
        }

        try {
            return [$this->images->toWebp((string) $path), 'webp', 'image/webp'];
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
