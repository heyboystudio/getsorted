<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Customer account home. Placeholder until jobs exist (Phase 2). */
#[Layout('components.layouts.app')]
#[Title('Your account')]
final class Home extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.account.home', [
            'firstName' => $user->first_name,
            'jobs' => ServiceJob::query()->where('customer_id', $user->id)
                ->whereNot('status', ServiceJobStatus::Cancelled)
                ->with(['service', 'property.suburb'])
                ->orderByRaw("case when status = 'draft' then 0 else 1 end")
                ->latest('updated_at')
                ->get(),
        ]);
    }
}
