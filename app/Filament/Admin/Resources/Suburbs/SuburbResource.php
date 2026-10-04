<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs;

use App\Filament\Admin\Resources\Suburbs\Pages\CreateSuburb;
use App\Filament\Admin\Resources\Suburbs\Pages\EditSuburb;
use App\Filament\Admin\Resources\Suburbs\Pages\ListSuburbs;
use App\Filament\Admin\Resources\Suburbs\Pages\ViewSuburb;
use App\Filament\Admin\Resources\Suburbs\Schemas\SuburbForm;
use App\Filament\Admin\Resources\Suburbs\Tables\SuburbsTable;
use App\Models\Suburb;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class SuburbResource extends Resource
{
    protected static ?string $model = Suburb::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string
    {
        return __('Places');
    }

    public static function form(Schema $schema): Schema
    {
        return SuburbForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuburbsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuburbs::route('/'),
            'create' => CreateSuburb::route('/create'),
            'view' => ViewSuburb::route('/{record}'),
            'edit' => EditSuburb::route('/{record}/edit'),
        ];
    }
}
