<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\RelationManagers;

use App\Domain\Matching\Enums\DeclineReason;
use App\Domain\Matching\Enums\InviteStatus;
use App\Models\ServiceJobInvite;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Who was invited to a job and what happened (spec 009, AC11). Read-only. */
final class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Invites');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['pro', 'invitedBy']))
            ->defaultSort('invited_at')
            ->paginated(false)
            ->emptyStateHeading(__('No pros invited yet'))
            ->columns([
                TextColumn::make('pro.business_name')->label(__('Pro')),
                TextColumn::make('wave')->label(__('Wave'))
                    ->formatStateUsing(fn (int $state, ServiceJobInvite $record): string => $record->invited_by === null ? (string) $state : $state.' · '.__('by :name', ['name' => $record->invitedBy?->fullName()])),
                TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (InviteStatus $state): string => $state->label()),
                TextColumn::make('invited_at')->label(__('Invited'))->dateTime('j M H:i'),
                TextColumn::make('viewed_at')->label(__('Seen'))->dateTime('j M H:i')->placeholder('—'),
                TextColumn::make('responded_at')->label(__('Answered'))->dateTime('j M H:i')->placeholder('—'),
                TextColumn::make('decline_reason')->label(__('Reason'))->placeholder('—')
                    ->formatStateUsing(fn (DeclineReason $state, ServiceJobInvite $record): string => $state->label().($record->decline_note ? ': '.$record->decline_note : '')),
            ]);
    }
}
