<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Introductions\Actions\StartCreditPurchase;
use App\Domain\Introductions\Exceptions\CannotBuyCredit;
use App\Domain\Introductions\Support\ProCredit;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\ProCreditEntry;
use App\Settings\IntroductionSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** A pro's introduction credit: balance, free allowance, buying a pack through PayFast and the ledger (spec 023, AC8–AC11). */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Introduction credit')]
final class Credit extends Component
{
    use EnsuresApprovedPro;

    public function mount(): void
    {
        $this->ensureApprovedPro();
    }

    public function buy(int $packCents, StartCreditPurchase $startCreditPurchase): void
    {
        try {
            $url = $startCreditPurchase->handle($this->currentUser(), $this->currentPro(), $packCents);
        } catch (CannotBuyCredit $exception) {
            throw ValidationException::withMessages(['pack' => $exception->getMessage()]);
        }

        $this->redirect($url);
    }

    public function render(ProCredit $credit, IntroductionSettings $settings): ?View
    {
        if (! $this->ensureApprovedPro()) {
            return null;
        }

        $pro = $this->currentPro();

        return view('livewire.pros.credit', [
            'feeEnabled' => $credit->feeEnabled(),
            'balanceCents' => $credit->balanceCents($pro),
            'freeLeft' => $credit->freeLeft($pro),
            'feeCents' => $settings->fee_cents,
            'packs' => $settings->credit_pack_cents,
            'entries' => ProCreditEntry::query()->where('pro_id', $pro->id)->orderByDesc('id')->limit(20)->get(),
        ]);
    }
}
