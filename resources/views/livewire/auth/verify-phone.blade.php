@include('livewire.auth.partials.shell-start')
        @unless ($changing)
            <p class="auth-step"><b>2 / 2</b>{{ __('Last step') }}</p>
        @endunless

        @if ($phoneE164 === null)
            <h1 class="auth-title">{{ $changing ? __('Change your mobile') : __('Add your mobile number') }}</h1>
            @if (\App\Support\AppMode::skipsPhoneCodes())
                <p class="auth-note warn"><i class="ph-bold ph-warning" aria-hidden="true"></i>{{ __('Test site: codes are switched off, so your number is saved without one.') }}</p>
            @endif
            <p class="auth-sub">{{ \App\Livewire\Auth\VerifyPhone::firstChannel() === \App\Contracts\Data\MessageChannel::Sms ? __("We'll text you a 6-digit code.") : __("We'll send a 6-digit code on WhatsApp.") }} {{ __('Pros and Get Sorted use this number to keep you updated about your jobs.') }}</p>

            <form wire:submit="sendCode" class="auth-form" novalidate>
                <div class="auth-field">
                    <label for="phone">{{ __('Mobile number') }}</label>
                    <input id="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="082 123 4567"
                        @class(['auth-input', 'has-error' => $errors->has('phone')])
                        aria-describedby="phone-error" @error('phone') aria-invalid="true" @enderror>
                    @error('phone') <p id="phone-error" class="auth-err" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="sendCode" class="btn-lime auth-submit">
                    <span wire:loading.remove wire:target="sendCode">{{ __('Send code') }}</span>
                    <span wire:loading wire:target="sendCode">{{ __('Sending…') }}</span>
                    <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                </button>
            </form>
        @else
            <h1 class="auth-title">{{ __('Enter your code') }}</h1>
            <p class="auth-sub">{{ __('We sent a code to :phone on :channel.', ['phone' => $this->maskedPhone(), 'channel' => $channel === \App\Contracts\Data\MessageChannel::Sms ? 'SMS' : 'WhatsApp']) }}</p>

            @if ($developmentCode !== null)
                <p class="auth-note warn"><i class="ph-bold ph-warning" aria-hidden="true"></i><span>{{ __('Development: your code is') }} {{ $developmentCode }}</span></p>
            @endif

            <form wire:submit="verifyCode" class="auth-form" novalidate>
                <div class="auth-field">
                    <label for="code">{{ __('6-digit code') }}</label>
                    <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code"
                        @class(['auth-input otp', 'has-error' => $errors->has('code')])
                        aria-describedby="code-error" @error('code') aria-invalid="true" @enderror>
                    @error('code') <p id="code-error" class="auth-err" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="verifyCode" class="btn-lime auth-submit">
                    <span wire:loading.remove wire:target="verifyCode">{{ __('Verify') }}</span>
                    <span wire:loading wire:target="verifyCode">{{ __('Checking…') }}</span>
                    <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                </button>
            </form>

            <div class="auth-foot"
                 x-data="{ seconds: {{ $this->smsAvailableIn() }} }"
                 x-init="const timer = setInterval(() => { if (seconds > 0) { seconds-- } else { clearInterval(timer) } }, 1000)">
                <button type="button" wire:click="changeNumber" class="auth-text-btn">{{ __('Wrong number?') }}</button>
                @php($other = $channel === \App\Contracts\Data\MessageChannel::Sms ? 'WhatsApp' : 'SMS')
                <span x-show="seconds > 0">{{ __(':channel available in', ['channel' => $other]) }} <span x-text="seconds"></span>s</span>
                <button type="button" x-show="seconds === 0" x-cloak wire:click="sendByOtherChannel" wire:loading.attr="disabled" wire:target="sendByOtherChannel" class="auth-text-btn">{{ __('Send by :channel instead', ['channel' => $other]) }}</button>
            </div>
        @endif

        @unless ($changing)
            <form method="POST" action="{{ route('logout') }}" class="auth-foot">
                @csrf
                <button type="submit" class="auth-text-btn">{{ __('Sign out') }}</button>
            </form>
        @endunless
    </section>
</main>
