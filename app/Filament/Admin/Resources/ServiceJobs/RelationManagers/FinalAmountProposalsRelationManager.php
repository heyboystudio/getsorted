<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\RelationManagers;

use App\Domain\Quotes\Enums\ProposalStatus;
use App\Support\Rand;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Every final-amount proposal on a job and the customer's answer, read-only (spec 018, AC15). */
final class FinalAmountProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'finalAmountProposals';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Price changes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->paginated(false)
            ->emptyStateHeading(__('No price changes'))
            ->columns([
                TextColumn::make('version')->label(__('#')),
                TextColumn::make('previous_total_cents')->label(__('From'))->formatStateUsing(fn (int $state): string => Rand::format($state)),
                TextColumn::make('total_cents')->label(__('To'))->formatStateUsing(fn (int $state): string => Rand::format($state)),
                TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (ProposalStatus $state): string => $state->label()),
                TextColumn::make('reason')->label(__('Reason'))->wrap()->limit(120),
                TextColumn::make('customer_note')->label(__('Customer note'))->wrap()->limit(120),
                TextColumn::make('created_at')->label(__('Proposed'))->dateTime('j M H:i'),
                TextColumn::make('decided_at')->label(__('Answered'))->dateTime('j M H:i'),
            ]);
    }
}
