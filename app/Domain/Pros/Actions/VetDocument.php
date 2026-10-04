<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Support\Vetting;
use App\Models\Pro;
use App\Models\ProDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Marks a document verified (with an expiry for registrations) or flags it with a message for the pro (spec 008, AC8). */
final class VetDocument
{
    public function verify(User $admin, ProDocument $document, ?CarbonImmutable $expiresAt, ?string $note = null): void
    {
        if ($document->type->hasExpiry() && ($expiresAt === null || $expiresAt->isPast())) {
            throw ValidationException::withMessages(['expires_at' => __('Enter the date this registration expires.')]);
        }

        Vetting::locked($admin, $document->pro()->firstOrFail(), [ProStatus::Submitted], function (Pro $pro) use ($admin, $document, $expiresAt, $note): void {
            $locked = $pro->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($locked->file() === null) {
                throw ValidationException::withMessages(['document' => __('There is no file to verify yet.')]);
            }

            if ($locked->type->isRegistration() && ($locked->number === null || $locked->number === '')) {
                throw ValidationException::withMessages(['document' => __('This registration has no number to check. Flag it so the pro adds one.')]);
            }

            $locked->forceFill([
                'status' => DocumentStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'expires_at' => $document->type->hasExpiry() ? $expiresAt : null,
                'flag_message' => null,
                'notes' => $note ?? $locked->notes,
            ])->save();

            Vetting::log($admin, $pro, 'pro_document_verified', ['type' => $locked->type->value]);
        });
    }

    public function flag(User $admin, ProDocument $document, string $message, ?string $note = null): void
    {
        $message = trim($message);
        Validator::make(['flag_message' => $message], ['flag_message' => ['required', 'string', 'max:500']], [
            'flag_message.required' => __('Tell the pro what to fix.'),
        ])->validate();

        Vetting::locked($admin, $document->pro()->firstOrFail(), [ProStatus::Submitted], function (Pro $pro) use ($admin, $document, $message, $note): void {
            $locked = $pro->documents()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => DocumentStatus::Flagged,
                'flag_message' => $message,
                'verified_at' => null,
                'verified_by' => null,
                'notes' => $note ?? $locked->notes,
            ])->save();

            Vetting::log($admin, $pro, 'pro_document_flagged', ['type' => $locked->type->value]);
        });
    }
}
