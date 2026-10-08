{{-- "Turn on notifications" card (spec 022, AC1, AC2, AC11). The browser's own dialog opens only from the button. --}}
<div x-data="pushControl({ mode: 'card' })" x-show="visible" style="display: none" {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-4') }} role="region" aria-label="{{ __('Notifications on this device') }}">
    <template x-if="justEnabled">
        <p class="font-medium text-zinc-900" role="status">{{ __('Notifications are on for this device.') }}</p>
    </template>

    <template x-if="!justEnabled && state === 'ask'">
        <div>
            <p class="font-medium text-zinc-900">{{ __('Get a pop-up when something happens') }}</p>
            <p class="mt-1 text-sm text-zinc-600">{{ __('We will tell you the moment a quote, a message or a new job arrives, even when this site is closed.') }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" x-on:click="enable()" x-bind:disabled="busy" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-60">{{ __('Turn on notifications') }}</button>
                <button type="button" x-on:click="dismiss()" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Not now') }}</button>
            </div>
            <p x-show="failed || reason" style="display: none" class="mt-2 text-sm text-red-800" role="alert" x-text="reason || '{{ __('That did not work. Please try again.') }}'"></p>
        </div>
    </template>

    <template x-if="!justEnabled && state === 'off'">
        <div>
            <p class="font-medium text-zinc-900">{{ __('Reconnect notifications on this device') }}</p>
            <p class="mt-1 text-sm text-zinc-600">{{ __('Your browser allows notifications, but this device is not connected yet.') }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" x-on:click="enable()" x-bind:disabled="busy" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-60">{{ __('Try again') }}</button>
                <button type="button" x-on:click="dismiss()" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Not now') }}</button>
            </div>
            <p x-show="failed || reason" style="display: none" class="mt-2 text-sm text-red-800" role="alert" x-text="reason || '{{ __('That did not work. Please try again.') }}'"></p>
        </div>
    </template>

    <template x-if="!justEnabled && state === 'install'">
        <div>
            <p class="font-medium text-zinc-900">{{ __('Add GetSorted to your Home Screen to get pop-ups') }}</p>
            <p class="mt-1 text-sm text-zinc-600">{{ __('On iPhone and iPad, tap the Share button, then "Add to Home Screen". Open GetSorted from your Home Screen and turn notifications on there.') }}</p>
            <button type="button" x-on:click="dismiss()" class="mt-3 text-sm text-zinc-600 underline underline-offset-4">{{ __('Not now') }}</button>
        </div>
    </template>

    <template x-if="!justEnabled && state === 'blocked'">
        <div>
            <p class="font-medium text-zinc-900">{{ __('Notifications are blocked in this browser') }}</p>
            <p class="mt-1 text-sm text-zinc-600">{{ __('To get pop-ups, allow notifications for this site in your browser settings (the lock or settings icon next to the address bar), then reload the page.') }}</p>
            <button type="button" x-on:click="dismiss()" class="mt-3 text-sm text-zinc-600 underline underline-offset-4">{{ __('Not now') }}</button>
        </div>
    </template>
</div>
