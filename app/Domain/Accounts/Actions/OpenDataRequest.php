<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Domain\Accounts\Enums\DataRequestType;
use App\Models\DataRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records that a customer asked for a copy of their data or for their account to be deleted
 * (spec 021, AC16). Nothing runs by itself: an admin fulfils it. Asking twice returns the
 * request already open.
 */
final class OpenDataRequest
{
    public function handle(User $user, DataRequestType $type): DataRequest
    {
        return DB::transaction(function () use ($user, $type): DataRequest {
            // Lock the user row so two taps cannot open two requests.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $existing = DataRequest::query()->where('user_id', $user->id)->where('type', $type)->where('status', DataRequestStatus::Open)->first();

            if ($existing instanceof DataRequest) {
                return $existing;
            }

            $request = new DataRequest;
            $request->forceFill(['user_id' => $user->id, 'type' => $type, 'status' => DataRequestStatus::Open])->save();
            activity()->performedOn($user)->causedBy($user)->withProperties(['type' => $type->value])->log('data request opened');

            return $request;
        });
    }
}
