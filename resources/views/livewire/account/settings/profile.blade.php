@php($input = 'mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/30')
<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('account.settings') }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Account') }}</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Profile') }}</h1>

        @if ($emailChanged)
            <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">{{ __('Your email address is updated.') }}</p>
        @endif

        <form wire:submit="saveName" class="mt-6 space-y-4" novalidate>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first-name" class="text-sm font-medium">{{ __('First name') }}</label>
                    <input id="first-name" type="text" wire:model="firstName" autocomplete="given-name" class="{{ $input }}">
                    @error('firstName') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="last-name" class="text-sm font-medium">{{ __('Surname') }}</label>
                    <input id="last-name" type="text" wire:model="lastName" autocomplete="family-name" class="{{ $input }}">
                    @error('lastName') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800">{{ __('Save name') }}</button>
            @if ($saved) <span class="ml-3 text-sm text-emerald-800" role="status">{{ __('Saved.') }}</span> @endif
        </form>

        <h2 class="mt-10 font-semibold">{{ __('Email') }}</h2>
        <p class="mt-1 text-sm text-zinc-700">{{ $user->email }}</p>
        @if ($user->pending_email)
            <div class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-950" role="status">
                {{ __('We sent a link to :email. Your address changes once you open it.', ['email' => $user->pending_email]) }}
                <button type="button" wire:click="cancelEmailChange" class="ml-1 underline underline-offset-4">{{ __('Cancel') }}</button>
            </div>
        @endif
        <form wire:submit="changeEmail" class="mt-3" novalidate>
            <label for="new-email" class="text-sm font-medium">{{ __('New email address') }}</label>
            <input id="new-email" type="email" wire:model="newEmail" autocomplete="email" class="{{ $input }}">
            @error('newEmail') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-lg border border-emerald-700 px-4 py-2.5 font-medium text-emerald-900 hover:bg-emerald-50">{{ __('Send confirmation link') }}</button>
            @if ($emailSent) <p class="mt-2 text-sm text-emerald-800" role="status">{{ __('Check your new inbox for the link.') }}</p> @endif
        </form>

        <h2 class="mt-10 font-semibold">{{ __('Mobile number') }}</h2>
        <p class="mt-1 text-sm text-zinc-700">{{ $user->phone_e164 ?? __('Not added yet') }}</p>
        <a wire:navigate.hover href="{{ route('verification.phone') }}" class="mt-2 inline-block text-sm text-emerald-800 underline underline-offset-4">{{ __('Change mobile number') }}</a>
        <p class="mt-1 text-xs text-zinc-500">{{ __('We keep your current number until the new one is verified with a code.') }}</p>
    </section>
</main>
