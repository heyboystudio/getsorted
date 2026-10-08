<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\Matching\Actions\WithdrawWaitlist;
use App\Domain\ServiceJobs\Actions\CancelServiceJob;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Support\CustomerAttention;
use App\Domain\ServiceJobs\Support\JobTimeline;
use App\Livewire\Booking\Thread;
use App\Models\ServiceJob;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Customer home: what needs you, Siya, active jobs and recent activity (spec 021). */
#[Layout('components.layouts.workspace', ['panel' => 'customer'])]
#[Title('Home')]
final class Home extends Component
{
    public bool $waitlistRemoved = false;

    public string $problem = '';

    /** "What's going on at home?": hands the text to Siya's booking thread (spec 017, AC1). */
    public function describe(): void
    {
        $text = trim($this->problem);

        if (mb_strlen($text) < 2 || mb_strlen($text) > 1000) {
            throw ValidationException::withMessages(['problem' => __('Tell us a little about the problem.')]);
        }

        session()->forget(Thread::SESSION_KEY);
        session()->put(Thread::START_KEY, $text);
        $this->redirectRoute('book', navigate: true);
    }

    public function removeWaitlistRequests(WithdrawWaitlist $withdraw): void
    {
        /** @var User $user */
        $user = auth()->user();
        $withdraw->handle($user);
        $this->waitlistRemoved = true;
    }

    /** Customers can remove a draft they no longer want (keeps them under the draft limit). */
    public function removeDraft(string $publicId, CancelServiceJob $cancel): void
    {
        /** @var User $user */
        $user = auth()->user();
        $job = ServiceJob::query()->where('public_id', $publicId)->where('customer_id', $user->id)->where('status', ServiceJobStatus::Draft)->first();

        if ($job instanceof ServiceJob && $user->can('update', $job)) {
            $cancel->handle($job, ActorType::Customer, $user->id, 'Removed by customer');
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.account.home', [
            'firstName' => $user->first_name,
            'stats' => $this->stats($user),
            'hasWaitlistRequests' => $user->phone_e164 !== null && WaitlistEntry::query()->where('phone_e164', $user->phone_e164)->exists(),
            'attention' => CustomerAttention::for($user),
            'activeJobs' => ServiceJob::query()->where('customer_id', $user->id)
                ->whereIn('status', ServiceJobStatus::underway())
                ->with(['trade', 'property', 'acceptedQuote.pro.documents.media'])
                ->latest('updated_at')
                ->limit(5)
                ->get(),
            'activity' => JobTimeline::recentFor($user, 5),
        ]);
    }

    /** @return array{active: int, open: int, booked: int, done: int} */
    private function stats(User $user): array
    {
        $counts = ServiceJob::query()->where('customer_id', $user->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $count = fn (ServiceJobStatus ...$statuses): int => (int) collect($statuses)->sum(fn (ServiceJobStatus $status): int => (int) ($counts[$status->value] ?? 0));

        return [
            'active' => $count(...ServiceJobStatus::underway()),
            'open' => $count(ServiceJobStatus::Open),
            'booked' => $count(ServiceJobStatus::Scheduled, ServiceJobStatus::InProgress),
            'done' => $count(...ServiceJobStatus::finished()),
        ];
    }
}
