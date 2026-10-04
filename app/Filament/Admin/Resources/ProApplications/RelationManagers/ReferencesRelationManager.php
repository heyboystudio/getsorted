<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ProApplications\RelationManagers;

use App\Domain\Pros\Actions\CheckReference;
use App\Domain\Pros\Enums\ReferenceOutcome;
use App\Domain\Pros\Exceptions\CannotChangeApplication;
use App\Models\ProReference;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** References for vetting admins to phone, with outcome and private note (spec 008, AC8). */
final class ReferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'references';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('References');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('Name')),
                TextColumn::make('phone_e164')->label(__('Mobile'))
                    ->formatStateUsing(fn (string $state): string => '0'.substr($state, 3, 2).' '.substr($state, 5, 3).' '.substr($state, 8)),
                TextColumn::make('relationship')->label(__('Knows their work as')),
                TextColumn::make('outcome')->label(__('Outcome'))->badge()->formatStateUsing(fn (ReferenceOutcome $state): string => $state->label()),
                TextColumn::make('note')->label(__('Private note'))->placeholder('—')->wrap(),
            ])
            ->recordActions([
                Action::make('record')->label(__('Record call'))
                    ->schema([
                        Select::make('outcome')->label(__('How did it go?'))->required()
                            ->options(collect([ReferenceOutcome::Positive, ReferenceOutcome::Negative, ReferenceOutcome::NoAnswer])->mapWithKeys(fn (ReferenceOutcome $outcome): array => [$outcome->value => $outcome->label()])->all()),
                        Textarea::make('note')->label(__('Private note (optional)'))->maxLength(1000)->rows(2),
                    ])
                    ->action(function (ProReference $record, array $data): void {
                        try {
                            app(CheckReference::class)->handle($this->admin(), $record, ReferenceOutcome::from($data['outcome']), $data['note'] ?? null);
                        } catch (CannotChangeApplication $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            throw new Halt;
                        }

                        Notification::make()->success()->title(__('Saved'))->send();
                    }),
            ]);
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
