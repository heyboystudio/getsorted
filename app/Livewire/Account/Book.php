<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** "Book a pro" inside the customer area: ask Siya, or pick a service (keeps customers in the portal). */
#[Layout('components.layouts.app')]
final class Book extends Component
{
    public function render(): View
    {
        $trades = Trade::query()->where('is_active', true)->orderBy('sort')
            ->with(['services' => fn ($query) => $query->where('is_active', true)->orderBy('sort')])
            ->get()->filter(fn (Trade $trade): bool => $trade->services->isNotEmpty());

        return view('livewire.account.book', ['trades' => $trades])->title(__('Book a pro'));
    }
}
