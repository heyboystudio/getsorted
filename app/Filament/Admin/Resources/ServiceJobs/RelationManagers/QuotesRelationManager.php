<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\RelationManagers;

use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\Quote;
use App\Support\Rand;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Every quote version on a job, read-only, with the pro's contact-masking flag (spec 010, AC13). */
final class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Quotes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('pro'))
            ->defaultSort('submitted_at')
            ->paginated(false)
            ->emptyStateHeading(__('No quotes yet'))
            ->columns([
                TextColumn::make('pro.business_name')->label(__('Pro'))
                    ->description(fn (Quote $record): ?string => $record->pro->isMaskingFlagged() ? __('Flagged: :count masked contact attempts', ['count' => $record->pro->contact_masking_count]) : null),
                TextColumn::make('version')->label(__('Version')),
                TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (QuoteStatus $state): string => $state->label()),
                TextColumn::make('total_cents')->label(__('Total'))->formatStateUsing(fn (int $state): string => Rand::format($state)),
                TextColumn::make('deposit_cents')->label(__('Deposit'))->formatStateUsing(fn (int $state): string => Rand::format($state)),
                TextColumn::make('earliest_start_date')->label(__('Earliest start'))->date('j M'),
                TextColumn::make('valid_until')->label(__('Valid until'))->date('j M'),
                TextColumn::make('submitted_at')->label(__('Sent'))->dateTime('j M H:i'),
            ]);
    }
}
