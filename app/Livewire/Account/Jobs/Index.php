<?php

declare(strict_types=1);

namespace App\Livewire\Account\Jobs;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** The customer's jobs, grouped Active / Done / Cancelled (spec 021, AC9). */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Your jobs')]
final class Index extends Component
{
    public const array TABS = ['active', 'done', 'ended'];

    #[Url]
    public string $tab = 'active';

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $current = in_array($this->tab, self::TABS, true) ? $this->tab : 'active';

        $statuses = match ($current) {
            'done' => ServiceJobStatus::finished(),
            'ended' => ServiceJobStatus::ended(),
            default => ServiceJobStatus::inFlight(),
        };

        $jobs = ServiceJob::query()
            ->where('customer_id', $user->id)
            ->whereIn('status', $statuses)
            // Drafts the customer removed never became a job; leave them out of the history.
            ->when($current === 'ended', fn ($query) => $query->whereNotNull('posted_at'))
            ->with(['service', 'property.suburb', 'acceptedQuote.pro'])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return view('livewire.account.jobs.index', ['jobs' => $jobs, 'current' => $current]);
    }
}
