<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications\Pages;

use App\Filament\Admin\Resources\ProApplications\ProApplicationResource;
use Filament\Resources\Pages\ListRecords;

final class ListProApplications extends ListRecords
{
    protected static string $resource = ProApplicationResource::class;
}
