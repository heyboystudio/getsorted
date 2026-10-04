<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services\Schemas;

use App\Domain\Catalogue\Enums\RegistrationType;
use App\Filament\Admin\Support\CatalogueFields;
use App\Models\Service;
use App\Models\Trade;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

final class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(100),
                CatalogueFields::key(fn () => Service::query()->where('trade_id', self::tradeId($schema))),
                Textarea::make('description')->label(__('Description'))->rows(3)->maxLength(1000)->columnSpanFull(),
                Select::make('requires_registration')
                    ->label(__('Registration required'))
                    ->options(collect(RegistrationType::cases())->mapWithKeys(fn (RegistrationType $type): array => [$type->value => $type->label()])->all())
                    ->placeholder(__('None')),
                Toggle::make('emergency_capable')->label(__('Can be booked as urgent (today)')),
                Repeater::make('safety_advice')
                    ->label(__('Safety advice'))
                    ->simple(TextInput::make('line')->required()->maxLength(300))
                    ->addActionLabel(__('Add advice line'))
                    ->defaultItems(0)
                    ->reorderable()
                    ->columnSpanFull(),
                Toggle::make('is_active')->label(__('Active'))->default(true)
                    ->helperText(__('Switch off instead of deleting, so past jobs keep their history.')),
            ]);
    }

    private static function tradeId(Schema $schema): ?int
    {
        $livewire = $schema->getLivewire();
        $trade = $livewire instanceof Page ? $livewire->getParentRecord() : null;

        return $trade instanceof Trade ? $trade->id : null;
    }
}
