<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Customer account home. Placeholder until jobs exist (Phase 2). */
#[Layout('components.layouts.app')]
#[Title('Your account')]
final class Home extends Component
{
    /** The customer area is for customers; pro-only accounts go to the pro area (spec 011). */
    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->hasRole(Role::Customer->value)) {
            $this->redirectRoute($user->homeRoute());
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.account.home', ['firstName' => $user->first_name]);
    }
}
