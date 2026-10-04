<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services;

use App\Filament\Admin\Resources\Trades\Resources\Services\Pages\CreateService;
use App\Filament\Admin\Resources\Trades\Resources\Services\Pages\EditService;
use App\Filament\Admin\Resources\Trades\Resources\Services\Pages\ViewService;
use App\Filament\Admin\Resources\Trades\Resources\Services\RelationManagers\QuestionsRelationManager;
use App\Filament\Admin\Resources\Trades\Resources\Services\Schemas\ServiceForm;
use App\Filament\Admin\Resources\Trades\Resources\Services\Tables\ServicesTable;
use App\Filament\Admin\Resources\Trades\TradeResource;
use App\Models\Service;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = TradeResource::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateService::route('/create'),
            'view' => ViewService::route('/{record}'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
