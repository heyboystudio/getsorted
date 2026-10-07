<?php

declare(strict_types=1);

namespace App\Livewire\Account\Settings;

use App\Domain\Accounts\Actions\OpenDataRequest;
use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Domain\Accounts\Enums\DataRequestType;
use App\Domain\Matching\Actions\WithdrawWaitlist;
use App\Models\DataRequest;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Privacy and data (spec 021, AC16): waitlist removal, and requests to download or delete
 * personal data. A request only records the ask; an admin fulfils it (POPIA, retention rules).
 */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Privacy and data')]
final class Privacy extends Component
{
    public bool $waitlistRemoved = false;

    public function request(string $type, OpenDataRequest $openDataRequest): void
    {
        $requestType = DataRequestType::tryFrom($type);
        abort_unless($requestType instanceof DataRequestType, 404);

        $key = 'data-request:'.$this->user()->id;
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429);
        RateLimiter::hit($key, 3600);

        $openDataRequest->handle($this->user(), $requestType);
    }

    public function removeWaitlistRequests(WithdrawWaitlist $withdraw): void
    {
        $withdraw->handle($this->user());
        $this->waitlistRemoved = true;
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.account.settings.privacy', [
            'open' => DataRequest::query()->where('user_id', $user->id)->where('status', DataRequestStatus::Open)->pluck('type')->map(fn (DataRequestType $type): string => $type->value)->all(),
            'hasWaitlistRequests' => $user->phone_e164 !== null && WaitlistEntry::query()->where('phone_e164', $user->phone_e164)->exists(),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
