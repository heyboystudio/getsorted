<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One inbox for every chat on the customer's jobs (spec 021, AC12). It lists who and when,
 * never the message text, so a masked contact detail can't show up in a preview.
 */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Messages')]
final class Messages extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $chats = ServiceJob::query()
            ->where('customer_id', $user->id)
            ->whereHas('conversations')
            ->with('service')
            ->get()
            ->flatMap(fn (ServiceJob $job) => JobChat::customerList($job)
                ->filter(fn (array $chat): bool => $chat['conversation'] !== null)
                ->map(fn (array $chat): array => [
                    'job' => $job,
                    'label' => $chat['label'],
                    'unread' => $chat['unread'],
                    'closed' => ! $chat['writable'],
                    'last_at' => $chat['conversation']->last_message_at,
                ]))
            ->sortByDesc(fn (array $chat) => $chat['last_at']?->getTimestamp() ?? 0)
            ->values();

        return view('livewire.account.messages', ['chats' => $chats]);
    }
}
