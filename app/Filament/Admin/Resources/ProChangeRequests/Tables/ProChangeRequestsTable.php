<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProChangeRequests\Tables;

use App\Domain\Pros\Actions\DecideProChange;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Models\ProChangeRequest;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Oldest pending request first; the file opens through a short-lived signed link (spec 021, AC27). */
final class ProChangeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->emptyStateHeading(__('No profile changes waiting'))
            ->columns([
                TextColumn::make('pro.business_name')->label(__('Business'))->searchable(),
                TextColumn::make('summary')->label(__('Change'))->state(fn (ProChangeRequest $record): string => $record->summary())->wrap(),
                TextColumn::make('registration_number')->label(__('Number'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (ProChangeStatus $state): string => $state->label())
                    ->color(fn (ProChangeStatus $state): string => match ($state) {
                        ProChangeStatus::Approved => 'success',
                        ProChangeStatus::Rejected => 'danger',
                        ProChangeStatus::Pending => 'warning',
                    }),
                TextColumn::make('created_at')->label(__('Asked'))->dateTime('j M Y')->sortable(),
                TextColumn::make('decision_reason')->label(__('Reason given'))->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))
                    ->options(collect(ProChangeStatus::cases())->mapWithKeys(fn (ProChangeStatus $status): array => [$status->value => $status->label()])->all())
                    ->default(ProChangeStatus::Pending->value),
            ])
            ->recordActions([
                // The five-minute link is made when clicked, so an old page still opens the file.
                Action::make('open')->label(__('Open file'))->icon('heroicon-o-eye')
                    ->visible(fn (ProChangeRequest $record): bool => $record->file() !== null)
                    ->action(fn (ProChangeRequest $record, $livewire) => $livewire->js('window.open('.json_encode($record->temporaryUrl()).", '_blank', 'noopener')")),
                Action::make('approve')->label(__('Approve'))->color('success')
                    ->visible(fn (ProChangeRequest $record): bool => $record->isPending())
                    ->schema(fn (ProChangeRequest $record): array => [
                        DatePicker::make('expires_at')->label(__('Registration expires on'))->required()->minDate(now()->addDay())
                            ->visible($record->document_type !== null),
                    ])
                    ->action(fn (ProChangeRequest $record, array $data) => self::attempt(fn () => app(DecideProChange::class)->approve(
                        self::admin(),
                        $record,
                        isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'], LocalTime::timezone())->endOfDay() : null,
                    ))),
                Action::make('reject')->label(__('Reject'))->color('danger')
                    ->visible(fn (ProChangeRequest $record): bool => $record->isPending())
                    ->schema([
                        Textarea::make('reason')->label(__('Why not? (the pro will see this)'))->required()->maxLength(1000)->rows(3),
                    ])
                    ->action(fn (ProChangeRequest $record, array $data) => self::attempt(fn () => app(DecideProChange::class)->reject(self::admin(), $record, $data['reason']))),
            ]);
    }

    private static function attempt(Closure $action): void
    {
        try {
            $action();
        } catch (CannotChangeApplication $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }

        Notification::make()->success()->title(__('Saved'))->send();
    }

    private static function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
