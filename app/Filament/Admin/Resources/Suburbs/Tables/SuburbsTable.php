<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Tables;

use App\Domain\Properties\Enums\Region;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class SuburbsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated([25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('No suburbs yet'))
            ->emptyStateDescription(__('Run the suburb seeder: php artisan db:seed --class=SuburbSeeder'))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('region')->label(__('Region'))->formatStateUsing(fn (Region $state): string => $state->label())->sortable(),
                IconColumn::make('is_active')->label(__('Active'))->boolean()->sortable(),
                TextColumn::make('properties_count')->label(__('Saved properties'))->counts('properties'),
            ])
            ->filters([
                SelectFilter::make('region')->label(__('Region'))
                    ->options(collect(Region::cases())->mapWithKeys(fn (Region $region): array => [$region->value => $region->label()])->all()),
                TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
