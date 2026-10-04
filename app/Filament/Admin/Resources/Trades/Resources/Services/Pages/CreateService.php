<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services\Pages;

use App\Filament\Admin\Resources\Trades\Resources\Services\ServiceResource;
use App\Filament\Admin\Support\CatalogueAudit;
use App\Models\Service;
use Filament\Resources\Pages\CreateRecord;

final class CreateService extends CreateRecord
{
    /**
     * New items go to the end of the list.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'sort' => CatalogueAudit::nextSort(Service::class, ['trade_id' => $this->getParentRecord()?->getKey()])];
    }

    protected static string $resource = ServiceResource::class;
}
