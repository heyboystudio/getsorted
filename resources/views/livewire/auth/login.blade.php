<main class="flex min-h-dvh items-start justify-center px-5 py-12 sm:items-center">
    <section class="w-full max-w-sm">
        <a href="{{ route('home') }}" class="mb-10 inline-block text-2xl font-semibold tracking-tight">{{ __('Sortd') }}<span aria-hidden="true" class="text-emerald-700">.</span></a>

        @if ($step === \App\Domain\Accounts\Enums\LoginStep::Phone)
            <h1 class="text-2xl font-semibold tracking-tight">{{ $asPro ? __('Join Sortd as a pro') : __('Log in or sign up') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __("We'll send a 6-digit code to your phone on WhatsApp.") }}</p>

            <form wire:submit="sendCode" class="mt-8 space-y-4" novalidate>
                <div>
                    <label for="phone" class="block text-sm font-medium">{{ __('Mobile number') }}</label>
                    <input id="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="082 123 4567"
                        @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 text-lg outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('phone'), 'border-zinc-300' => ! $errors->has('phone')])
                        aria-describedby="phone-error" @error('phone') aria-invalid="true" @enderror>
                    @error('phone') <p id="phone-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="sendCode"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="sendCode">{{ __('Send code') }}</span>
                    <span wire:loading wire:target="sendCode">{{ __('Sending…') }}</span>
                </button>
            </form>
        @elseif ($step === \App\Domain\Accounts\Enums\LoginStep::Code)
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Enter your code') }}</h1>
            <p class="mt-2 text-zinc-600">
                {{ __('We sent a code to :phone on :channel.', ['phone' => $this->maskedPhone(), 'channel' => $channel === \App\Contracts\Data\MessageChannel::Sms ? 'SMS' : 'WhatsApp']) }}
            </p>

            @if ($developmentCode !== null)
                <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {{ __('Development: your code is') }} {{ $developmentCode }}
                </p>
            @endif

            <form wire:submit="verifyCode" class="mt-8 space-y-4" novalidate>
                <div>
                    <label for="code" class="block text-sm font-medium">{{ __('6-digit code') }}</label>
                    <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" wire:model="code"
                        @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 text-center text-2xl tracking-[0.5em] outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('code'), 'border-zinc-300' => ! $errors->has('code')])
                        aria-describedby="code-error" @error('code') aria-invalid="true" @enderror>
                    @error('code') <p id="code-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" wire:model="remember" class="size-5 rounded border-zinc-300 text-emerald-700">
                    {{ __('Keep me logged in for 30 days') }}
                </label>

                <button type="submit" wire:loading.attr="disabled" wire:target="verifyCode"
                    class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="verifyCode">{{ __('Verify') }}</span>
                    <span wire:loading wire:target="verifyCode">{{ __('Checking…') }}</span>
                </button>
            </form>

            <div class="mt-6 flex items-center justify-between text-sm"
                 x-data="{ seconds: {{ $this->smsAvailableIn() }} }"
                 x-init="const timer = setInterval(() => { if (seconds > 0) { seconds-- } else { clearInterval(timer) } }, 1000)">
                <button type="button" wire:click="changeNumber" class="text-zinc-600 underline underline-offset-4">{{ __('Wrong number?') }}</button>
                <span x-show="seconds > 0" class="text-zinc-500">{{ __('SMS available in') }} <span x-text="seconds"></span>s</span>
                <button type="button" x-show="seconds === 0" x-cloak wire:click="sendBySms" wire:loading.attr="disabled" wire:target="sendBySms"
                    class="font-medium text-emerald-800 underline underline-offset-4 disabled:opacity-60">{{ __('Send by SMS instead') }}</button>
            </div>
        @else
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Tell us about you') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('Just your name — your number is already verified.') }}</p>

            <form wire:submit="register" class="mt-8 space-y-4" novalidate>
                @foreach (['firstName' => __('First name'), 'lastName' => __('Surname')] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label>
                        <input id="{{ $field }}" type="text" autocomplete="{{ $field === 'firstName' ? 'given-name' : 'family-name' }}" wire:model="{{ $field }}"
                            @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has($field), 'border-zinc-300' => ! $errors->has($field)])
                            aria-describedby="{{ $field }}-error" @error($field) aria-invalid="true" @enderror>
                        @error($field) <p id="{{ $field }}-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div>
                    <label for="email" class="block text-sm font-medium">{{ __('Email') }} <span class="font-normal text-zinc-500">({{ __('optional') }})</span></label>
                    <input id="email" type="email" autocomplete="email" wire:model="email"
                        @class(['mt-1 block w-full rounded-lg border bg-white px-3 py-3 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('email'), 'border-zinc-300' => ! $errors->has('email')])
                        aria-describedby="email-error" @error('email') aria-invalid="true" @enderror>
                    @error('email') <p id="email-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-3 pt-2 text-sm">
                    <label class="flex gap-3">
                        <input type="checkbox" wire:model="acceptTerms" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700">
                        <span>{{ __('I accept the') }} <a href="{{ route('terms') }}" target="_blank" class="underline underline-offset-4">{{ __('terms of service') }}</a></span>
                    </label>
                    @error('acceptTerms') <p class="text-red-700" role="alert">{{ $message }}</p> @enderror

                    <label class="flex gap-3">
                        <input type="checkbox" wire:model="acceptPrivacy" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700">
                        <span>{{ __('I accept the') }} <a href="{{ route('privacy') }}" target="_blank" class="underline underline-offset-4">{{ __('privacy notice') }}</a></span>
                    </label>
                    @error('acceptPrivacy') <p class="text-red-700" role="alert">{{ $message }}</p> @enderror

                    @if ($asPro)
                        <label class="flex gap-3">
                            <input type="checkbox" wire:model="acceptProAgreement" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700"
                                aria-describedby="acceptProAgreement-error" @error('acceptProAgreement') aria-invalid="true" @enderror>
                            <span>{{ __('I accept the') }} <a href="{{ route('pros.agreement') }}" target="_blank" class="underline underline-offset-4">{{ __('pro agreement') }}</a></span>
                        </label>
                        @error('acceptProAgreement') <p id="acceptProAgreement-error" class="text-red-700" role="alert">{{ $message }}</p> @enderror
                    @endif

                    <label class="flex gap-3 text-zinc-600">
                        <input type="checkbox" wire:model="marketing" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700">
                        <span>{{ __('Send me tips and offers (optional)') }}</span>
                    </label>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="register"
                    class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="register">{{ __('Create account') }}</span>
                    <span wire:loading wire:target="register">{{ __('Creating…') }}</span>
                </button>
            </form>
        @endif
    </section>
</main>
