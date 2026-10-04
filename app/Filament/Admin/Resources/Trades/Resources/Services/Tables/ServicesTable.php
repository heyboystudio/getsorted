<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Trades\Resources\Services\Tables;

use App\Domain\Catalogue\Enums\RegistrationType;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('key')->label(__('Key'))->fontFamily('mono')->color('gray'),
                TextColumn::make('requires_registration')->label(__('Registration'))
                    ->formatStateUsing(fn (?RegistrationType $state): string => $state?->label() ?? '—'),
                IconColumn::make('emergency_capable')->label(__('Urgent'))->boolean(),
                TextColumn::make('questions_count')->label(__('Questions'))->counts('questions'),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
