<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ServiceJobs\RelationManagers;

use App\Domain\Accounts\Enums\Role;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A job's chats, read-only (spec 018, AC15). Opening one is recorded in the activity
 * log; support and super admins can close a chat for abuse but never edit messages.
 */
final class ConversationsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Chats');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('pro')->withCount([
                'messages',
                'messages as reported_count' => fn (Builder $messages): Builder => $messages->whereNotNull('reported_at'),
            ]))
            ->defaultSort('last_message_at', 'desc')
            ->paginated(false)
            ->emptyStateHeading(__('No chats yet'))
            ->columns([
                TextColumn::make('pro.business_name')->label(__('Pro')),
                TextColumn::make('messages_count')->label(__('Messages')),
                TextColumn::make('reported_count')->label(__('Reported'))->badge()->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('last_message_at')->label(__('Last message'))->dateTime('j M H:i'),
            ])
            ->recordActions([
                Action::make('read')->label(__('Read'))
                    ->modalHeading(fn (JobConversation $record): string => __('Chat with :pro', ['pro' => (string) $record->pro->business_name]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->modalContent(fn (JobConversation $record): View => $this->transcript($record)),
                Action::make('close')->label(__('Close chat'))->color('danger')
                    ->visible(fn (JobConversation $record): bool => $record->status === 'open' && $this->admin()->hasAnyRole([Role::AdminSuper->value, Role::AdminSupport->value]))
                    ->schema([Textarea::make('reason')->label(__('Reason'))->required()->maxLength(300)->rows(2)])
                    ->action(function (JobConversation $record, array $data): void {
                        $record->forceFill(['status' => 'closed', 'closed_reason' => $data['reason'], 'closed_at' => now()])->save();
                        activity()->causedBy($this->admin())->performedOn($record->serviceJob)
                            ->withProperties(['conversation' => $record->public_id])->log('chat_closed_by_admin');
                    }),
            ]);
    }

    private function transcript(JobConversation $conversation): View
    {
        // Every admin view of private messages is logged (spec 018, AC15; spec 016 AC15).
        activity()->causedBy($this->admin())->performedOn($conversation->serviceJob)
            ->withProperties(['conversation' => $conversation->public_id])->log('chat_viewed_by_admin');

        return view('filament.admin.job-chat', [
            'messages' => $conversation->messages()->with('media')->orderBy('id')->get(),
            'photoUrls' => fn (JobMessage $message): array => $message->getMedia(JobMessage::PHOTO_COLLECTION)->map(fn ($photo): string => $message->photoUrl($photo))->all(),
        ]);
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
