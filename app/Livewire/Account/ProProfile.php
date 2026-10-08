<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Domain\Introductions\Support\ProIdentity;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Pros\Support\ProPublicProfile;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A quoting pro's profile for the customer comparing quotes (spec 021, AC17–AC19). Reached
 * through the quote, so it exists only while that pro has a live quote on the customer's job.
 */
#[Layout('components.layouts.workspace', ['panel' => 'customer'])]
#[Title('Pro profile')]
final class ProProfile extends Component
{
    #[Locked]
    public string $quotePublicId;

    public function mount(Quote $quote): void
    {
        /** @var User $user */
        $user = auth()->user();

        // 404, not 403, so a pro's profile is never revealed to someone who has no quote from them.
        abort_unless($user->can('viewPro', $quote) && $quote->pro->status === ProStatus::Approved, 404);

        $this->quotePublicId = $quote->public_id;
    }

    public function render(): View
    {
        $quote = Quote::query()->where('public_id', $this->quotePublicId)->with(['pro', 'serviceJob'])->firstOrFail();
        abort_unless(auth()->user()?->can('viewPro', $quote) === true, 404);

        return view('livewire.account.pro-profile', [
            'profile' => ProPublicProfile::from($quote->pro, $quote->hasProPhoto() ? $quote->proPhotoUrl() : null, ProIdentity::displayName($quote->pro, $quote->serviceJob)),
            'job' => $quote->serviceJob,
        ]);
    }
}
