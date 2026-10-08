<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Exceptions\InvalidWebhookSignature;
use App\Contracts\PaymentGateway;
use App\Domain\Introductions\Actions\ApplyPaymentEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/** PayFast's instant transaction notification (spec 023). Credit is added only after PayFast's four checks pass. */
final class PayFastNotificationController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, ApplyPaymentEvent $apply): Response
    {
        try {
            $event = $gateway->verifyWebhook($request->getContent(), [PaymentGateway::SOURCE_IP_HEADER => (string) $request->ip()]);
        } catch (InvalidWebhookSignature $exception) {
            Log::warning('PayFast notification rejected', ['reason' => $exception->getMessage()]);

            return response('Invalid', 400);
        }

        $apply->handle($event);

        return response('OK', 200);
    }
}
