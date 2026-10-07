<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProChangeRequests\Pages;

use App\Filament\Admin\Resources\ProChangeRequests\ProChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListProChangeRequests extends ListRecords
{
    protected static string $resource = ProChangeRequestResource::class;
}
