<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ProPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ProPanelProvider::class,
];
