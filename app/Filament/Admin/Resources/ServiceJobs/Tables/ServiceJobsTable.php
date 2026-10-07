<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\Tables;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Models\Trade;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ServiceJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('trade'))
            ->emptyStateHeading(__('No jobs yet'))
            ->columns([
                TextColumn::make('trade.name')->label(__('Trade'))->description(fn ($record): string => $record->factTexts()[0] ?? ''),
                TextColumn::make('area_label')->label(__('Area'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (ServiceJobStatus $state): string => __(str($state->value)->replace('_', ' ')->ucfirst()->toString()))
                    ->color(fn (ServiceJobStatus $state): string => $state->badgeColor()),
                TextColumn::make('urgency')->label(__('Urgency'))->badge()
                    ->formatStateUsing(fn (Urgency $state): string => $state === Urgency::Urgent ? __('Urgent') : __('Normal'))
                    ->color(fn (Urgency $state): string => $state === Urgency::Urgent ? 'danger' : 'gray'),
                TextColumn::make('posted_at')->label(__('Posted'))->dateTime('j M Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(ServiceJobStatus::cases())->mapWithKeys(fn (ServiceJobStatus $s): array => [$s->value => __(str($s->value)->replace('_', ' ')->ucfirst()->toString())])->all()),
                SelectFilter::make('trade')->label(__('Trade'))
                    ->options(fn (): array => Trade::query()->orderBy('sort')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $data['value'] ? $query->where('trade_id', $data['value']) : $query),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
