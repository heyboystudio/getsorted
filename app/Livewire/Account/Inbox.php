<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Everything we have told this person, newest first. Clients and pros each see only their own. */
#[Layout('components.layouts.app')]
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

    public function render(): View
    {
        return view('livewire.account.inbox', [
            'notifications' => $this->user()->notifications()->limit(50)->get(),
            'unread' => $this->user()->unreadNotifications()->count(),
            'home' => route($this->user()->homeRoute()),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
