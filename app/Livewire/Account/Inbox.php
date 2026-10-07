<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Everything we have told this person, newest first. Clients and pros each see only their own. */
#[Title('Notifications')]
final class Inbox extends Component
{
    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');
        $this->redirect($url !== '' && str_starts_with($url, url('/')) ? $url : route('notifications'), navigate: true);
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /** A push that arrives while this page is open shows up at once (spec 022, AC12, AC13). */
    #[On('push-received')]
    public function refreshInbox(): void
    {
        // Rendering again reads the new notices.
    }

    public function render(): View
    {
        return view('livewire.account.inbox', [
            'notifications' => $this->user()->notifications()->limit(50)->get(),
            'unread' => $this->user()->unreadNotifications()->count(),
        ])->layout('components.layouts.panel', ['panel' => $this->user()->hasRole(Role::Pro->value) ? 'pro' : 'customer']);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
