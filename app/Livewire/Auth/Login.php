<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Contracts\Data\MessageChannel;
use App\Domain\Accounts\Actions\RegisterCustomer;
use App\Domain\Accounts\Actions\SendLoginCode;
use App\Domain\Accounts\Actions\VerifyLoginCode;
use App\Domain\Accounts\Exceptions\CouldNotSendLoginCode;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Domain\Accounts\Support\LoginThrottle;
use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Phone + OTP login and sign-up (spec 001): number → code → (new customers) details.
 * The verified phone for sign-up is kept in the server session, never trusted from the browser.
 */
#[Layout('components.layouts.app')]
#[Title('Log in')]
final class Login extends Component
{
    private const string VERIFIED_PHONE_KEY = 'login.verified_phone';

    public string $step = 'phone';

    public string $phone = '';

    #[Locked]
    public ?string $phoneE164 = null;

    #[Locked]
    public ?int $codeSentAt = null;

    #[Locked]
    public string $channel = 'whatsapp';

    #[Locked]
    public ?string $developmentCode = null;

    public string $code = '';

    public bool $remember = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public bool $acceptTerms = false;

    public bool $acceptPrivacy = false;

    public bool $marketing = false;

    public function sendCode(SendLoginCode $sendLoginCode): void
    {
        $this->resetErrorBag();

        $phoneE164 = PhoneNumbers::normaliseSaMobile($this->phone);

        if ($phoneE164 === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid South African mobile number.']);
        }

        $this->deliver($sendLoginCode, $phoneE164, MessageChannel::WhatsApp, 'phone');
    }

    public function sendBySms(SendLoginCode $sendLoginCode): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null || $this->codeSentAt === null) {
            $this->step = 'phone';

            return;
        }

        if (now()->getTimestamp() - $this->codeSentAt < (int) config('sortd.otp.sms_fallback_after_seconds')) {
            throw ValidationException::withMessages(['code' => 'Please wait a moment before asking for an SMS.']);
        }

        $this->deliver($sendLoginCode, $this->phoneE164, MessageChannel::Sms, 'code');
    }

    public function verifyCode(VerifyLoginCode $verifyLoginCode): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null) {
            $this->step = 'phone';

            return;
        }

        $this->validate(['code' => ['required', 'digits:'.config('sortd.otp.length')]], [
            'code.required' => 'Enter the 6-digit code.',
            'code.digits' => 'Enter the 6-digit code.',
        ]);

        $waitSeconds = LoginThrottle::attempt([LoginThrottle::verifyLimit(request()->ip())]);

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.$this->minutes($waitSeconds).'.']);
        }

        try {
            $user = $verifyLoginCode->handle($this->phoneE164, $this->code);
        } catch (LoginCodeRejected $rejected) {
            throw ValidationException::withMessages(['code' => $this->rejectionMessage($rejected)]);
        }

        if ($user instanceof User) {
            $this->logIn($user);

            return;
        }

        session()->put(self::VERIFIED_PHONE_KEY, ['phone' => $this->phoneE164, 'at' => now()->getTimestamp()]);
        $this->developmentCode = null;
        $this->step = 'profile';
    }

    public function register(RegisterCustomer $registerCustomer): void
    {
        $this->resetErrorBag();

        $phoneE164 = $this->verifiedPhone();

        if ($phoneE164 === null || $phoneE164 !== $this->phoneE164) {
            $this->reset();

            throw ValidationException::withMessages(['phone' => 'Please verify your number again.']);
        }

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'lowercase', 'email:strict', 'max:255', 'unique:users,email'],
            'acceptTerms' => ['accepted'],
            'acceptPrivacy' => ['accepted'],
            'marketing' => ['boolean'],
        ], [
            'firstName.required' => 'Enter your first name.',
            'lastName.required' => 'Enter your surname.',
            'email.email' => 'Enter a valid email address, or leave it empty.',
            'email.unique' => 'That email address is already in use.',
            'acceptTerms.accepted' => 'Please accept the terms of service.',
            'acceptPrivacy.accepted' => 'Please accept the privacy notice.',
        ]);

        $user = $registerCustomer->handle(
            $phoneE164,
            trim($validated['firstName']),
            trim($validated['lastName']),
            $validated['email'] === '' ? null : $validated['email'],
            (bool) $validated['marketing'],
            request()->ip(),
            request()->userAgent(),
        );

        session()->forget(self::VERIFIED_PHONE_KEY);
        $this->logIn($user);
    }

    public function changeNumber(): void
    {
        $this->reset();
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
        return view('livewire.auth.login');
    }

    private function deliver(SendLoginCode $sendLoginCode, string $phoneE164, MessageChannel $channel, string $errorField): void
    {
        $waitSeconds = LoginThrottle::attempt(LoginThrottle::sendLimits($phoneE164, request()->ip()));

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages([$errorField => 'Too many codes requested. Try again in '.$this->minutes($waitSeconds).'.']);
        }

        try {
            $sent = $sendLoginCode->handle($phoneE164, $channel, request()->ip());
        } catch (CouldNotSendLoginCode) {
            throw ValidationException::withMessages([$errorField => "We couldn't send your code. Try SMS or try again shortly."]);
        }

        $this->phoneE164 = $sent->phoneE164;
        $this->channel = $sent->channel->value;
        $this->codeSentAt = $sent->sentAt->getTimestamp();
        $this->developmentCode = $sent->developmentCode;
        $this->code = '';
        $this->step = 'code';
    }

    private function logIn(User $user): void
    {
        Auth::login($user, $this->remember);
        session()->regenerate();

        $this->redirectRoute('account.home');
    }

    private function verifiedPhone(): ?string
    {
        /** @var array{phone?: string, at?: int}|null $verified */
        $verified = session()->get(self::VERIFIED_PHONE_KEY);
        $ttlSeconds = (int) config('sortd.otp.verified_phone_ttl_minutes') * 60;

        if (! is_array($verified) || ! isset($verified['phone'], $verified['at']) || now()->getTimestamp() - $verified['at'] > $ttlSeconds) {
            return null;
        }

        return $verified['phone'];
    }

    private function rejectionMessage(LoginCodeRejected $rejected): string
    {
        if ($rejected->reason === LoginCodeRejected::EXPIRED || $rejected->attemptsLeft === 0) {
            return 'This code has expired. Request a new one.';
        }

        if ($rejected->attemptsLeft === null) {
            return 'That code is incorrect.';
        }

        return 'That code is incorrect. '.$rejected->attemptsLeft.' '.($rejected->attemptsLeft === 1 ? 'attempt' : 'attempts').' left.';
    }

    private function minutes(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }
}
