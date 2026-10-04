<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Schemas;

use App\Domain\Catalogue\Enums\TradeStatus;
use App\Filament\Admin\Support\CatalogueFields;
use App\Models\Trade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class TradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(100),
                CatalogueFields::key(fn () => Trade::query()),
                Select::make('status')
                    ->label(__('Status'))
                    ->options(collect(TradeStatus::cases())->mapWithKeys(fn (TradeStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(TradeStatus::Demo->value)
                    ->required(),
                Toggle::make('is_active')->label(__('Active'))->default(true)
                    ->helperText(__('Switch off instead of deleting, so past jobs keep their history.')),
            ]);
    }
}
