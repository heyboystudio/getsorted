<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services\Pages;

use App\Filament\Admin\Resources\Trades\Resources\Services\ServiceResource;
use Filament\Resources\Pages\EditRecord;

final class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;
}
