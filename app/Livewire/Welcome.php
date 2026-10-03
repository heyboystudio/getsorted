<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class Welcome extends Component
{
    public function render(): View
    {
        return view('livewire.welcome');
    }
}
