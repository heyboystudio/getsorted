<?php

declare(strict_types=1);

namespace App\Livewire\Pros\Jobs;

use App\Domain\Matching\Enums\InviteStatus;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\ServiceJobInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** A pro's invites: new ones to answer, and past ones (spec 009, AC7). */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Your jobs')]
final class Index extends Component
{
    use EnsuresApprovedPro;

    #[Url]
    public string $tab = 'new';

    public function mount(): void
    {
        $this->ensureApprovedPro();
    }

    public function render(): View
    {
        $new = $this->tab !== 'past';

        $invites = ServiceJobInvite::query()
            ->where('pro_id', $this->currentPro()->id)
            ->with(['serviceJob.service', 'serviceJob.property.suburb'])
            ->when($new, fn (Builder $query) => $query->whereIn('status', InviteStatus::open())->where('expires_at', '>', now()))
            ->when(! $new, fn (Builder $query) => $query->where(fn (Builder $past) => $past->whereNotIn('status', InviteStatus::open())->orWhere('expires_at', '<=', now())))
            ->latest('invited_at')
            ->limit(50)
            ->get();

        return view('livewire.pros.jobs.index', ['invites' => $invites, 'isNew' => $new]);
    }
}
