<?php

declare(strict_types=1);

use App\Livewire\Account\Inbox;
use App\Livewire\NotificationBell;
use App\Models\Pro;
use App\Models\User;
use App\Notifications\UserNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function pushConfigured(): void
{
    config(['webpush.vapid.public_key' => 'BPublicKeyForTests', 'webpush.vapid.private_key' => 'privateKeyForTests']);
}

function notice(User $user, string $title = 'New quote'): void
{
    $user->notifyNow(new UserNotice('quote_received', $title, 'Body text', url('/app')));
}

// --- Installable site and the service worker (AC11) --------------------------------------

it('serves a valid web app manifest with the Get Sorted name and real icons (spec 022, AC11)', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Get Sorted')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/app')
        ->and(array_column($manifest['icons'], 'sizes'))->toContain('192x192', '512x512');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }

    foreach (['icons/apple-touch-icon.png', 'icons/badge-96.png'] as $file) {
        expect(file_exists(public_path($file)))->toBeTrue();
    }
});

it('links the manifest and Home Screen details from every page (spec 022, AC11)', function (): void {
    $this->get(route('login'))->assertOk()
        ->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false)->assertSee('name="theme-color"', false)->assertSee('name="csrf-token"', false);
});

it('tells the browser the public push key only when push is set up (spec 022, AC14)', function (): void {
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
    $this->get(route('login'))->assertDontSee('vapid-public-key', false);

    pushConfigured();
    $this->get(route('login'))->assertSee('name="vapid-public-key" content="BPublicKeyForTests"', false)->assertDontSee('privateKeyForTests', false);
});

it('ships a service worker that shows pushes and only opens pages on this site (spec 022, AC3, AC13)', function (): void {
    $worker = (string) file_get_contents(public_path('sw.js'));

    expect($worker)->toContain("addEventListener('push'")->toContain('showNotification')->toContain("addEventListener('notificationclick'")
        ->toContain('requested.origin === self.location.origin')->toContain('isSafari')->toContain('push-received');
});

// --- The card and the switch (AC1, AC10) -------------------------------------------------

it('offers the notifications card on the customer home and the pro Today screen (spec 022, AC1)', function (): void {
    $customer = User::factory()->customer()->create();
    $proUser = User::factory()->pro()->create();
    Pro::factory()->approved()->create(['user_id' => $proUser->id]);

    $this->actingAs($customer)->get(route('account.home'))->assertOk()
        ->assertSee("pushControl({ mode: 'card' })", false)->assertSee('Turn on notifications')->assertSee('Not now');
    $this->actingAs($proUser)->get(route('pros.welcome'))->assertOk()->assertSee("pushControl({ mode: 'card' })", false);
});

it('asks for permission only from the button and explains every blocked state (spec 022, AC1, AC2, AC11)', function (): void {
    $html = $this->actingAs(User::factory()->customer()->create())->get(route('account.home'))->getContent();

    expect($html)->toContain('x-on:click="enable()"')
        ->and($html)->not->toContain('requestPermission')
        ->and($html)->toContain('Notifications are blocked in this browser')
        ->and($html)->toContain('Add Get Sorted to your Home Screen')
        ->and($html)->toContain('x-on:click="dismiss()"');
});

it('shows the real state of this device in Account and on the pro profile (spec 022, AC10)', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer)->get(route('account.notifications'))->assertOk()
        ->assertSee('Pop-up notifications on this device')->assertSee("pushControl({ mode: 'switch' })", false)
        ->assertSee('Turn off on this device')->assertSee('Blocked in this browser')->assertSee('does not support pop-up notifications');

    $proUser = User::factory()->pro()->create();
    Pro::factory()->approved()->create(['user_id' => $proUser->id]);
    $this->actingAs($proUser)->get(route('pros.profile'))->assertOk()->assertSee('Pop-up notifications on this device');
});

it('keeps this device registered quietly on every signed-in page without showing anything (spec 022, AC8)', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get(route('jobs.index'))->assertOk()->assertSee("pushControl({ mode: 'silent' })", false);
});

it('removes this device before signing out (spec 022, AC7)', function (): void {
    $html = $this->actingAs(User::factory()->customer()->create())->get(route('account.home'))->getContent();
    $script = (string) file_get_contents(resource_path('js/push.js'));

    expect($html)->toContain('action="'.route('logout').'"');
    expect($script)->toContain('/\/logout$/')->toContain('unregister()')->toContain("call('DELETE'");
});

it('never asks the server to trust the page with push credentials beyond the public key (spec 022, security)', function (): void {
    $script = (string) file_get_contents(resource_path('js/push.js'));

    expect($script)->not->toContain('private')->not->toContain('VAPID_PRIVATE');
});

// --- The bell and the live inbox (AC12, AC13) --------------------------------------------

it('shows one bell with the unread count in the shell, not the old floating cluster (spec 022, AC12)', function (): void {
    $user = User::factory()->customer()->create();
    notice($user);
    notice($user);

    $html = $this->actingAs($user)->get(route('account.home'))->getContent();

    expect($html)->toContain('Notifications, 2 unread')
        ->and(substr_count($html, 'href="'.route('notifications').'"'))->toBe(1)
        ->and($html)->not->toContain('aria-label="Inbox"');
});

it('keeps the floating inbox shortcuts on pages outside the shell, with a live bell (spec 022, AC12)', function (): void {
    $user = User::factory()->customer()->create();
    notice($user);

    $this->actingAs($user)->get(route('book'))->assertOk()->assertSee('aria-label="Inbox"', false)->assertSee('Notifications, 1 unread');
});

it('refreshes the count by itself and the moment a push arrives (spec 022, AC12, AC13)', function (): void {
    $user = User::factory()->customer()->create();
    $this->actingAs($user);

    $bell = Livewire::test(NotificationBell::class)->assertDontSee('Notifications, 1 unread')->assertSeeHtml('wire:poll.15s.visible');

    notice($user);
    $bell->dispatch('push-received')->assertSee('Notifications, 1 unread');

    notice($user);
    $bell->call('$refresh')->assertSee('Notifications, 2 unread');
});

it('shows new notices in the inbox without a reload and keeps the shell (spec 022, AC12)', function (): void {
    $user = User::factory()->customer()->create();
    $this->actingAs($user);

    $inbox = Livewire::test(Inbox::class)->assertSee('Nothing yet')->assertSeeHtml('wire:poll.15s.visible');

    notice($user, 'Quote from Dlamini Plumbing');
    $inbox->dispatch('push-received')->assertSee('Quote from Dlamini Plumbing')->assertDontSee('Nothing yet');

    $html = $this->get(route('notifications'))->getContent();
    expect($html)->toContain('aria-label="Main"')->toContain('Quote from Dlamini Plumbing');
});

it('shows a pro their own notifications in the pro panel (spec 022, AC12, AC31)', function (): void {
    $proUser = User::factory()->pro()->create();
    Pro::factory()->approved()->create(['user_id' => $proUser->id]);
    $other = User::factory()->customer()->create();
    notice($proUser, 'New job near you');
    notice($other, 'Somebody else notice');

    $this->actingAs($proUser)->get(route('notifications'))->assertOk()->assertSee('New job near you')->assertDontSee('Somebody else notice')->assertSee(route('pros.jobs'), false);
});
