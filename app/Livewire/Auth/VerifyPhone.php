<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\User;
use App\Support\BookingStart;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Add or change a South African mobile number. During the MVP no texts or WhatsApp
 * messages are sent, so there is no code: the number is saved as given and used for contact only.
 * `phone_verified_at` marks "number on file" and still gates booking.
 */
#[Layout('components.layouts.auth')]
#[Title('Add your mobile')]
final class VerifyPhone extends Component
{
    public string $phone = '';

    public function mount(): void
    {
        // The email comes first (spec 014, AC6).
        if ($this->user()->email_verified_at === null && ! $this->user()->isAdmin()) {
            $this->redirectRoute('verification.email');
        }
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $phoneE164 = PhoneNumbers::normaliseSaMobile($this->phone);

        if ($phoneE164 === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid South African mobile number.')]);
        }

        if ($phoneE164 === $this->user()->phone_e164) {
            throw ValidationException::withMessages(['phone' => __('This is already your number.')]);
        }

        // A number is taken once any other account (deleted ones included) holds it.
        if (User::withTrashed()->where('phone_e164', $phoneE164)->whereKeyNot($this->user()->getKey())->exists()) {
            throw ValidationException::withMessages(['phone' => $this->takenMessage()]);
        }

        try {
            $this->user()->forceFill(['phone_e164' => $phoneE164, 'phone_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone' => $this->takenMessage()]);
        }

        activity()->performedOn($this->user())->causedBy($this->user())->log('phone number saved');
        $this->redirectIntended(BookingStart::landing($this->user()));
    }

    public function render(): View
    {
        return view('livewire.auth.verify-phone', ['changing' => $this->user()->phone_e164 !== null]);
    }

    private function takenMessage(): string
    {
        return __('This number is already linked to another account. Sign in to that account or contact support.');
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
