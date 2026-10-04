<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Home: pick a trade to start a booking (spec 005, AC1). */
#[Layout('components.layouts.app')]
final class Welcome extends Component
{
    public function render(): View
    {
        return view('livewire.welcome', [
            'trades' => Trade::query()->where('is_active', true)
                ->whereHas('services', fn ($query) => $query->where('is_active', true))
                ->orderBy('sort')->get(),
        ]);
    }
}
