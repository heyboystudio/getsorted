<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

/**
 * This browser asks to receive pop-up notifications, or stops (spec 022, AC2, AC7, AC8). The endpoint,
 * key and token are credentials: they are validated, stored against the signed-in user only, and never
 * logged or returned. A device last used by someone else moves to whoever signs in on it.
 */
final class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $user = $this->user($request);
        $user->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['contentEncoding'] ?? 'aes128gcm');
        activity()->performedOn($user)->causedBy($user)->log('push notifications turned on');

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH]]);

        $user = $this->user($request);
        $user->deletePushSubscription($data['endpoint']);
        activity()->performedOn($user)->causedBy($user)->log('push notifications turned off');

        return response()->json(['subscribed' => false]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
