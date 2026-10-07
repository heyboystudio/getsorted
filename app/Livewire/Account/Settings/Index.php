<?php

declare(strict_types=1);

namespace App\Livewire\Account\Settings;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The Account tab: a list of focused pages (spec 021, AC13). */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Account')]
final class Index extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.account.settings.index', ['user' => $user]);
    }
}
