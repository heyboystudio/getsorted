<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\DataRequests\Tables;

use App\Domain\Accounts\Actions\CompleteDataRequest;
use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Domain\Accounts\Enums\DataRequestType;
use App\Models\DataRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Oldest open request first; the customer is named so support can contact them. */
final class DataRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->emptyStateHeading(__('No data requests'))
            ->columns([
                TextColumn::make('user.email')->label(__('Customer'))->searchable(),
                TextColumn::make('type')->label(__('Request'))->badge()
                    ->formatStateUsing(fn (DataRequestType $state): string => $state->label())
                    ->color(fn (DataRequestType $state): string => $state === DataRequestType::Deletion ? 'danger' : 'info'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (DataRequestStatus $state): string => $state->label())
                    ->color(fn (DataRequestStatus $state): string => $state === DataRequestStatus::Open ? 'warning' : 'success'),
                TextColumn::make('created_at')->label(__('Asked'))->dateTime('j M Y')->sortable(),
                TextColumn::make('handler.email')->label(__('Handled by'))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(DataRequestStatus::cases())->mapWithKeys(fn (DataRequestStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(DataRequestStatus::Open->value),
            ])
            ->recordActions([
                Action::make('complete')->label(__('Mark done'))->icon('heroicon-o-check')->requiresConfirmation()
                    ->modalDescription(__('Only mark this done once the copy has been sent or the account has been handled.'))
                    ->visible(fn (DataRequest $record): bool => $record->status === DataRequestStatus::Open)
                    ->action(function (DataRequest $record): void {
                        /** @var User $admin */
                        $admin = auth()->user();
                        app(CompleteDataRequest::class)->handle($admin, $record);
                    }),
            ]);
    }
}
