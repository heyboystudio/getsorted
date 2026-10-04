<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Pages;

use App\Filament\Admin\Resources\Trades\TradeResource;
use Filament\Resources\Pages\EditRecord;

final class EditTrade extends EditRecord
{
    protected static string $resource = TradeResource::class;
}
