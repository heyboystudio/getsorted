<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DataRequests;

use App\Filament\Admin\Resources\DataRequests\Pages\ListDataRequests;
use App\Filament\Admin\Resources\DataRequests\Tables\DataRequestsTable;
use App\Models\DataRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Customers' requests to download or delete their data (spec 021, AC16). Marked done by hand. */
final class DataRequestResource extends Resource
{
    protected static ?string $model = DataRequest::class;

    protected static ?string $slug = 'data-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function getModelLabel(): string
    {
        return __('data request');
    }

    public static function getNavigationLabel(): string
    {
        return __('Data requests');
    }

    public static function getNavigationGroup(): string
    {
        return __('Customers');
    }

    /** @return Builder<DataRequest> */
    public static function getEloquentQuery(): Builder
    {
        return DataRequest::query()->with('user');
    }

    public static function table(Table $table): Table
    {
        return DataRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListDataRequests::route('/')];
    }
}
