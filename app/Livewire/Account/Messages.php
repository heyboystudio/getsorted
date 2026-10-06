<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Every conversation a client or pro is in, newest first, with unread counts. Each opens on its job page. */
#[Layout('components.layouts.app')]
#[Title('Messages')]
final class Messages extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.account.messages', [
            'rows' => JobChat::inboxFor($user),
            'home' => route($user->homeRoute()),
        ]);
    }
}
