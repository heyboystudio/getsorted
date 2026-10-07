@include('livewire.auth.partials.shell-start')
        <h1 class="text-2xl font-semibold tracking-tight">{{ $asPro ? __('Sign in to join as a pro') : __('Sign in') }}</h1>
        <p class="mt-2 text-zinc-600">{{ __('New to Sortd?') }} <a href="{{ route($asPro ? 'pros.register' : 'register') }}" class="font-medium text-emerald-800 underline underline-offset-4">{{ $asPro ? __('Create a pro account') : __('Create a client account') }}</a></p>

        @if (session('status'))
            <p class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">{{ session('status') }}</p>
        @endif

        <div class="mt-8">
            @include('livewire.auth.partials.google-button')
        </div>

        <form wire:submit="login" class="space-y-4" novalidate>
            @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email', 'readonly' => $linkingGoogle])
            <div x-data="{ show: false }">
                <div class="flex items-baseline justify-between">
                    <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
                    <a wire:navigate.hover href="{{ route('password.request') }}" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Forgot password?') }}</a>
                </div>
                <div class="relative mt-1">
                    <input id="password" :type="show ? 'text' : 'password'" autocomplete="current-password" wire:model="password"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 pr-16 outline-none focus:ring-2 focus:ring-emerald-600">
                    <button type="button" x-on:click="show = ! show" class="absolute inset-y-0 right-0 px-3 text-sm text-zinc-600" x-text="show ? '{{ __('Hide') }}' : '{{ __('Show') }}'"></button>
                </div>
                @error('password') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" wire:model="remember" class="size-5 rounded border-zinc-300 text-emerald-700">
                {{ __('Keep me signed in for 30 days') }}
            </label>

            <button type="submit" wire:loading.attr="disabled" wire:target="login"
                class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="login">{{ $linkingGoogle ? __('Sign in and connect Google') : __('Sign in') }}</span>
                <span wire:loading wire:target="login">{{ __('Signing in…') }}</span>
            </button>
        </form>
    </section>
</main>
