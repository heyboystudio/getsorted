<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <a wire:navigate.hover href="{{ route('account.settings') }}" class="mb-6 inline-block text-sm text-zinc-600 underline underline-offset-4">← {{ __('Account') }}</a>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Notifications') }}</h1>
        <p class="mt-2 text-sm text-zinc-600">{{ __('We text you about your jobs. Choose what you want to hear about. Sign-in codes and security messages always arrive.') }}</p>

        <form wire:submit="save" class="mt-6 space-y-6">
            <fieldset>
                <legend class="font-semibold">{{ __('What to send') }}</legend>
                <div class="mt-3 space-y-3">
                    @foreach ($labels as $key => $label)
                        <label class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                            <input type="checkbox" wire:model="groups.{{ $key }}" class="mt-0.5 size-5 rounded border-zinc-300 text-emerald-700 focus:ring-emerald-700">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="font-semibold">{{ __('Where to send it') }}</legend>
                <div class="mt-3 space-y-3">
                    @foreach (['whatsapp' => __('WhatsApp'), 'sms' => __('SMS')] as $value => $label)
                        <label class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 text-sm">
                            <input type="radio" wire:model="channel" value="{{ $value }}" class="size-5 border-zinc-300 text-emerald-700 focus:ring-emerald-700">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ __('Messages go to your verified mobile number. Email messages are not offered yet.') }}</p>
            </fieldset>

            <div>
                <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 font-medium text-white hover:bg-emerald-800">{{ __('Save') }}</button>
                @if ($saved) <span class="ml-3 text-sm text-emerald-800" role="status">{{ __('Saved.') }}</span> @endif
            </div>
        </form>
    </section>
</main>
