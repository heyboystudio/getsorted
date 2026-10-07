<?php

declare(strict_types=1);

namespace App\Livewire\Account\Settings;

use App\Contracts\Data\MessageChannel;
use App\Domain\Accounts\Support\NotificationPreferences;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Which text messages a customer gets and where (spec 021, AC15). Login codes are not optional. */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Notifications')]
final class Notifications extends Component
{
    /** @var array<string, bool> */
    public array $groups = [];

    public string $channel = 'whatsapp';

    public bool $saved = false;

    public function mount(): void
    {
        $user = $this->user();

        foreach (array_keys(NotificationPreferences::groups()) as $group) {
            $this->groups[$group] = NotificationPreferences::allows($user, $group);
        }

        $this->channel = NotificationPreferences::channel($user)->value;
    }

    public function save(): void
    {
        $this->validate([
            'channel' => ['required', 'in:'.implode(',', array_column(MessageChannel::cases(), 'value'))],
            'groups' => ['array'],
            'groups.*' => ['boolean'],
        ]);

        NotificationPreferences::save($this->user(), $this->groups, MessageChannel::from($this->channel));
        $this->saved = true;
    }

    public function updated(): void
    {
        $this->saved = false;
    }

    public function render(): View
    {
        return view('livewire.account.settings.notifications', ['labels' => NotificationPreferences::groups()]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
