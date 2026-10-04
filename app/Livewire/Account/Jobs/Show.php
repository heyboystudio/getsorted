<?php

declare(strict_types=1);

namespace App\Livewire\Account\Jobs;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** A customer's own job, read-only (spec 005, AC11, AC13). */
#[Layout('components.layouts.app')]
final class Show extends Component
{
    public ServiceJob $job;

    public function mount(ServiceJob $job): void
    {
        /** @var User $user */
        $user = auth()->user();

        // 404, not 403, so other customers' jobs are not revealed.
        abort_unless($job->customer_id === $user->id, 404);

        $this->job = $job;
    }

    public function render(): View
    {
        return view('livewire.account.jobs.show', [
            'job' => $this->job->load(['service.trade', 'property.suburb']),
            'justPosted' => session('job_posted') === true,
        ])->title($this->job->service->name);
    }
}
