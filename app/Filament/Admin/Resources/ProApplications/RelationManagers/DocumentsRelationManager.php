<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications\RelationManagers;

use App\Domain\Pros\Actions\VetDocument;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Models\ProDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Each document with a signed viewer link and verify/flag actions (spec 008, AC8). */
final class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Documents');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->paginated(false)
            ->columns([
                TextColumn::make('type')->label(__('Document'))->formatStateUsing(fn (DocumentType $state): string => $state->label()),
                TextColumn::make('number')->label(__('Number'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (DocumentStatus $state): string => $state->label())
                    ->color(fn (DocumentStatus $state): string => match ($state) {
                        DocumentStatus::Verified => 'success',
                        DocumentStatus::Flagged => 'warning',
                        DocumentStatus::Pending => 'gray',
                    }),
                TextColumn::make('expires_at')->label(__('Expires'))->date('j M Y')->placeholder('—'),
                TextColumn::make('flag_message')->label(__('Message to pro'))->placeholder('—')->wrap(),
                TextColumn::make('notes')->label(__('Private note'))->placeholder('—')->wrap(),
            ])
            ->recordActions([
                Action::make('open')->label(__('Open'))->icon('heroicon-o-eye')
                    ->visible(fn (ProDocument $record): bool => $record->file() !== null)
                    ->url(fn (ProDocument $record): string => $record->temporaryUrl(), shouldOpenInNewTab: true),
                Action::make('verify')->label(__('Verify'))->color('success')
                    ->visible(fn (ProDocument $record): bool => $record->file() !== null)
                    ->schema(fn (ProDocument $record): array => [
                        DatePicker::make('expires_at')->label(__('Expires on'))->required()->minDate(now()->addDay())
                            ->visible($record->type->hasExpiry()),
                        Textarea::make('note')->label(__('Private note (optional)'))->maxLength(1000)->rows(2),
                    ])
                    ->action(fn (ProDocument $record, array $data) => $this->attempt(fn () => app(VetDocument::class)->verify(
                        $this->admin(),
                        $record,
                        isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'])->endOfDay() : null,
                        $data['note'] ?? null,
                    ))),
                Action::make('flag')->label(__('Flag'))->color('warning')
                    ->schema([
                        Textarea::make('flag_message')->label(__('What should the pro fix? (they will see this)'))->required()->maxLength(500)->rows(2),
                        Textarea::make('note')->label(__('Private note (optional)'))->maxLength(1000)->rows(2),
                    ])
                    ->action(fn (ProDocument $record, array $data) => $this->attempt(fn () => app(VetDocument::class)->flag($this->admin(), $record, $data['flag_message'], $data['note'] ?? null))),
            ]);
    }

    private function attempt(Closure $action): void
    {
        try {
            $action();
        } catch (CannotChangeApplication $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }

        Notification::make()->success()->title(__('Saved'))->send();
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
