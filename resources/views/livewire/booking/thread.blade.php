{{-- Spec 017: booking in one Siya thread. Every message is escaped text; nothing is rendered as HTML. --}}
@php($chip = 'rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm hover:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 disabled:opacity-60')
@php($primary = 'w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60')
@php($tapped = 'startNextJob,pickTrade,send,continueAfterEmergency,retry,startBooking,selectProperty,chooseWhen,finishPhotos,confirmBooking,joinWaitlist,noThanks,differentTrade,removeFact')
<main class="flex min-h-dvh justify-center" x-data="{ pending: '' }">
    <section class="flex w-full max-w-2xl flex-col px-4">
        <header class="gs-siya-head sticky top-0 z-10 -mx-4 border-b border-zinc-200 bg-stone-50/95 px-4 pb-3 pt-4 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <a href="{{ auth()->check() ? route('account.home') : route('home') }}" wire:navigate class="text-zinc-500" aria-label="{{ __('Back') }}">←</a>
                    <span class="gs-siya-avatar flex size-10 items-center justify-center rounded-full bg-emerald-700 font-semibold text-white" aria-hidden="true">S</span>
                    <div>
                        <h1 class="font-semibold leading-tight">{{ __('Siya') }}</h1>
                        <p class="text-xs text-zinc-500">{{ __('Get Sorted’s AI assistant · can make mistakes') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="restart" wire:confirm="{{ __('Start over? This clears the chat.') }}" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Restart') }}</button>
            </div>
        </header>

        <ol class="flex-1 space-y-3 py-4" aria-live="polite" aria-label="{{ __('Conversation') }}">
            @foreach ($messages as $item)
                @php($kind = $item['kind'] ?? null)
                <li wire:key="msg-{{ $loop->index }}" @class(['flex', 'justify-end' => $item['role'] === 'customer'])>
                    @if ($kind === 'emergency')
                        <div class="w-full rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert"><strong>{{ __('Safety first') }}:</strong> {{ $item['text'] }}</div>
                    @elseif ($kind === 'safety')
                        <div class="w-full rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"><strong>{{ __('Safety advice') }}:</strong> {{ $item['text'] }} <span class="block pt-1 text-xs">{{ __('This is guidance, not a guarantee.') }}</span></div>
                    @elseif ($kind === 'done')
                        <div class="flex w-full items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <div><p class="text-xs font-medium text-emerald-800">{{ $item['label'] ?? '' }}</p><p class="text-sm font-medium text-emerald-950">{{ $item['text'] }}</p></div>
                            <span class="text-emerald-700" aria-hidden="true">✓</span>
                        </div>
                    @elseif ($kind === 'error')
                        <div class="rounded-lg bg-zinc-100 px-4 py-3 text-sm text-zinc-700">{{ $item['text'] }}</div>
                    @else
                        <p @class([
                            'max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-2 text-[15px]',
                            'bg-emerald-700 text-white' => $item['role'] === 'customer',
                            'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200' => $item['role'] === 'assistant',
                        ])>{{ $item['text'] }}</p>
                    @endif
                </li>
            @endforeach
            {{-- Shown at once on a tap, before the server answers (AC14). --}}
            <li wire:loading.flex wire:target="{{ $tapped }}" class="hidden justify-end" x-show="pending !== ''">
                <p class="max-w-[85%] rounded-2xl bg-emerald-700 px-4 py-2 text-[15px] text-white" x-text="pending"></p>
            </li>
            <li wire:loading.flex wire:target="{{ $tapped }}" class="hidden">
                <p class="rounded-2xl bg-white px-4 py-2 text-sm text-zinc-500 shadow-sm ring-1 ring-zinc-200">{{ __('Siya is typing…') }}</p>
            </li>
        </ol>

        @if ($retryPending && $stage !== 'emergency')
            <button type="button" wire:click="retry" wire:loading.attr="disabled" wire:target="retry" class="mb-3 {{ $chip }}">{{ __('Try again') }}</button>
        @endif

        @if ($limitReached)
            <p class="mb-3 text-sm text-zinc-600">{{ __('This conversation has reached its message limit. Restart to continue booking. Emergency guidance is still available.') }}</p>
        @endif

        @if (($trade || $facts !== []) && ! in_array($stage, ['emergency', 'posted', 'closed', 'notes'], true))
            <details class="mb-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm" @if ($stage === 'chat') open @endif>
                <summary class="cursor-pointer font-medium">{{ __('What I’ve got so far') }}</summary>
                <p class="mt-2"><span class="text-zinc-500">{{ __('Trade') }}:</span> {{ $trade?->name ?? __('Not sure yet') }}</p>
                @if ($facts !== [])
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($facts as $fact)
                            <li wire:key="fact-{{ $fact['id'] }}" class="flex items-center gap-1 rounded-full bg-amber-100 py-1 pl-3 pr-1 text-amber-950">
                                {{ $fact['text'] }}
                                <button type="button" wire:click="removeFact(@js($fact['id']))" wire:loading.attr="disabled" class="flex size-6 items-center justify-center rounded-full hover:bg-amber-200" aria-label="{{ __('Remove: :fact', ['fact' => $fact['text']]) }}">×</button>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-zinc-500">{{ __('Pros see this. Tell me if anything is wrong, or tap × to remove it.') }}</p>
                @endif
            </details>
        @endif

        {{-- The current card --}}
        <div class="pb-4" wire:loading.class="opacity-60" wire:target="{{ $tapped }}">
            @if ($stage === 'emergency')
                <p class="mb-3 text-sm text-red-900">{{ __('Contact emergency services first. Get Sorted can only help plan later repair work.') }}</p>
                <button type="button" wire:click="continueAfterEmergency" wire:loading.attr="disabled" class="{{ $chip }}">{{ __('Discuss a later repair') }}</button>
            @elseif ($stage === 'chat')
                @if ($trades->isNotEmpty())
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($trades as $tradeOption)
                            <button type="button" wire:key="trade-{{ $tradeOption->key }}" wire:click="pickTrade(@js($tradeOption->key))" x-on:click="pending = @js($tradeOption->name)"
                                class="rounded-xl border border-zinc-200 bg-white px-4 py-4 text-left font-medium hover:border-emerald-700">{{ $tradeOption->name }} <span class="float-right text-zinc-400" aria-hidden="true">›</span></button>
                        @endforeach
                    </div>
                @elseif (! $trade)
                    <button type="button" wire:click="showTrades" wire:loading.attr="disabled" class="text-sm text-emerald-800 underline">{{ __('Choose a trade instead') }}</button>
                @endif
                @if ($ready)
                    <button type="button" wire:click="startBooking" wire:loading.attr="disabled" x-on:click="pending = @js(__('Continue to book'))" class="mt-3 {{ $primary }}">{{ __('Continue to book') }}</button>
                    <p class="mt-2 text-center text-xs text-zinc-500">{{ __('Or keep chatting. You can add more detail any time before you confirm.') }}</p>
                @endif
            @elseif ($stage === 'signin')
                <div class="rounded-xl border border-zinc-200 bg-white p-4">
                    @if ($isGuest)
                        <p class="font-medium">{{ __('Sign in to book') }}</p>
                        <p class="mt-1 text-sm text-zinc-600">{{ __('It takes a minute. Your answers are kept and we’ll carry on right here.') }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" wire:click="signUp" class="rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white">{{ __('Sign up') }}</button>
                            <button type="button" wire:click="signIn" class="rounded-lg border border-zinc-300 px-4 py-3 font-medium">{{ __('Sign in') }}</button>
                        </div>
                    @elseif ($needsPhone)
                        <p class="font-medium">{{ __('Verify your mobile number to book') }}</p>
                        <button type="button" wire:click="verifyPhone" class="mt-4 {{ $primary }}">{{ __('Verify my number') }}</button>
                    @else
                        <p class="text-sm text-zinc-700">{{ __('Bookings need a customer account.') }}</p>
                    @endif
                </div>
            @elseif ($stage === 'where')
                <div class="rounded-xl border border-zinc-200 bg-white p-4">
                    <p class="text-sm text-zinc-500">{{ __('We only share your street address with the pro whose quote you accept.') }}</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($properties as $property)
                            <button type="button" wire:key="property-{{ $property->public_id }}" wire:click="selectProperty(@js($property->public_id))" x-on:click="pending = @js($property->label.($property->area_label ? ', '.$property->area_label : ''))"
                                class="block w-full rounded-xl border border-zinc-200 px-4 py-3 text-left hover:border-emerald-700">
                                <span class="block font-medium">{{ $property->label }}</span>
                                <span class="block text-sm text-zinc-600">{{ $property->street_address }}@if ($property->area_label), {{ $property->area_label }}@endif</span>
                            </button>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addProperty" class="mt-3 w-full rounded-xl border border-dashed border-zinc-300 px-4 py-3 text-emerald-800">+ {{ $properties->isEmpty() ? __('Add your address') : __('Add another property') }}</button>
                    <p wire:loading wire:target="selectProperty" class="mt-3 text-sm text-zinc-500">{{ __('Looking for pros near you…') }}</p>
                    @error('where') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
            @elseif ($stage === 'add_property')
                <div class="rounded-xl border border-zinc-200 bg-white p-4">
                    <p class="font-medium">{{ __('Add an address') }}</p>
                    <div class="mt-3">@include('livewire.partials.address-search')</div>
                    @error('addressQuery') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    @if ($newStreet !== '')
                        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">{{ $newStreet }}@if ($newArea), {{ $newArea }}@endif</p>
                    @endif
                    <fieldset class="mt-4">
                        <legend class="text-sm font-medium">{{ __('Type of property') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($propertyTypes as $type)
                                <label class="cursor-pointer rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                                    <input type="radio" wire:model="newType" value="{{ $type->value }}" class="sr-only"> {{ $type->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('newType') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </fieldset>
                    <div class="mt-4 flex gap-2">
                        <button type="button" wire:click="saveProperty" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Use this address') }}</button>
                        <button type="button" wire:click="cancelAddProperty" class="rounded-lg border border-zinc-300 px-4 py-3">{{ __('Cancel') }}</button>
                    </div>
                    <p wire:loading wire:target="saveProperty" class="mt-3 text-sm text-zinc-500">{{ __('Looking for pros near you…') }}</p>
                </div>
            @elseif ($stage === 'waitlist')
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="joinWaitlist" x-on:click="pending = @js(__('Yes, keep me updated'))" class="rounded-full bg-emerald-700 px-5 py-2 text-sm font-medium text-white">{{ __('Yes, keep me updated') }}</button>
                    <button type="button" wire:click="differentTrade" x-on:click="pending = @js(__('Choose a different trade'))" class="{{ $chip }}">{{ __('Choose a different trade') }}</button>
                    <button type="button" wire:click="noThanks" x-on:click="pending = @js(__('No thanks'))" class="{{ $chip }}">{{ __('No thanks') }}</button>
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ __('“Keep me updated” lets Get Sorted contact you about this trade near your address.') }} <a href="{{ route('privacy') }}" wire:navigate class="underline">{{ __('Privacy notice') }}</a></p>
                @error('waitlist') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @elseif ($stage === 'closed')
                <button type="button" wire:click="differentTrade" x-on:click="pending = @js(__('Choose a different trade'))" class="{{ $chip }}">{{ __('Choose a different trade') }}</button>
            @elseif ($stage === 'when')
                <div class="rounded-xl border border-zinc-200 bg-white p-4"
                    x-data="{
                        open: false, date: $wire.entangle('preferredDate'), min: @js($minDate), max: @js($maxDate), view: null,
                        init() { const d = new Date((this.date || this.min) + 'T00:00:00'); this.view = new Date(d.getFullYear(), d.getMonth(), 1) },
                        iso(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') },
                        get title() { return this.view.toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' }) },
                        get cells() {
                            const y = this.view.getFullYear(), m = this.view.getMonth(), out = [];
                            for (let i = 0; i < new Date(y, m, 1).getDay(); i++) out.push(null);
                            for (let d = 1; d <= new Date(y, m + 1, 0).getDate(); d++) { const iso = this.iso(new Date(y, m, d)); out.push({ d, iso, off: iso < this.min || iso > this.max }) }
                            return out;
                        },
                        get canPrev() { return this.iso(new Date(this.view.getFullYear(), this.view.getMonth(), 0)) >= this.min },
                        get canNext() { return this.iso(new Date(this.view.getFullYear(), this.view.getMonth() + 1, 1)) <= this.max },
                        shift(n) { this.view = new Date(this.view.getFullYear(), this.view.getMonth() + n, 1) },
                        pick(iso) { this.date = iso },
                        quick(offset) { const d = new Date(this.min + 'T00:00:00'); d.setDate(d.getDate() + offset); const iso = this.iso(d); if (iso <= this.max) { this.date = iso; this.view = new Date(d.getFullYear(), d.getMonth(), 1) } },
                        weekend() { const d = new Date(this.min + 'T00:00:00'); d.setDate(d.getDate() + ((6 - d.getDay() + 7) % 7)); const iso = this.iso(d); if (iso <= this.max) { this.date = iso; this.view = new Date(d.getFullYear(), d.getMonth(), 1) } },
                        get pretty() { return this.date ? new Date(this.date + 'T00:00:00').toLocaleDateString('en-ZA', { weekday: 'long', day: 'numeric', month: 'long' }) : '' }
                    }"
                    x-on:keydown.escape.window="open = false">
                    <p class="font-medium">{{ __('When do you need help?') }}</p>
                    @if (in_array(\App\Domain\ServiceJobs\Enums\TimeWindow::Today, $windows, true))
                        <button type="button" wire:click="chooseWhen('today')" x-on:click="pending = @js(__('Urgent — today'))" class="mt-3 w-full rounded-lg border border-red-300 bg-red-50 px-4 py-3 font-medium text-red-900">{{ __('Urgent — today') }}</button>
                    @endif
                    <button type="button" x-on:click="open = true" class="mt-3 flex w-full items-center justify-between rounded-lg border border-zinc-300 bg-white px-4 py-3 text-left hover:border-emerald-700" aria-haspopup="dialog">
                        <span x-text="date ? pretty : @js(__('Choose a date and time'))" x-bind:class="date ? 'font-medium' : 'text-zinc-500'"></span>
                        <span class="text-zinc-400" aria-hidden="true">▾</span>
                    </button>
                    @error('when') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Choose a date and time') }}">
                            <div class="absolute inset-0 bg-black/40" x-on:click="open = false" x-transition.opacity></div>
                            <div x-show="open" x-trap.noscroll="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-6 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                                class="relative w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
                                <div class="flex items-center justify-between">
                                    <button type="button" x-on:click="shift(-1)" x-bind:disabled="! canPrev" class="flex size-10 items-center justify-center rounded-full hover:bg-zinc-100 disabled:opacity-30" aria-label="{{ __('Previous month') }}">‹</button>
                                    <p class="font-semibold" x-text="title" aria-live="polite"></p>
                                    <button type="button" x-on:click="shift(1)" x-bind:disabled="! canNext" class="flex size-10 items-center justify-center rounded-full hover:bg-zinc-100 disabled:opacity-30" aria-label="{{ __('Next month') }}">›</button>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button" x-on:click="quick(0)" class="rounded-full border border-zinc-300 px-3 py-1 text-sm hover:border-emerald-700">{{ __('Today') }}</button>
                                    <button type="button" x-on:click="quick(1)" class="rounded-full border border-zinc-300 px-3 py-1 text-sm hover:border-emerald-700">{{ __('Tomorrow') }}</button>
                                    <button type="button" x-on:click="weekend()" class="rounded-full border border-zinc-300 px-3 py-1 text-sm hover:border-emerald-700">{{ __('This weekend') }}</button>
                                </div>
                                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs text-zinc-500" aria-hidden="true">
                                    @foreach ([__('Su'), __('Mo'), __('Tu'), __('We'), __('Th'), __('Fr'), __('Sa')] as $weekday) <span>{{ $weekday }}</span> @endforeach
                                </div>
                                <div class="mt-1 grid grid-cols-7 gap-1" role="grid" aria-label="{{ __('Pick a day') }}">
                                    <template x-for="(cell, index) in cells" :key="index">
                                        <div class="aspect-square">
                                            <template x-if="cell">
                                                <button type="button" x-on:click="pick(cell.iso)" x-bind:disabled="cell.off" x-bind:aria-pressed="date === cell.iso"
                                                    x-bind:class="date === cell.iso ? 'bg-emerald-700 text-white' : (cell.off ? 'text-zinc-300' : 'hover:bg-emerald-50')"
                                                    class="size-full rounded-full text-sm disabled:cursor-not-allowed" x-text="cell.d"></button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <p class="mt-4 text-sm font-medium">{{ __('What time suits you?') }}</p>
                                <div class="mt-2 grid grid-cols-3 gap-2">
                                    @foreach ($windows as $window)
                                        @continue($window === \App\Domain\ServiceJobs\Enums\TimeWindow::Today)
                                        <button type="button" wire:click="chooseWhen(@js($window->value))" x-bind:disabled="! date" x-on:click="pending = pretty + ', ' + @js($window->label())"
                                            class="rounded-xl border border-zinc-300 px-2 py-3 text-sm font-medium hover:border-emerald-700 disabled:opacity-40">{{ $window->label() }}</button>
                                    @endforeach
                                </div>
                                <button type="button" x-on:click="open = false" class="mt-4 w-full rounded-lg px-4 py-2 text-sm text-zinc-600 underline underline-offset-4">{{ __('Close') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            @elseif ($stage === 'photos')
                <div class="rounded-xl border border-zinc-200 bg-white p-4">
                    <p class="font-medium">{{ __('Add a few quick photos') }}</p>
                    <p class="text-sm text-zinc-500">{{ __('Optional, but recommended. Up to :count photos.', ['count' => config('sortd.job_photos.max_count')]) }}</p>
                    @if ($photos->isNotEmpty())
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ($photos as $photo)
                                <div wire:key="photo-{{ $photo->uuid }}" class="relative overflow-hidden rounded-lg">
                                    <img src="{{ $photoUrls[$photo->uuid] }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full object-cover">
                                    <button type="button" wire:click="removePhoto(@js($photo->uuid))" class="absolute right-1 top-1 rounded-full bg-white/90 px-2 text-sm" aria-label="{{ __('Remove photo :number', ['number' => $loop->iteration]) }}">×</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($photos->count() < config('sortd.job_photos.max_count'))
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <label class="cursor-pointer rounded-lg bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-900">
                                <input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" wire:model="photoUpload" class="sr-only"> {{ __('Upload from gallery') }}
                            </label>
                            <label class="cursor-pointer rounded-lg border border-zinc-300 px-4 py-3 text-center text-sm font-medium">
                                <input type="file" accept="image/*" capture="environment" wire:model="photoUpload" class="sr-only"> {{ __('Take a photo') }}
                            </label>
                        </div>
                    @endif
                    <p wire:loading wire:target="photoUpload" class="mt-2 text-sm text-zinc-500">{{ __('Uploading photo…') }}</p>
                    @error('photoUpload') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button type="button" wire:click="finishPhotos" wire:loading.attr="disabled" wire:target="photoUpload,finishPhotos" x-on:click="pending = @js($photos->isEmpty() ? __('Skip for now') : __('Continue'))" class="mt-4 {{ $primary }}">{{ $photos->isEmpty() ? __('Skip for now') : __('Continue') }}</button>
                </div>
            @elseif ($stage === 'notes')
                <div class="rounded-xl border border-zinc-200 bg-white p-4">
                    <p class="text-sm font-medium">{{ __('What pros will see') }}</p>
                    @if ($facts !== [])
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($facts as $fact)
                                <li wire:key="edit-fact-{{ $fact['id'] }}" class="flex items-center gap-1 rounded-full bg-amber-100 py-1 pl-3 pr-1 text-sm text-amber-950">
                                    {{ $fact['text'] }}
                                    <button type="button" wire:click="removeFact(@js($fact['id']))" class="flex size-6 items-center justify-center rounded-full hover:bg-amber-200" aria-label="{{ __('Remove: :fact', ['fact' => $fact['text']]) }}">×</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <label for="notes-draft" class="mt-4 block text-sm font-medium">{{ __('Notes for your pro') }}</label>
                    <textarea id="notes-draft" rows="4" maxlength="{{ config('sortd.jobs.notes_max_length') }}" wire:model="notesDraft" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2"></textarea>
                    @error('notesDraft') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button type="button" wire:click="saveNotes" class="mt-3 {{ $primary }}">{{ __('Save') }}</button>
                </div>
            @elseif ($stage === 'summary' && $summary)
                <div class="rounded-xl border border-zinc-200 bg-white">
                    <p class="px-4 pt-4 text-xs font-medium uppercase tracking-widest text-emerald-800">{{ __('Booking summary') }}</p>
                    <dl class="divide-y divide-zinc-100">
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('Need help with') }}</dt><button type="button" wire:click="change('trade')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="font-medium">{{ $trade?->name }} @if ($summary['urgent']) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span> @endif</dd>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('What pros will see') }}</dt><button type="button" wire:click="change('details')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="mt-2">
                                @if ($summary['facts'] !== [])
                                    <ul class="flex flex-wrap gap-2">@foreach ($summary['facts'] as $fact)<li class="rounded-full bg-amber-100 px-3 py-1 text-sm text-amber-950">{{ $fact }}</li>@endforeach</ul>
                                @endif
                                @if ($notes !== '')<p class="mt-2 whitespace-pre-line text-sm">{{ $notes }}</p>@elseif ($summary['facts'] === [])<p class="text-sm">{{ __('None') }}</p>@endif
                            </dd>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('Address') }}</dt><button type="button" wire:click="change('where')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="mt-1 text-sm">@if ($summary['property']){{ $summary['property']->street_address }}@if ($summary['property']->area_label), {{ $summary['property']->area_label }}@endif@endif</dd>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('When') }}</dt><button type="button" wire:click="change('when')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="mt-1 text-sm">{{ $summary['when'] }}</dd>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('Photos') }}</dt><button type="button" wire:click="change('photos')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="mt-2">
                                @if ($photos->isEmpty())
                                    <span class="text-sm">{{ __('None') }}</span>
                                @else
                                    <div class="grid grid-cols-4 gap-2">
                                        @foreach ($photos as $photo)
                                            <img wire:key="summary-photo-{{ $photo->uuid }}" src="{{ $photoUrls[$photo->uuid] }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-lg object-cover">
                                        @endforeach
                                    </div>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if ($summary['key'])
                        <div class="px-4"><livewire:booking.job-summary-card :job-public-id="$jobPublicId" :key="'job-summary-'.$summary['key']" /></div>
                    @endif
                    @if ($summary['guidance'])
                        <div class="mx-4 mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                            @if ($summary['advice'] !== [])
                                <ul class="list-disc space-y-1 pl-5">@foreach ($summary['advice'] as $line) <li>{{ $line }}</li> @endforeach</ul>
                            @endif
                            <p class="mt-2">{{ __('This is guidance, not a guarantee.') }}</p>
                        </div>
                    @endif
                    <div class="p-4">
                        @error('post') <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror
                        <button type="button" wire:click="confirmBooking" wire:loading.attr="disabled" wire:target="confirmBooking" x-on:click="pending = @js(__('Confirm booking'))" class="{{ $primary }} text-lg">
                            <span wire:loading.remove wire:target="confirmBooking">{{ __('Confirm booking') }}</span>
                            <span wire:loading wire:target="confirmBooking">{{ __('Booking…') }}</span>
                        </button>
                        <p class="mt-2 text-center text-xs text-zinc-500">{{ __('By confirming, you agree to the') }} <a href="{{ route('terms') }}" wire:navigate class="underline">{{ __('Terms') }}</a> {{ __('and allow us to share your job (without your street address) with vetted pros near you.') }}</p>
                    </div>
                </div>
            @elseif ($stage === 'posted' && $jobPublicId)
                <a href="{{ route('jobs.show', $jobPublicId) }}" wire:navigate class="block text-center {{ $primary }}">{{ __('See your job') }}</a>
                @if ($parked !== [])
                    <button type="button" wire:click="startNextJob" wire:loading.attr="disabled" class="mt-3 w-full {{ $chip }}">{{ __('Book “:job” next', ['job' => $parked[0]]) }}</button>
                @endif
            @endif
            <div wire:key="end-{{ count($messages) }}-{{ $stage }}" x-init="$nextTick(() => $el.scrollIntoView({ block: 'end' }))"></div>
        </div>

        @if (! in_array($stage, ['posted', 'closed'], true))
            <form wire:submit="send" x-on:submit="pending = $wire.message" class="gs-siya-form sticky bottom-0 -mx-4 flex gap-2 border-t border-zinc-200 bg-stone-50 px-4 py-3">
                <label for="siya-message" class="sr-only">{{ __('Message Siya') }}</label>
                <input id="siya-message" type="text" wire:model="message" maxlength="1000" autocomplete="off"
                    placeholder="{{ $available ? __('Message Siya…') : __('Tap an option to continue') }}"
                    class="block w-full rounded-full border border-zinc-300 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-emerald-600">
                <button type="submit" wire:loading.attr="disabled" wire:target="send" class="rounded-full bg-emerald-700 px-5 py-3 font-medium text-white disabled:opacity-60" aria-label="{{ __('Send') }}">↑</button>
            </form>
            @error('message') <p class="pb-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <p class="pb-3 text-center text-xs text-zinc-500">{{ __('Don’t share phone numbers or addresses in the chat.') }}</p>
        @endif
    </section>
</main>
