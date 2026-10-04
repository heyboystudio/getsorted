<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Pro landing page: start, continue or check the application (spec 011, AC6; spec 008). */
#[Layout('components.layouts.app')]
#[Title('Welcome, pro')]
final class Welcome extends Component
{
    public function mount(): void
    {
        if (! $this->user()->hasRole(Role::Pro->value)) {
            $this->redirectRoute('pros.join');
        }
    }

    public function render(): View
    {
        $user = $this->user();
        $pro = Pro::query()->where('user_id', $user->id)->first();

        return view('livewire.pros.welcome', [
            'firstName' => $user->first_name,
            'isCustomer' => $user->hasRole(Role::Customer->value),
            'status' => $pro?->status,
            'canReapply' => $pro?->status === ProStatus::Rejected && $pro->reapply_after?->isFuture() !== true,
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
