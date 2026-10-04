<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\Tables;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Models\Suburb;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['service.trade', 'property.suburb']))
            ->emptyStateHeading(__('No jobs yet'))
            ->columns([
                TextColumn::make('service.name')->label(__('Service'))->description(fn ($record): string => $record->service->trade->name),
                TextColumn::make('property.suburb.name')->label(__('Suburb'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (ServiceJobStatus $state): string => __(str($state->value)->replace('_', ' ')->ucfirst()->toString())),
                TextColumn::make('urgency')->label(__('Urgency'))->badge()
                    ->formatStateUsing(fn (Urgency $state): string => $state === Urgency::Urgent ? __('Urgent') : __('Normal'))
                    ->color(fn (Urgency $state): string => $state === Urgency::Urgent ? 'danger' : 'gray'),
                TextColumn::make('posted_at')->label(__('Posted'))->dateTime('j M Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(ServiceJobStatus::cases())->mapWithKeys(fn (ServiceJobStatus $s): array => [$s->value => __(str($s->value)->replace('_', ' ')->ucfirst()->toString())])->all()),
                SelectFilter::make('suburb')->label(__('Suburb'))
                    ->options(fn (): array => Suburb::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $data['value'] ? $query->whereHas('property', fn (Builder $q) => $q->where('suburb_id', $data['value'])) : $query),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
