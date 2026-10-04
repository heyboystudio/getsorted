<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\Pages;

use App\Filament\Admin\Resources\ServiceJobs\ServiceJobResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewServiceJob extends ViewRecord
{
    protected static string $resource = ServiceJobResource::class;
}
