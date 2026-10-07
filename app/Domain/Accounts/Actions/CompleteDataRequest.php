<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Models\DataRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** An admin marks a data request as fulfilled (spec 021, AC16). The work itself is done by hand. */
final class CompleteDataRequest
{
    public function handle(User $admin, DataRequest $request): void
    {
        abort_unless($admin->hasAnyRole(['admin_super', 'admin_support']), 403);

        DB::transaction(function () use ($admin, $request): void {
            $locked = DataRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status === DataRequestStatus::Done) {
                return;
            }

            $locked->forceFill(['status' => DataRequestStatus::Done, 'handled_by' => $admin->id, 'handled_at' => now()])->save();
            activity()->performedOn($locked->user)->causedBy($admin)->withProperties(['type' => $locked->type->value])->log('data request completed');
        });
    }
}
