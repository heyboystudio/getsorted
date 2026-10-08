<?php

declare(strict_types=1);

use App\Livewire\Auth\VerifyPhone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/** No texts or WhatsApp messages are sent during the MVP (decision 062), so a mobile is saved without a code. */
uses(RefreshDatabase::class);

const PHONE = '+27821234567';

function customerWithoutPhone(): User
{
    return User::factory()->customer()->withoutPhone()->create();
}

it('normalises SA mobile numbers and saves them without a code', function (string $input): void {
    $user = customerWithoutPhone();
    $this->actingAs($user);

    Livewire::test(VerifyPhone::class)->set('phone', $input)->call('save')->assertHasNoErrors();

    expect($user->fresh()->phone_e164)->toBe(PHONE)->and($user->fresh()->phone_verified_at)->not->toBeNull()
        ->and(Activity::query()->where('description', 'phone number saved')->count())->toBe(1);
})->with(['082 123 4567', '+27 82 123 4567', '27821234567', '0821234567']);

it('rejects invalid and non-mobile numbers', function (string $input): void {
    $user = customerWithoutPhone();
    $this->actingAs($user);

    Livewire::test(VerifyPhone::class)->set('phone', $input)->call('save')->assertHasErrors(['phone']);

    expect($user->fresh()->phone_e164)->toBeNull();
})->with(['', 'abc', '0211234567', '12345', '+44 7700 900123']);

it('refuses a number another account holds, deleted accounts included', function (): void {
    User::factory()->customer()->create(['phone_e164' => PHONE])->delete();
    $user = customerWithoutPhone();
    $this->actingAs($user);

    Livewire::test(VerifyPhone::class)->set('phone', '082 123 4567')->call('save')->assertHasErrors(['phone']);

    expect($user->fresh()->phone_e164)->toBeNull();
});

it('sends someone whose email is not verified back to the email step', function (): void {
    $this->actingAs(User::factory()->customer()->withoutPhone()->unverified()->create());

    Livewire::test(VerifyPhone::class)->assertRedirect(route('verification.email'));
});
