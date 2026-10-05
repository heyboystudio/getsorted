<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Contracts\Data\MessageChannel;
use App\Domain\Accounts\Actions\SendPhoneCode;
use App\Domain\Accounts\Actions\VerifyPhoneCode;
use App\Domain\Accounts\Exceptions\CouldNotSendLoginCode;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Domain\Accounts\Exceptions\PhoneAlreadyRegistered;
use App\Domain\Accounts\Support\LoginThrottle;
use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\User;
use App\Support\AppMode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Add and verify a South African mobile with a 6-digit code, by SMS first (or
 * WhatsApp, per `sortd.otp.default_channel`) with the other channel after 30 seconds (spec 014, AC4–AC7). Also used to change a verified number.
 */
#[Layout('components.layouts.app')]
#[Title('Verify your mobile')]
final class VerifyPhone extends Component
{
    public string $phone = '';

    #[Locked]
    public ?string $phoneE164 = null;

    #[Locked]
    public ?int $codeSentAt = null;

    #[Locked]
    public MessageChannel $channel = MessageChannel::WhatsApp;

    #[Locked]
    public ?string $developmentCode = null;

    public string $code = '';

    public function mount(): void
    {
        // The email comes first (AC6).
        if ($this->user()->email_verified_at === null && ! $this->user()->isAdmin()) {
            $this->redirectRoute('verification.email');
        }
    }

    public function sendCode(SendPhoneCode $sendPhoneCode): void
    {
        $this->resetErrorBag();
        $phoneE164 = PhoneNumbers::normaliseSaMobile($this->phone);

        if ($phoneE164 === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid South African mobile number.')]);
        }

        if ($phoneE164 === $this->user()->phone_e164 && $this->user()->phone_verified_at !== null) {
            throw ValidationException::withMessages(['phone' => __('This is already your verified number.')]);
        }

        if (AppMode::skipsPhoneCodes()) {
            $this->saveWithoutCode($phoneE164);

            return;
        }

        $this->deliver($sendPhoneCode, $phoneE164, self::firstChannel(), 'phone');
    }

    /** Test site only, while codes can't be sent: keep the number and continue (decision 041). */
    private function saveWithoutCode(string $phoneE164): void
    {
        if (SendPhoneCode::isTakenByAnotherAccount($this->user(), $phoneE164)) {
            throw ValidationException::withMessages(['phone' => $this->takenMessage()]);
        }

        try {
            $this->user()->forceFill(['phone_e164' => $phoneE164, 'phone_verified_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone' => $this->takenMessage()]);
        }

        activity()->performedOn($this->user())->causedBy($this->user())->log('phone saved without code (test site)');
        $this->redirectIntended(route($this->user()->homeRoute(session()->get('auth.as_pro') === true)));
    }

    /** The other channel, offered after a short wait (spec 014, AC4). */
    public function sendByOtherChannel(SendPhoneCode $sendPhoneCode): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null || $this->codeSentAt === null) {
            return;
        }

        if (now()->getTimestamp() - $this->codeSentAt < (int) config('sortd.otp.sms_fallback_after_seconds')) {
            throw ValidationException::withMessages(['code' => __('Please wait a moment before asking again.')]);
        }

        $other = $this->channel === MessageChannel::Sms ? MessageChannel::WhatsApp : MessageChannel::Sms;
        $this->deliver($sendPhoneCode, $this->phoneE164, $other, 'code');
    }

    public function verifyCode(VerifyPhoneCode $verifyPhoneCode): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null) {
            return;
        }

        $digits = (int) config('sortd.otp.length');
        $this->validate(['code' => ['required', 'digits:'.$digits]], [
            'code.required' => __('Enter the :digits-digit code.', ['digits' => $digits]),
            'code.digits' => __('Enter the :digits-digit code.', ['digits' => $digits]),
        ]);

        $waitSeconds = LoginThrottle::attempt([LoginThrottle::verifyLimit(request()->ip())]);

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in :time.', ['time' => $this->minutes($waitSeconds)])]);
        }

        try {
            $verifyPhoneCode->handle($this->user(), $this->phoneE164, $this->code);
        } catch (LoginCodeRejected $rejected) {
            throw ValidationException::withMessages(['code' => $this->rejectionMessage($rejected)]);
        } catch (PhoneAlreadyRegistered) {
            $this->changeNumber();

            throw ValidationException::withMessages(['phone' => $this->takenMessage()]);
        }

        $this->redirectIntended(route($this->user()->homeRoute(session()->get('auth.as_pro') === true)));
    }

    public function changeNumber(): void
    {
        $this->reset(['phone', 'phoneE164', 'codeSentAt', 'channel', 'developmentCode', 'code']);
    }

    public function maskedPhone(): string
    {
        return $this->phoneE164 === null ? '' : PhoneNumbers::maskForDisplay($this->phoneE164);
    }

    public function smsAvailableIn(): int
    {
        if ($this->codeSentAt === null) {
            return 0;
        }

        return max(0, (int) config('sortd.otp.sms_fallback_after_seconds') - (now()->getTimestamp() - $this->codeSentAt));
    }

    public function render(): View
    {
        return view('livewire.auth.verify-phone', ['changing' => $this->user()->phone_verified_at !== null]);
    }

    private function deliver(SendPhoneCode $sendPhoneCode, string $phoneE164, MessageChannel $channel, string $errorField): void
    {
        $waitSeconds = LoginThrottle::attempt(LoginThrottle::sendLimits($phoneE164, request()->ip()));

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages([$errorField => __('Too many codes requested. Try again in :time.', ['time' => $this->minutes($waitSeconds)])]);
        }

        try {
            $sent = $sendPhoneCode->handle($this->user(), $phoneE164, $channel, request()->ip());
        } catch (PhoneAlreadyRegistered) {
            throw ValidationException::withMessages([$errorField => $this->takenMessage()]);
        } catch (CouldNotSendLoginCode) {
            throw ValidationException::withMessages([$errorField => __("We couldn't send your code. Try SMS or try again shortly.")]);
        }

        $this->phoneE164 = $sent->phoneE164;
        $this->channel = $sent->channel;
        $this->codeSentAt = $sent->sentAt->getTimestamp();
        $this->developmentCode = $sent->developmentCode;
        $this->code = '';
    }

    public static function firstChannel(): MessageChannel
    {
        return config('sortd.otp.default_channel') === 'whatsapp' ? MessageChannel::WhatsApp : MessageChannel::Sms;
    }

    private function takenMessage(): string
    {
        return __('This number is already linked to another account. Sign in to that account or contact support.');
    }

    private function rejectionMessage(LoginCodeRejected $rejected): string
    {
        if ($rejected->reason === LoginCodeRejected::EXPIRED || $rejected->attemptsLeft === 0) {
            return __('This code has expired. Request a new one.');
        }

        if ($rejected->attemptsLeft === null) {
            return __('That code is incorrect.');
        }

        return __('That code is incorrect.').' '.trans_choice(':count attempt left.|:count attempts left.', $rejected->attemptsLeft);
    }

    private function minutes(int $seconds): string
    {
        return trans_choice(':count minute|:count minutes', (int) ceil($seconds / 60));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
