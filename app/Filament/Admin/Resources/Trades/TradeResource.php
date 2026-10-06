<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades;

use App\Filament\Admin\Resources\Trades\Pages\CreateTrade;
use App\Filament\Admin\Resources\Trades\Pages\EditTrade;
use App\Filament\Admin\Resources\Trades\Pages\ListTrades;
use App\Filament\Admin\Resources\Trades\Pages\ViewTrade;
use App\Filament\Admin\Resources\Trades\Schemas\TradeForm;
use App\Filament\Admin\Resources\Trades\Tables\TradesTable;
use App\Models\Trade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class TradeResource extends Resource
{
    protected static ?string $model = Trade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return __('Catalogue');
    }

    public static function form(Schema $schema): Schema
    {
        return TradeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TradesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrades::route('/'),
            'create' => CreateTrade::route('/create'),
            'view' => ViewTrade::route('/{record}'),
            'edit' => EditTrade::route('/{record}/edit'),
        ];
    }
}
