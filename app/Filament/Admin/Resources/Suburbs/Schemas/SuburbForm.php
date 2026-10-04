<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Schemas;

use App\Domain\Properties\Enums\Region;
use App\Filament\Admin\Support\CatalogueFields;
use App\Models\Suburb;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class SuburbForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(100),
                CatalogueFields::key(fn () => Suburb::query(), 'slug'),
                Select::make('region')
                    ->label(__('Region'))
                    ->options(collect(Region::cases())->mapWithKeys(fn (Region $region): array => [$region->value => $region->label()])->all())
                    ->required(),
                TextInput::make('latitude')->label(__('Centre latitude'))->numeric()->required()->minValue(-30.5)->maxValue(-29.3)
                    ->helperText(__('eThekwini is around -29.85. Approximate is fine.')),
                TextInput::make('longitude')->label(__('Centre longitude'))->numeric()->required()->minValue(30.5)->maxValue(31.3)
                    ->helperText(__('eThekwini is around 31.0.')),
                Toggle::make('is_active')->label(__('Active'))
                    ->helperText(__('Only active suburbs take bookings. Switch off instead of deleting.')),
            ]);
    }
}
