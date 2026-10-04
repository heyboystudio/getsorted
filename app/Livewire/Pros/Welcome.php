<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Holding page for pros until the application wizard opens (spec 011, AC6). */
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

        return view('livewire.pros.welcome', [
            'firstName' => $user->first_name,
            'isCustomer' => $user->hasRole(Role::Customer->value),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
