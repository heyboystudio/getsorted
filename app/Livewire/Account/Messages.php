<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\Accounts\Enums\Role;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Every conversation a client or pro is in, newest first, with unread counts. Each opens on its job page. */
#[Title('Messages')]
final class Messages extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        // The same inbox for both roles; it sits in whichever panel the person is acting as (spec 021).
        return view('livewire.account.messages', [
            'rows' => JobChat::inboxFor($user),
        ])->layout('components.layouts.workspace', ['panel' => $user->hasRole(Role::Pro->value) ? 'pro' : 'customer']);
    }
}
