<?php

declare(strict_types=1);

namespace App\Livewire\Account\Jobs;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** A customer's own job, read-only (spec 005, AC11, AC13). */
#[Layout('components.layouts.app')]
final class Show extends Component
{
    #[Locked]
    public string $publicId;

    public function mount(ServiceJob $job): void
    {
        /** @var User $user */
        $user = auth()->user();

        // 404, not 403, so other customers' jobs are not revealed.
        abort_unless($user->can('view', $job) && $job->customer_id === $user->id, 404);

        $this->publicId = $job->public_id;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $job = ServiceJob::query()->where('public_id', $this->publicId)->where('customer_id', $user->id)
            ->with(['service.trade', 'service.questions', 'property.suburb'])->firstOrFail();

        return view('livewire.account.jobs.show', [
            'job' => $job,
            'justPosted' => session('job_posted') === true,
        ])->title($job->service->name);
    }
}
