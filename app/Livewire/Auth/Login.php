<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Contracts\Data\MessageChannel;
use App\Domain\Accounts\Actions\RegisterCustomer;
use App\Domain\Accounts\Actions\SendLoginCode;
use App\Domain\Accounts\Actions\VerifyLoginCode;
use App\Domain\Accounts\Enums\LoginStep;
use App\Domain\Accounts\Exceptions\CouldNotSendLoginCode;
use App\Domain\Accounts\Exceptions\LoginCodeRejected;
use App\Domain\Accounts\Exceptions\PhoneAlreadyRegistered;
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

    #[Locked]
    public LoginStep $step = LoginStep::Phone;

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

    public bool $remember = false;

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public bool $acceptTerms = false;

    public bool $acceptPrivacy = false;

    public bool $marketing = false;

    public function sendCode(SendLoginCode $sendLoginCode): void
    {
        if ($this->redirectIfLoggedIn()) {
            return;
        }

        $this->resetErrorBag();

        $phoneE164 = PhoneNumbers::normaliseSaMobile($this->phone);

        if ($phoneE164 === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid South African mobile number.')]);
        }

        $this->deliver($sendLoginCode, $phoneE164, MessageChannel::WhatsApp, 'phone');
    }

    public function sendBySms(SendLoginCode $sendLoginCode): void
    {
        if ($this->redirectIfLoggedIn()) {
            return;
        }

        $this->resetErrorBag();

        if ($this->phoneE164 === null || $this->codeSentAt === null) {
            $this->step = LoginStep::Phone;

            return;
        }

        if (now()->getTimestamp() - $this->codeSentAt < (int) config('sortd.otp.sms_fallback_after_seconds')) {
            throw ValidationException::withMessages(['code' => __('Please wait a moment before asking for an SMS.')]);
        }

        $this->deliver($sendLoginCode, $this->phoneE164, MessageChannel::Sms, 'code');
    }

    public function verifyCode(VerifyLoginCode $verifyLoginCode): void
    {
        if ($this->redirectIfLoggedIn()) {
            return;
        }

        $this->resetErrorBag();

        if ($this->phoneE164 === null) {
            $this->step = LoginStep::Phone;

            return;
        }

        $this->validate(['code' => ['required', 'digits:'.config('sortd.otp.length')]], [
            'code.required' => __('Enter the :digits-digit code.', ['digits' => config('sortd.otp.length')]),
            'code.digits' => __('Enter the :digits-digit code.', ['digits' => config('sortd.otp.length')]),
        ]);

        $waitSeconds = LoginThrottle::attempt([LoginThrottle::verifyLimit(request()->ip())]);

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in :time.', ['time' => $this->minutes($waitSeconds)])]);
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
        $this->step = LoginStep::Profile;
    }

    public function register(RegisterCustomer $registerCustomer): void
    {
        if ($this->redirectIfLoggedIn()) {
            return;
        }

        $this->resetErrorBag();

        $phoneE164 = $this->verifiedPhone();

        if ($phoneE164 === null || $phoneE164 !== $this->phoneE164) {
            $this->reset();

            throw ValidationException::withMessages(['phone' => __('Please verify your number again.')]);
        }

        $waitSeconds = LoginThrottle::attempt([LoginThrottle::registerLimit(request()->ip())]);

        if ($waitSeconds !== null) {
            throw ValidationException::withMessages(['firstName' => __('Too many attempts. Try again in :time.', ['time' => $this->minutes($waitSeconds)])]);
        }

        $this->email = mb_strtolower(trim($this->email));

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email:strict', 'max:255'],
            'acceptTerms' => ['accepted'],
            'acceptPrivacy' => ['accepted'],
            'marketing' => ['boolean'],
        ], [
            'firstName.required' => __('Enter your first name.'),
            'lastName.required' => __('Enter your surname.'),
            'email.email' => __('Enter a valid email address, or leave it empty.'),
            'acceptTerms.accepted' => __('Please accept the terms of service.'),
            'acceptPrivacy.accepted' => __('Please accept the privacy notice.'),
        ]);

        try {
            $user = $registerCustomer->handle(
                $phoneE164,
                trim($validated['firstName']),
                trim($validated['lastName']),
                $validated['email'] === '' ? null : $validated['email'],
                (bool) $validated['marketing'],
                request()->ip(),
                request()->userAgent(),
            );
        } catch (PhoneAlreadyRegistered) {
            session()->forget(self::VERIFIED_PHONE_KEY);
            $this->reset();

            throw ValidationException::withMessages(['phone' => __('This number already has an account. Log in to continue.')]);
        }

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
            throw ValidationException::withMessages([$errorField => __('Too many codes requested. Try again in :time.', ['time' => $this->minutes($waitSeconds)])]);
        }

        try {
            $sent = $sendLoginCode->handle($phoneE164, $channel, request()->ip());
        } catch (CouldNotSendLoginCode) {
            throw ValidationException::withMessages([$errorField => __("We couldn't send your code. Try SMS or try again shortly.")]);
        }

        $this->phoneE164 = $sent->phoneE164;
        $this->channel = $sent->channel;
        $this->codeSentAt = $sent->sentAt->getTimestamp();
        $this->developmentCode = $sent->developmentCode;
        $this->code = '';
        $this->step = LoginStep::Code;
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

    /** `guest` middleware only runs on page load, so actions re-check it. */
    private function redirectIfLoggedIn(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $this->redirectRoute('account.home');

        return true;
    }
}
