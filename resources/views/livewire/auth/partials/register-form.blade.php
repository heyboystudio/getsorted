        <div class="mt-8">
            @unless ($withGoogle)
                @include('livewire.auth.partials.google-button')
            @else
                <p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ __('Signing up with Google. Check your name and accept the terms to finish.') }}</p>
            @endunless
        </div>

        <form wire:submit="register" class="space-y-4" novalidate>
            @include('livewire.auth.partials.field', ['name' => 'firstName', 'label' => __('First name'), 'autocomplete' => 'given-name'])
            @include('livewire.auth.partials.field', ['name' => 'lastName', 'label' => __('Surname'), 'autocomplete' => 'family-name'])
            @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email', 'readonly' => $withGoogle])

            @unless ($withGoogle)
                <div x-data="{ show: false }">
                    <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
                    <div class="relative mt-1">
                        <input id="password" :type="show ? 'text' : 'password'" autocomplete="new-password" wire:model="password"
                            @class(['block w-full rounded-lg border bg-white px-3 py-3 pr-16 outline-none focus:ring-2 focus:ring-emerald-600', 'border-red-500' => $errors->has('password'), 'border-zinc-300' => ! $errors->has('password')])
                            aria-describedby="password-hint password-error" @error('password') aria-invalid="true" @enderror>
                        <button type="button" x-on:click="show = ! show" class="absolute inset-y-0 right-0 px-3 text-sm text-zinc-600" x-text="show ? '{{ __('Hide') }}' : '{{ __('Show') }}'"></button>
                    </div>
                    <p id="password-hint" class="mt-1 text-xs text-zinc-500">{{ __('At least 10 characters.') }}</p>
                    @error('password') <p id="password-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
            @endunless

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
                        <input type="checkbox" wire:model="acceptProAgreement" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700">
                        <span>{{ __('I accept the') }} <a href="{{ route('pros.agreement') }}" target="_blank" class="underline underline-offset-4">{{ __('pro agreement') }}</a></span>
                    </label>
                    @error('acceptProAgreement') <p class="text-red-700" role="alert">{{ $message }}</p> @enderror
                @endif

                <label class="flex gap-3 text-zinc-600">
                    <input type="checkbox" wire:model="marketing" class="mt-0.5 size-5 shrink-0 rounded border-zinc-300 text-emerald-700">
                    <span>{{ __('Send me tips and offers (optional)') }}</span>
                </label>
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="register"
                class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="register">{{ $asPro ? __('Create pro account') : __('Create client account') }}</span>
                <span wire:loading wire:target="register">{{ __('Creating…') }}</span>
            </button>
            <p class="text-center text-xs text-zinc-500">{{ __('Next, we\'ll confirm your email and mobile number.') }}</p>
        </form>
