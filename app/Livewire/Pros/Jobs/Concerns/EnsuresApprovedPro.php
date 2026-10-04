<?php

declare(strict_types=1);

namespace App\Livewire\Pros\Jobs\Concerns;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\User;

/** Jobs are for approved pros; others go to joining or their application (spec 009, AC7). */
trait EnsuresApprovedPro
{
    /** @return bool true when the page should render */
    private function ensureApprovedPro(): bool
    {
        $user = $this->currentUser();

        if (! $user->hasRole(Role::Pro->value)) {
            $this->redirectRoute('pros.join');
            $this->skipRender();

            return false;
        }

        if (Pro::query()->where('user_id', $user->id)->first()?->status !== ProStatus::Approved) {
            $this->redirectRoute('pros.status');
            $this->skipRender();

            return false;
        }

        return true;
    }

    private function currentPro(): Pro
    {
        return Pro::query()->where('user_id', $this->currentUser()->id)->where('status', ProStatus::Approved)->firstOrFail();
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
