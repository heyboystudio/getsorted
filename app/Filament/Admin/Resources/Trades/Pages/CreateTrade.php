<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Pages;

use App\Filament\Admin\Resources\Trades\TradeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTrade extends CreateRecord
{
    protected static string $resource = TradeResource::class;
}
