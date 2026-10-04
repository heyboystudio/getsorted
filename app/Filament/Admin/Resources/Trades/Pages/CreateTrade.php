<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Pages;

use App\Filament\Admin\Resources\Trades\TradeResource;
use App\Filament\Admin\Support\CatalogueAudit;
use App\Models\Trade;
use Filament\Resources\Pages\CreateRecord;

final class CreateTrade extends CreateRecord
{
    /**
     * New items go to the end of the list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sort' => CatalogueAudit::nextSort(Trade::class, [])];
    }

    protected static string $resource = TradeResource::class;
}
