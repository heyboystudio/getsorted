<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Reviews\Tables;

use App\Domain\Reviews\Actions\SetReviewHidden;
use App\Domain\Reviews\Exceptions\CannotReview;
use App\Models\Review;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No reviews yet'))
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime('j M Y')->sortable(),
                TextColumn::make('pro.business_name')->label(__('Pro'))->searchable(),
                TextColumn::make('rating')->label(__('Stars'))->sortable(),
                TextColumn::make('comment')->label(__('Comment'))->wrap()->limit(200)->placeholder('—'),
                TextColumn::make('reply')->label(__('Pro reply'))->wrap()->limit(200)->placeholder('—'),
                IconColumn::make('hidden_at')->label(__('Hidden'))->boolean()->state(fn (Review $record): bool => $record->hidden_at !== null)->trueColor('danger')->falseColor('gray'),
                TextColumn::make('hide_reason')->label(__('Why hidden'))->placeholder('—')->wrap(),
            ])
            ->recordActions([
                Action::make('hide')->label(__('Hide'))->color('danger')
                    ->visible(fn (Review $record): bool => $record->hidden_at === null)
                    ->schema([Textarea::make('reason')->label(__('Why? (kept for the record)'))->required()->minLength(3)->maxLength(300)->rows(2)])
                    ->action(fn (Review $record, array $data) => self::set($record, true, $data['reason'])),
                Action::make('unhide')->label(__('Show again'))
                    ->visible(fn (Review $record): bool => $record->hidden_at !== null)
                    ->action(fn (Review $record) => self::set($record, false)),
            ]);
    }

    private static function set(Review $review, bool $hidden, ?string $reason = null): void
    {
        $admin = auth()->user();

        try {
            app(SetReviewHidden::class)->handle($admin instanceof User ? $admin : abort(403), $review, $hidden, $reason);
        } catch (CannotReview $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }
}
