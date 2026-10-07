<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\ProChangeRequest;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * An approved pro asks to add a trade, or to add or renew a registration badge (spec 021, AC27).
 * Nothing changes on their profile yet: the request waits for an admin, and the approved state
 * they have now stays in force until then. Registrations are optional badges that never block
 * matching (spec 020), so a trade can be requested with or without one.
 */
final readonly class RequestProChange
{
    public function __construct(private StoreProDocument $documents) {}

    public function handle(User $user, Pro $pro, ?Trade $trade, ?DocumentType $registration, ?string $number, ?UploadedFile $file): ProChangeRequest
    {
        abort_unless($pro->user_id === $user->id && $pro->status === ProStatus::Approved, 403);

        if (! $trade instanceof Trade && ! $registration instanceof DocumentType) {
            throw ValidationException::withMessages(['trade' => __('Choose a trade to add, or a registration to send.')]);
        }

        $this->checkTrade($pro, $trade);
        $this->checkRegistration($pro, $trade, $registration);
        $bytes = null;

        if ($registration instanceof DocumentType) {
            $number = $this->validNumber($number);

            if (! $file instanceof UploadedFile) {
                throw ValidationException::withMessages(['upload' => __('Add a photo or PDF of the registration.')]);
            }

            $limitKey = 'pro-uploads:'.$user->id;

            if (RateLimiter::tooManyAttempts($limitKey, (int) config('sortd.pros.uploads_per_hour'))) {
                throw ValidationException::withMessages(['upload' => __('You have uploaded a lot of files. Please try again later.')]);
            }

            RateLimiter::hit($limitKey, 3600);

            if ($file->getSize() === false || $file->getSize() > StoreProDocument::MAX_KILOBYTES * 1024) {
                throw ValidationException::withMessages(['upload' => __('Each file must be 10 MB or smaller.')]);
            }

            $bytes = $this->documents->process($registration, $file);
        }

        return DB::transaction(function () use ($user, $pro, $trade, $registration, $number, $bytes): ProChangeRequest {
            $locked = Pro::query()->with('trades')->lockForUpdate()->findOrFail($pro->id);
            abort_unless($locked->status === ProStatus::Approved, 403);

            $this->guardAgainstDuplicates($locked, $trade, $registration);

            $request = new ProChangeRequest;
            $request->forceFill([
                'pro_id' => $locked->id,
                'trade_id' => $trade?->id,
                'document_type' => $registration,
                'registration_number' => $registration instanceof DocumentType ? $number : null,
                'status' => ProChangeStatus::Pending,
            ])->save();

            if ($bytes !== null) {
                [$content, $extension, $mime] = $bytes;
                $request->addMediaFromString($content)
                    ->usingFileName('document.'.$extension)
                    ->usingName($registration->label())
                    ->withCustomProperties(['mime' => $mime])
                    ->toMediaCollection(ProChangeRequest::FILE_COLLECTION, 'media');
            }

            $locked->forceFill(['last_activity_at' => now()])->save();
            activity()->performedOn($locked)->causedBy($user)->withProperties([
                'trade' => $trade?->key,
                'registration' => $registration?->value,
            ])->log('pro change requested');

            return $request->refresh();
        });
    }

    private function checkTrade(Pro $pro, ?Trade $trade): void
    {
        if (! $trade instanceof Trade) {
            return;
        }

        if (! $trade->is_active) {
            throw ValidationException::withMessages(['trade' => __('That trade is not offered right now.')]);
        }

        if ($pro->trades()->whereKey($trade->id)->exists()) {
            throw ValidationException::withMessages(['trade' => __('You already offer this trade.')]);
        }
    }

    /**
     * A registration can be renewed if the pro already gave us one, or added if one of their trades
     * (including the one they are adding) has that registration to verify.
     */
    private function checkRegistration(Pro $pro, ?Trade $trade, ?DocumentType $registration): void
    {
        if (! $registration instanceof DocumentType) {
            return;
        }

        if (! $registration->isRegistration()) {
            throw ValidationException::withMessages(['registration' => __('Choose a registration, such as PIRB or electrical.')]);
        }

        $offered = $pro->offeredRegistrations();

        if ($trade?->registration !== null) {
            $offered[] = DocumentType::forRegistration($trade->registration);
        }

        $holds = $pro->documents()->where('type', $registration)->exists();

        if (! $holds && ! in_array($registration, $offered, true)) {
            throw ValidationException::withMessages(['registration' => __('You can send a registration for one of your trades, or renew one you already gave us.')]);
        }
    }

    private function guardAgainstDuplicates(Pro $pro, ?Trade $trade, ?DocumentType $registration): void
    {
        $pending = $pro->changeRequests()->where('status', ProChangeStatus::Pending);

        if ($trade instanceof Trade && (clone $pending)->where('trade_id', $trade->id)->exists()) {
            throw ValidationException::withMessages(['trade' => __('You already asked for this trade. It is waiting for review.')]);
        }

        if ($registration instanceof DocumentType && (clone $pending)->where('document_type', $registration)->exists()) {
            throw ValidationException::withMessages(['registration' => __('You already sent this registration. It is waiting for review.')]);
        }
    }

    private function validNumber(?string $number): string
    {
        $number = mb_strtoupper(trim((string) $number));

        if ($number === '' || mb_strlen($number) > 40 || preg_match('/^[A-Z0-9\/\-]+$/', $number) !== 1) {
            throw ValidationException::withMessages(['number' => __('Enter the registration number as it appears on your certificate.')]);
        }

        return $number;
    }
}
