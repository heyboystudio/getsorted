<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Pages;

use App\Filament\Admin\Resources\Trades\TradeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTrades extends ListRecords
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
