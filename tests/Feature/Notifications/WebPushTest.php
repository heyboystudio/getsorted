<?php

declare(strict_types=1);

use App\Domain\Notifications\Notify;
use App\Models\User;
use App\Notifications\Channels\SafeWebPushChannel;
use App\Notifications\UserNotice;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function pushEnabled(): void
{
    config(['webpush.vapid.public_key' => 'BPublicKeyForTests', 'webpush.vapid.private_key' => 'privateKeyForTests']);
}

function subscribed(User $user, string $endpoint = 'https://push.example.test/send/abc123'): User
{
    $user->updatePushSubscription($endpoint, 'p256dh-key', 'auth-token', 'aes128gcm');

    return $user;
}

function channelsFor(User $user, UserNotice $notice): array
{
    return $notice->via($user);
}

// --- The channel (AC3, AC4, AC14) --------------------------------------------------------

it('adds a pop-up to notices for a person with a subscribed device (spec 022, AC3)', function (): void {
    pushEnabled();
    $customer = subscribed(User::factory()->customer()->create());

    expect(channelsFor($customer, new UserNotice('quote_received', 'New quote', 'A pro sent a quote.', url('/app'), group: 'quotes')))
        ->toContain('database', SafeWebPushChannel::class);
});

it('sends no pop-up without a subscribed device (spec 022, AC3)', function (): void {
    pushEnabled();
    $customer = User::factory()->customer()->create();

    expect(channelsFor($customer, new UserNotice('quote_received', 'New quote', 'Body', url('/app'))))->toBe(['database']);
});

it('skips push quietly when the server has no VAPID keys (spec 022, AC14)', function (): void {
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
    $customer = subscribed(User::factory()->customer()->create());

    expect(channelsFor($customer, new UserNotice('chat_message', 'New message', 'Body', url('/app'), group: 'messages')))->toBe(['database']);

    Notification::fake();
    Notify::user($customer, 'chat_message', 'New message', 'Body', url('/app'), group: 'messages');
    Notification::assertSentTo($customer, UserNotice::class);
});

it('keeps emailing as before and adds the pop-up on top (spec 022, AC3)', function (): void {
    pushEnabled();
    $customer = subscribed(User::factory()->customer()->create());

    expect(channelsFor($customer, new UserNotice('quote_received', 'New quote', 'Body', url('/app'), email: true, group: 'quotes')))
        ->toBe(['database', 'mail', SafeWebPushChannel::class]);
});

it('honours a customer who switched a group off, for that group only (spec 022, AC4)', function (): void {
    pushEnabled();
    $customer = subscribed(User::factory()->customer()->create());
    $customer->forceFill(['notification_preferences' => ['groups' => ['quotes' => false, 'job_updates' => true, 'messages' => true], 'channel' => 'sms']])->save();

    expect(channelsFor($customer, new UserNotice('quote_received', 'T', 'B', url('/app'), group: 'quotes')))->toBe(['database']);
    expect(channelsFor($customer, new UserNotice('chat_message', 'T', 'B', url('/app'), group: 'messages')))->toContain(SafeWebPushChannel::class);
    expect(channelsFor($customer, new UserNotice('job_posted', 'T', 'B', url('/app'), group: 'job_updates')))->toContain(SafeWebPushChannel::class);
});

it('never lets a customer\'s choices silence a notice that has no group, such as a new job for a pro (spec 022, AC4)', function (): void {
    pushEnabled();
    $pro = subscribed(User::factory()->pro()->create());
    $pro->forceFill(['notification_preferences' => ['groups' => ['quotes' => false, 'job_updates' => false, 'messages' => false], 'channel' => 'sms']])->save();

    expect(channelsFor($pro, new UserNotice('job_invite', 'New job', 'Body', url('/pros/jobs'))))->toContain(SafeWebPushChannel::class);
});

it('carries only the notice text, a safe tag and the link in the pop-up (spec 022, AC6, AC9)', function (): void {
    $user = User::factory()->customer()->create();
    $notice = new UserNotice('quote_received', 'New quote for your plumbing job', 'Dlamini Plumbing sent you a quote.', url('/app/jobs/abc'), group: 'quotes');

    $message = $notice->toWebPush($user);
    $payload = $message->toArray();

    expect($message)->toBeInstanceOf(WebPushMessage::class)
        ->and($payload['title'])->toBe('New quote for your plumbing job')
        ->and($payload['body'])->toBe('Dlamini Plumbing sent you a quote.')
        ->and($payload['data'])->toBe(['url' => url('/app/jobs/abc')])
        ->and($payload['tag'])->toStartWith('quote_received-')
        ->and(array_keys($payload))->not->toContain('phone')->not->toContain('email');

    $again = (new UserNotice('quote_received', 'Another', 'Body', url('/app/jobs/abc')))->toWebPush($user)->toArray();
    $other = (new UserNotice('quote_received', 'Another', 'Body', url('/app/jobs/xyz')))->toWebPush($user)->toArray();
    expect($again['tag'])->toBe($payload['tag'])->and($other['tag'])->not->toBe($payload['tag']);
});

