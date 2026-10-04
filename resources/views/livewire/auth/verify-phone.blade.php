@include('livewire.auth.partials.shell-start')
        @unless ($changing)
            <p class="text-sm font-medium text-emerald-800">{{ __('Last step') }}</p>
        @endunless

        @if ($phoneE164 === null)
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $changing ? __('Change your mobile') : __('Add your mobile number') }}</h1>
            <p class="mt-2 text-zinc-600">{{ \App\Livewire\Auth\VerifyPhone::firstChannel() === \App\Contracts\Data\MessageChannel::Sms ? __('We\'ll text you a 6-digit code.') : __('We\'ll send a 6-digit code on WhatsApp.') }} {{ __('Pros and Sortd use this number to keep you updated about your jobs.') }}</p>

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
        @else
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Enter your code') }}</h1>
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
                @php($other = $channel === \App\Contracts\Data\MessageChannel::Sms ? 'WhatsApp' : 'SMS')
                <span x-show="seconds > 0" class="text-zinc-500">{{ __(':channel available in', ['channel' => $other]) }} <span x-text="seconds"></span>s</span>
                <button type="button" x-show="seconds === 0" x-cloak wire:click="sendByOtherChannel" wire:loading.attr="disabled" wire:target="sendByOtherChannel"
                    class="font-medium text-emerald-800 underline underline-offset-4 disabled:opacity-60">{{ __('Send by :channel instead', ['channel' => $other]) }}</button>
            </div>
        @endif

        @unless ($changing)
            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Sign out') }}</button>
            </form>
        @endunless
    </section>
</main>
