<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\RelationManagers;

use App\Filament\Admin\Resources\Trades\Resources\Services\ServiceResource;
use App\Filament\Admin\Support\CatalogueAudit;
use App\Models\Service;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

final class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $relatedResource = ServiceResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->afterReordering(CatalogueAudit::reordered(Service::class, $this->getOwnerRecord()))
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
