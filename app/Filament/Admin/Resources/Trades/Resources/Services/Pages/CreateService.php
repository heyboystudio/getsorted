<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services\Pages;

use App\Filament\Admin\Resources\Trades\Resources\Services\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;
}
