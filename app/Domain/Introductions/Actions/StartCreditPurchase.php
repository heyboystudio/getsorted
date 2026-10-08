<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Actions;

use App\Contracts\Data\CheckoutRequest;
use App\Contracts\PaymentGateway;
use App\Domain\Introductions\Enums\CreditPurchaseStatus;
use App\Domain\Introductions\Exceptions\CannotBuyCredit;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\CreditPurchase;
use App\Models\Pro;
use App\Models\User;
use App\Settings\IntroductionSettings;
use Brick\Money\Money;

/** A pro picks a credit pack; we record it as pending and send them to PayFast to pay (spec 023, AC9–AC10). */
final readonly class StartCreditPurchase
{
    public function __construct(private IntroductionSettings $settings, private PaymentGateway $gateway) {}

    /** @return string where to send the pro to pay */
    /** @param  string|null  $returnUrl  where PayFast sends the pro afterwards; their credit page by default */
    public function handle(User $user, Pro $pro, int $packCents, ?string $returnUrl = null): string
    {
        if ($pro->user_id !== $user->id || $pro->status !== ProStatus::Approved) {
            throw new CannotBuyCredit(__('Only an approved pro can buy credit.'));
        }

        if (! $this->settings->fee_enabled) {
            throw new CannotBuyCredit(__('Credit is not needed right now: introductions are free.'));
        }

        if (! in_array($packCents, $this->settings->credit_pack_cents, true)) {
            throw new CannotBuyCredit(__('Choose one of the credit packs.'));
        }

        $purchase = new CreditPurchase;
        $purchase->forceFill(['pro_id' => $pro->id, 'amount_cents' => $packCents, 'status' => CreditPurchaseStatus::Pending])->save();

        $checkout = $this->gateway->createCheckout(new CheckoutRequest(
            Money::ofMinor($packCents, 'ZAR'),
            $purchase->public_id,
            'GetSorted introduction credit',
            $returnUrl ?? route('pros.credit'),
            'credit-purchase:'.$purchase->id,
            $user->first_name,
            $user->email,
        ));

        activity()->causedBy($user)->performedOn($pro)->withProperties(['purchase' => $purchase->public_id, 'amount_cents' => $packCents])->log('credit_purchase_started');

        return $checkout->redirectUrl;
    }
}