it('never lets a push problem stop the rest of the notice (spec 022, AC5)', function (): void {
    $inner = Mockery::mock(WebPushChannel::class);
    $inner->shouldReceive('send')->andThrow(new RuntimeException('push service down'));

    $channel = new SafeWebPushChannel($inner);

    expect(fn () => $channel->send(User::factory()->customer()->create(), new UserNotice('chat_message', 'T', 'B', url('/app'))))->not->toThrow(Throwable::class);
});

it('stores the in-app notice even when push is on for the person (spec 022, AC5)', function (): void {
    pushEnabled();
    $customer = subscribed(User::factory()->customer()->create());
    $inner = Mockery::mock(WebPushChannel::class);
    $inner->shouldReceive('send')->once();
    app()->instance(WebPushChannel::class, $inner);

    Notify::user($customer, 'quote_received', 'New quote', 'Body', url('/app'), group: 'quotes');

    expect($customer->notifications()->count())->toBe(1)->and($customer->notifications()->first()->data['title'])->toBe('New quote');
});

// --- Subscribing and unsubscribing (AC2, AC7, AC8) ---------------------------------------

function subscriptionBody(string $endpoint = 'https://push.example.test/send/abc123'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'], 'contentEncoding' => 'aes128gcm'];
}

it('registers this device for the signed-in person and logs it without the endpoint (spec 022, AC2)', function (): void {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)->postJson(route('push.subscribe'), subscriptionBody())->assertCreated()->assertExactJson(['subscribed' => true]);

    expect($user->pushSubscriptions()->count())->toBe(1);
    $entry = Activity::query()->where('description', 'push notifications turned on')->sole();
    expect(json_encode($entry->properties))->not->toContain('push.example.test');
});

it('does not duplicate a device that registers twice (spec 022, AC8)', function (): void {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)->postJson(route('push.subscribe'), subscriptionBody())->assertCreated();
    $this->actingAs($user)->postJson(route('push.subscribe'), subscriptionBody())->assertCreated();

    expect($user->pushSubscriptions()->count())->toBe(1);
});

it('keeps each of a person\'s devices separate (spec 022, AC8)', function (): void {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)->postJson(route('push.subscribe'), subscriptionBody('https://push.example.test/send/phone'))->assertCreated();
    $this->actingAs($user)->postJson(route('push.subscribe'), subscriptionBody('https://push.example.test/send/laptop'))->assertCreated();
    $this->actingAs($user)->deleteJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example.test/send/phone'])->assertOk();

    expect($user->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example.test/send/laptop']);
});

it('moves a shared device to whoever signs in on it (spec 022, AC7)', function (): void {
    $first = User::factory()->customer()->create();
    $second = User::factory()->pro()->create();

    $this->actingAs($first)->postJson(route('push.subscribe'), subscriptionBody())->assertCreated();
    $this->actingAs($second)->postJson(route('push.subscribe'), subscriptionBody())->assertCreated();

    expect($first->pushSubscriptions()->count())->toBe(0)->and($second->pushSubscriptions()->count())->toBe(1);
});

it('lets nobody remove another person\'s device (spec 022, AC7, security)', function (): void {
    $owner = subscribed(User::factory()->customer()->create());
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)->deleteJson(route('push.unsubscribe'), ['endpoint' => 'https://push.example.test/send/abc123'])->assertOk();

    expect($owner->pushSubscriptions()->count())->toBe(1);
});

