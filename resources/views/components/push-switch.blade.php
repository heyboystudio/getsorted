{{-- Pop-up notifications on this device, with its real state (spec 022, AC10). --}}
<section x-data="pushControl({ mode: 'switch' })" x-show="visible" style="display: none" {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-4') }} aria-labelledby="push-switch-title">
    <h2 id="push-switch-title" class="font-semibold">{{ __('Pop-up notifications on this device') }}</h2>

    <p x-show="state === 'on'" style="display: none" class="mt-1 text-sm text-emerald-800">{{ __('On. You will get a pop-up on this device.') }}</p>
    <p x-show="state === 'off' || state === 'ask'" style="display: none" class="mt-1 text-sm text-zinc-600">{{ __('Off. Turn on to get a pop-up the moment a quote, message or job arrives.') }}</p>
    <p x-show="state === 'blocked'" style="display: none" class="mt-1 text-sm text-amber-800">{{ __('Blocked in this browser. Allow notifications for this site in your browser settings, then reload the page.') }}</p>
    <p x-show="state === 'install'" style="display: none" class="mt-1 text-sm text-zinc-600">{{ __('On iPhone and iPad, add GetSorted to your Home Screen first (Share, then "Add to Home Screen"), then open it from there and turn this on.') }}</p>
    <p x-show="state === 'unsupported'" style="display: none" class="mt-1 text-sm text-zinc-600">{{ __('This browser does not support pop-up notifications. Try Chrome, Edge, Firefox or Safari.') }}</p>

    <button type="button" x-show="state === 'on'" style="display: none" x-on:click="disable()" x-bind:disabled="busy" class="mt-3 rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-60">{{ __('Turn off on this device') }}</button>
    <button type="button" x-show="state === 'off' || state === 'ask'" style="display: none" x-on:click="enable()" x-bind:disabled="busy" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Turn on on this device') }}</button>
    <p x-show="failed || (reason && state === 'off')" style="display: none" class="mt-2 text-sm text-red-800" role="alert" x-text="reason || '{{ __('That did not work. Please try again.') }}'"></p>
</section>
