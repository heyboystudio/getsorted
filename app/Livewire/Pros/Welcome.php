<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\Support\ProPipeline;
use App\Domain\Pros\Actions\SetProAvailability;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Pro;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pro landing page: start, continue or check the application (spec 011, AC6; spec 008). For an
 * approved pro it is the Today screen (spec 021, AC20–AC21).
 */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Welcome, pro')]
final class Welcome extends Component
{
    public function mount(): void
    {
        if (! $this->user()->hasRole(Role::Pro->value)) {
            $this->redirectRoute('pros.join');
        }
    }

    public function setPaused(bool $paused, SetProAvailability $setProAvailability): void
    {
        $pro = Pro::query()->where('user_id', $this->user()->id)->firstOrFail();
        $setProAvailability->handle($this->user(), $pro, $paused);
    }

    public function render(): View
    {
        $user = $this->user();
        $pro = Pro::query()->where('user_id', $user->id)->first();

        if ($pro instanceof Pro && $pro->status === ProStatus::Approved) {
            return view('livewire.pros.today', $this->today($pro->refresh()));
        }

        return view('livewire.pros.welcome', [
            'firstName' => $user->first_name,
            'status' => $pro?->status,
            'canReapply' => $pro?->status === ProStatus::Rejected && $pro->reapply_after?->isFuture() !== true,
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * What an approved pro should look at, in order (spec 021, AC20).
     *
     * @return array<string, mixed>
     */
    private function today(Pro $pro): array
    {
        $pipeline = ProPipeline::for($pro, 100);
        $weekAhead = now()->addDays(7)->toDateString();

        return [
            'firstName' => $this->user()->first_name,
            'paused' => $pro->isPaused(),
            'invites' => $pipeline['invites']->take(10),
            'invitesTotal' => $pipeline['invites']->count(),
            'quoted' => $pipeline['quoted']->take(10),
            // Full address and contact are only ever read for jobs this pro won (policy checked below).
            'booked' => $pipeline['booked']
                ->filter(fn (array $row): bool => in_array($row['job']->status, [ServiceJobStatus::Scheduled, ServiceJobStatus::InProgress, ServiceJobStatus::AwaitingDeposit], true)
                    && ($row['job']->scheduled_for === null || $row['job']->scheduled_for->toDateString() <= $weekAhead))
                ->values()
                ->map(function (array $row): array {
                    $job = $row['job']->loadMissing('customer');
                    $contact = $this->user()->can('viewContact', $job);

                    return $row + ['contact' => $contact ? [
                        'name' => $job->customer->first_name,
                        'phone' => $job->customer->phone_e164,
                        'address' => $job->property?->street_address,
                    ] : null];
                }),
        ];
    }
}
