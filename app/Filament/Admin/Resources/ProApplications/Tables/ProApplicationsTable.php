<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications\Tables;

use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Applications, oldest submitted first; no documents or contact details in the list (spec 008, AC7). */
final class ProApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('services.trade')->withCount('serviceAreas'))
            ->emptyStateHeading(__('No applications waiting'))
            ->columns([
                TextColumn::make('business_name')->label(__('Business'))->placeholder(__('Not given yet'))->searchable(),
                TextColumn::make('trades')->label(__('Trades'))
                    ->state(fn (Pro $record): string => $record->services->pluck('trade.name')->unique()->sort()->implode(', ')),
                TextColumn::make('service_areas_count')->label(__('Suburbs')),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (ProStatus $state): string => $state->label())
                    ->color(fn (ProStatus $state): string => match ($state) {
                        ProStatus::Approved => 'success',
                        ProStatus::Submitted => 'warning',
                        ProStatus::Rejected, ProStatus::Suspended => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('submitted_at')->label(__('Submitted'))->dateTime('j M Y')->placeholder('—')->sortable(),
                TextColumn::make('waiting')->label(__('Days waiting'))
                    ->state(fn (Pro $record): ?int => $record->status === ProStatus::Submitted && $record->submitted_at !== null ? (int) $record->submitted_at->diffInDays(now()) : null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(ProStatus::cases())->mapWithKeys(fn (ProStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(ProStatus::Submitted->value),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
