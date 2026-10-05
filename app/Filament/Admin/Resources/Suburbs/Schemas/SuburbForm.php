<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Suburbs\Schemas;

use App\Domain\Properties\Enums\Region;
use App\Filament\Admin\Support\CatalogueFields;
use App\Models\Suburb;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

final class SuburbForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('municipality', config('sortd.places.municipality'))),
                CatalogueFields::key(fn () => Suburb::query(), 'slug'),
                Select::make('region')
                    ->label(__('Region'))
                    ->options(collect(Region::cases())->mapWithKeys(fn (Region $region): array => [$region->value => $region->label()])->all())
                    ->required(),
                TextInput::make('latitude')->label(__('Centre latitude'))->numeric()->required()->minValue((float) config('sortd.places.latitude.min'))->maxValue((float) config('sortd.places.latitude.max'))
                    ->helperText(__('eThekwini is around -29.85. Approximate is fine.')),
                TextInput::make('longitude')->label(__('Centre longitude'))->numeric()->required()->minValue((float) config('sortd.places.longitude.min'))->maxValue((float) config('sortd.places.longitude.max'))
                    ->helperText(__('eThekwini is around 31.0.')),
                TagsInput::make('aliases')->label(__('Other names'))
                    ->helperText(__('Names Google Maps may use for this suburb, e.g. "Umhlanga Rocks". Used to match typed addresses.'))
                    ->nestedRecursiveRules(['string', 'max:100'])->default([]),
                Toggle::make('is_active')->label(__('Active'))
                    ->helperText(__('Only active suburbs take bookings. Switch off instead of deleting.')),
            ]);
    }
}