it('refuses guests and bad subscriptions (spec 022, security)', function (): void {
    $this->postJson(route('push.subscribe'), subscriptionBody())->assertUnauthorized();
    $this->deleteJson(route('push.unsubscribe'), ['endpoint' => 'https://x'])->assertUnauthorized();

    $user = User::factory()->customer()->create();
    $this->actingAs($user);

    $this->postJson(route('push.subscribe'), [...subscriptionBody(), 'endpoint' => 'http://insecure.example.test/x'])->assertUnprocessable();
    $this->postJson(route('push.subscribe'), [...subscriptionBody(), 'endpoint' => 'not a url'])->assertUnprocessable();
    $this->postJson(route('push.subscribe'), ['endpoint' => 'https://push.example.test/x'])->assertUnprocessable();
    $this->postJson(route('push.subscribe'), [...subscriptionBody(), 'contentEncoding' => 'rot13'])->assertUnprocessable();
    $this->postJson(route('push.subscribe'), [...subscriptionBody(), 'endpoint' => 'https://push.example.test/'.str_repeat('a', 1100)])->assertUnprocessable();
    $this->deleteJson(route('push.unsubscribe'), [])->assertUnprocessable();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

it('rate limits registering devices (spec 022, security)', function (): void {
    $user = User::factory()->customer()->create();
    $this->actingAs($user);

    foreach (range(1, 20) as $i) {
        $this->postJson(route('push.subscribe'), subscriptionBody("https://push.example.test/send/{$i}"))->assertCreated();
    }

    $this->postJson(route('push.subscribe'), subscriptionBody('https://push.example.test/send/21'))->assertStatus(429);
});

it('removes a person\'s devices when their account is deleted for good (spec 022, security)', function (): void {
    $user = subscribed(User::factory()->customer()->create());

    $user->forceDelete();

    expect(PushSubscription::query()->count())->toBe(0);
});

// --- Checking it on a real device ----------------------------------------------------------

it('sends a test notice to one account through the normal path (spec 022)', function (): void {
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'tester@example.com']);
    $other = User::factory()->customer()->create();

    $this->artisan('getsorted:send-test-notification', ['email' => ' Tester@Example.com '])->expectsOutputToContain('no subscribed device')->assertSuccessful();

    Notification::assertSentTo($user, UserNotice::class, fn (UserNotice $notice): bool => $notice->kind === 'test' && $notice->group === null);
    Notification::assertNotSentTo($other, UserNotice::class);
});

it('says how many devices will get the pop-up and fails for an unknown account (spec 022)', function (): void {
    Notification::fake();
    subscribed(User::factory()->customer()->create(['email' => 'phone@example.com']));

    $this->artisan('getsorted:send-test-notification', ['email' => 'phone@example.com'])->expectsOutputToContain('1 subscribed device')->assertSuccessful();
    $this->artisan('getsorted:send-test-notification', ['email' => 'nobody@example.com'])->expectsOutputToContain('No account has that email')->assertFailed();
});

// --- Knowing how each push went ----------------------------------------------------------

it('logs whether the push service accepted a push, with the status and nothing private (spec 022)', function (): void {
    $user = subscribed(User::factory()->customer()->create());
    $subscription = $user->pushSubscriptions()->firstOrFail();
    $message = (new UserNotice('chat_message', 'T', 'B', url('/app')))->toWebPush($user);

    $logged = [];
    Log::shouldReceive('log')->andReturnUsing(function (string $level, string $text, array $context) use (&$logged): void {
        $logged[] = [$level, $text, $context];
    });

    $ok = new MessageSentReport(new PsrRequest('POST', 'https://push.example.test/send/secret-endpoint'), new PsrResponse(201));
    $gone = new MessageSentReport(new PsrRequest('POST', 'https://push.example.test/send/secret-endpoint'), new PsrResponse(410), false, 'Gone');

    Event::dispatch(new NotificationSent($ok, $subscription, $message));
    Event::dispatch(new NotificationFailed($gone, $subscription, $message));

    expect($logged)->toBe([
        ['info', 'Web push result.', ['accepted' => true, 'status' => 201, 'device_gone' => false]],
        ['warning', 'Web push result.', ['accepted' => false, 'status' => 410, 'device_gone' => true]],
    ]);
    expect(json_encode($logged))->not->toContain('secret-endpoint')->not->toContain('p256dh-key');
});

it('deletes a device the push service says is gone (spec 022, AC5)', function (): void {
    $user = subscribed(User::factory()->customer()->create());
    $subscription = $user->pushSubscriptions()->firstOrFail();
    $message = (new UserNotice('chat_message', 'T', 'B', url('/app')))->toWebPush($user);

    app(ReportHandler::class)->handleReport(
        new MessageSentReport(new PsrRequest('POST', $subscription->endpoint), new PsrResponse(410), false, 'Gone'),
        $subscription,
        $message,
    );

    expect(PushSubscription::query()->count())->toBe(0);
});
