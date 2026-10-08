<?php

declare(strict_types=1);

namespace App\Integrations\PayFast;

use App\Contracts\Data\Checkout;
use App\Contracts\Data\CheckoutRequest;
use App\Contracts\Data\PaymentEvent;
use App\Contracts\Data\PaymentEventType;
use App\Contracts\Data\PayoutRequest;
use App\Contracts\Data\ProviderResult;
use App\Contracts\Data\RefundRequest;
use App\Contracts\Exceptions\InvalidWebhookSignature;
use App\Contracts\PaymentGateway;
use Brick\Money\Money;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * PayFast hosted checkout and instant transaction notifications (ITN) for pros' prepaid credit
 * (spec 023, decision 063). The pro is sent to PayFast with a signed link and PayFast tells us the result
 * by ITN. A notification is trusted only after all four PayFast checks: the signature, the sender's
 * address, the confirmation call back to PayFast, and (in the caller) the amount. Refunds and payouts
 * are not used in the MVP.
 */
final readonly class PayFastGateway implements PaymentGateway
{
    private const array HOSTS = ['www.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za', 'sandbox.payfast.co.za'];

    public function __construct(
        private string $merchantId,
        private string $merchantKey,
        private ?string $passphrase,
        private bool $sandbox,
        private string $notifyUrl,
        private bool $checkSourceIp = true,
    ) {}

    public function createCheckout(CheckoutRequest $request): Checkout
    {
        $fields = [
            'merchant_id' => $this->merchantId,
            'merchant_key' => $this->merchantKey,
            'return_url' => $request->returnUrl,
            'cancel_url' => $request->returnUrl,
            'notify_url' => $this->notifyUrl,
            'm_payment_id' => $request->reference,
            'amount' => number_format($request->amount->getMinorAmount()->toInt() / 100, 2, '.', ''),
            'item_name' => mb_substr($request->description, 0, 100),
        ];

        $fields['signature'] = $this->signature($fields);

        return new Checkout($request->reference, 'https://'.$this->host().'/eng/process?'.http_build_query($fields));
    }

    public function verifyWebhook(string $payload, array $headers): PaymentEvent
    {
        parse_str($payload, $data);
        /** @var array<string, mixed> $data */
        $fields = array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $data);

        $sent = $fields['signature'] ?? '';
        unset($fields['signature']);

        if ($sent === '' || ! hash_equals($this->signature($fields), $sent)) {
            throw new InvalidWebhookSignature('PayFast signature mismatch.');
        }

        if ($this->checkSourceIp && ! $this->isPayFastAddress($headers[self::SOURCE_IP_HEADER] ?? '')) {
            throw new InvalidWebhookSignature('PayFast notification did not come from PayFast.');
        }

        if (($fields['merchant_id'] ?? '') !== $this->merchantId) {
            throw new InvalidWebhookSignature('PayFast notification is for another merchant.');
        }

        $confirmation = Http::asForm()->timeout(10)->post('https://'.$this->host().'/eng/query/validate', $fields);

        if (trim($confirmation->body()) !== 'VALID') {
            throw new InvalidWebhookSignature('PayFast did not confirm the notification.');
        }

        $type = match ($fields['payment_status'] ?? '') {
            'COMPLETE' => PaymentEventType::PaymentSucceeded,
            'FAILED', 'CANCELLED' => PaymentEventType::PaymentFailed,
            default => PaymentEventType::PaymentPending,
        };

        return new PaymentEvent(
            $fields['pf_payment_id'] ?? '',
            $type,
            $fields['m_payment_id'] ?? '',
            Money::of($fields['amount_gross'] ?? '0', 'ZAR'),
        );
    }

    public function refund(RefundRequest $request): ProviderResult
    {
        throw new LogicException('PayFast refunds are not used in the MVP.');
    }

    public function payout(PayoutRequest $request): ProviderResult
    {
        throw new LogicException('PayFast payouts are not used in the MVP.');
    }

    /**
     * MD5 of the fields in the order given, URL-encoded, with the passphrase last (PayFast's documented method).
     *
     * @param  array<string, string>  $fields
     */
    private function signature(array $fields): string
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            if ($value !== '') {
                $parts[] = $key.'='.urlencode(trim($value));
            }
        }

        if ($this->passphrase !== null && $this->passphrase !== '') {
            $parts[] = 'passphrase='.urlencode(trim($this->passphrase));
        }

        return md5(implode('&', $parts));
    }

    private function host(): string
    {
        return $this->sandbox ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    }

    private function isPayFastAddress(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        foreach (self::HOSTS as $host) {
            if (in_array($ip, gethostbynamel($host) ?: [], true)) {
                return true;
            }
        }

        return false;
    }
}
