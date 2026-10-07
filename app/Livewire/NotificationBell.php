<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The bell with the unread count (spec 022, AC12). It refreshes by itself while the tab is visible, and
 * straight away when a push arrives while the site is open.
 */
final class NotificationBell extends Component
{
    #[On('push-received')]
    public function refreshCount(): void
    {
        // Rendering again reads the new count.
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unread' => $user instanceof User ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
