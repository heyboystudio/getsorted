<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Schemas;

use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Filament\Admin\Support\CatalogueFields;
use App\Models\Trade;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
                Select::make('registration')->label(__('Registration pros can verify'))
                    ->options(collect(RegistrationType::cases())->mapWithKeys(fn (RegistrationType $type): array => [$type->value => $type->label()])->all())
                    ->placeholder(__('None'))
                    ->helperText(__('Optional. A verified registration shows as a badge; it never stops a pro receiving jobs.')),
                TagsInput::make('safety_advice')->label(__('Safety advice for urgent jobs'))
                    ->helperText(__('Short, reviewed lines shown to customers. Siya never writes safety instructions itself.'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('Active'))->default(true)
                    ->helperText(__('Switch off instead of deleting, so past jobs keep their history.')),
            ]);
    }
}
