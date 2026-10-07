<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Filament\Admin\Resources\ServiceJobs\ServiceJobResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** The ten most recently touched jobs, newest first. */
final class RecentJobs extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent jobs';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ServiceJobResource::getEloquentQuery()->with('trade')->latest('updated_at'))
            ->paginated(false)
            ->defaultPaginationPageOption(10)
            ->recordUrl(fn ($record): string => ServiceJobResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading(__('No jobs yet'))
            ->columns([
                TextColumn::make('trade.name')->label(__('Trade'))->description(fn ($record): string => $record->factTexts()[0] ?? ''),
                TextColumn::make('area_label')->label(__('Area'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (ServiceJobStatus $state): string => __(str($state->value)->replace('_', ' ')->ucfirst()->toString()))
                    ->color(fn (ServiceJobStatus $state): string => $state->badgeColor()),
                TextColumn::make('updated_at')->label(__('Updated'))->since(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->limit(10));
    }
}
