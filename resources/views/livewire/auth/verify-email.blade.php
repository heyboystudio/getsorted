@include('livewire.auth.partials.shell-start')
        <p class="text-sm font-medium text-emerald-800">{{ __('Step 1 of 2') }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Check your email') }}</h1>
        <p class="mt-2 text-zinc-600">{{ __('We sent a link to :email. Open it to confirm your address, then we\'ll verify your mobile.', ['email' => $email]) }}</p>

        <div wire:poll.5s="check" class="mt-8 space-y-4">
            @if ($resent)
                <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">{{ __('We sent a new link.') }}</p>
            @endif
            @error('resend') <p class="text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="resend" wire:loading.attr="disabled" class="w-full rounded-lg border border-zinc-300 bg-white px-4 py-3 font-medium hover:bg-zinc-50 disabled:opacity-60">{{ __('Send the link again') }}</button>
            <p class="text-sm text-zinc-500">{{ __('Can\'t find it? Check your spam folder.') }}</p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Use a different account') }}</button>
        </form>
    </section>
</main>
