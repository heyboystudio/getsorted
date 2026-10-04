<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Pages;

use App\Filament\Admin\Resources\Trades\TradeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Read-only view for admins who may see but not edit the catalogue (spec 003, AC12). */
final class ViewTrade extends ViewRecord
{
    protected static string $resource = TradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
