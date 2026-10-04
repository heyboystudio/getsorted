<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Trade;
use Illuminate\Contracts\View\View;

final class ShowTradeController
{
    public function __invoke(Trade $trade): View
    {
        abort_unless($trade->is_active, 404);

        return view('pages.trades.show', [
            'trade' => $trade,
            'services' => $trade->services()->where('is_active', true)->get(),
        ]);
    }
}
