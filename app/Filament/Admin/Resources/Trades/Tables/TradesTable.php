<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Tables;

use App\Domain\Catalogue\Enums\TradeStatus;
use App\Filament\Admin\Support\CatalogueAudit;
use App\Models\Trade;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->afterReordering(CatalogueAudit::reordered(Trade::class))
            ->emptyStateHeading(__('No trades yet'))
            ->emptyStateDescription(__('Run the catalogue seeder: php artisan db:seed --class=CatalogueSeeder'))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('key')->label(__('Key'))->fontFamily('mono')->color('gray'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (TradeStatus $state): string => $state->label())
                    ->color(fn (TradeStatus $state): string => $state === TradeStatus::Live ? 'success' : 'warning'),
                TextColumn::make('services_count')->label(__('Services'))->counts('services'),
                TextColumn::make('active_services_count')->label(__('Active services'))
                    ->counts(['services as active_services_count' => fn ($query) => $query->where('is_active', true)]),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
