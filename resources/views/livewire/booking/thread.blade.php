{{-- Spec 017: booking in one Siya thread. Every message is escaped text; nothing is rendered as HTML. --}}
@php($chip = 'rounded-full border border-zinc-300 bg-white px-4 py-2 text-sm hover:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 disabled:opacity-60')
@php($primary = 'w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60')
@php($tapped = 'answer,pickTrade,pickService,describeOther,send,confirmService,rejectService,addDetails,continueDetails,selectProperty,chooseWhen,finishPhotos,confirmBooking,joinWaitlist,noThanks,differentService,skipQuestion')
<main class="flex min-h-dvh justify-center" x-data="{ pending: '' }">
    <section class="flex w-full max-w-2xl flex-col px-4">
        <header class="sticky top-0 z-10 -mx-4 border-b border-zinc-200 bg-stone-50/95 px-4 pb-3 pt-4 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <a href="{{ auth()->check() ? route('account.home') : route('home') }}" wire:navigate class="text-zinc-500" aria-label="{{ __('Back') }}">←</a>
                    <span class="flex size-10 items-center justify-center rounded-full bg-emerald-700 font-semibold text-white" aria-hidden="true">S</span>
                    <div>
                        <h1 class="font-semibold leading-tight">{{ __('Siya') }}</h1>
                        <p class="text-xs text-zinc-500">{{ __('Sortd’s AI assistant · can make mistakes') }}</p>
                    </div>
                </div>
                <button type="button" wire:click="restart" wire:confirm="{{ __('Start over? This clears the chat.') }}" class="text-sm text-zinc-600 underline underline-offset-4">{{ __('Restart') }}</button>
            </div>

            <ol class="mt-3 grid grid-cols-4 gap-2" aria-label="{{ __('Booking progress') }}">
                @foreach (\App\Livewire\Booking\Thread::PROGRESS as $key => $label)
                    @php($reached = array_search($key, array_keys(\App\Livewire\Booking\Thread::PROGRESS), true) <= array_search($progress, array_keys(\App\Livewire\Booking\Thread::PROGRESS), true))
                    <li @if ($key === $progress) aria-current="step" @endif>
                        <span @class(['block h-1.5 rounded-full', 'bg-emerald-700' => $reached, 'bg-zinc-200' => ! $reached])></span>
                        <span @class(['mt-1 block text-xs', 'font-medium text-emerald-800' => $key === $progress, 'text-zinc-500' => $key !== $progress])>{{ __($label) }}</span>
                    </li>
                @endforeach
            </ol>
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
                    @elseif ($kind === 'suggest')
                        <p class="max-w-[85%] rounded-2xl bg-white px-4 py-2 text-[15px] shadow-sm ring-1 ring-zinc-200">{{ __('Sounds like') }} <strong>{{ $item['text'] }}</strong> ({{ $item['label'] ?? '' }}). {{ __('Is that right?') }}</p>
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

        {{-- The current card --}}
        <div class="pb-4" wire:loading.class="opacity-60" wire:target="{{ $tapped }}">
            @if (in_array($stage, ['trade', 'describe'], true) && $trades->isNotEmpty())
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($trades as $tradeOption)
                        <button type="button" wire:key="trade-{{ $tradeOption->key }}" wire:click="pickTrade(@js($tradeOption->key))" x-on:click="pending = @js($tradeOption->name)"
                            class="rounded-xl border border-zinc-200 bg-white px-4 py-4 text-left font-medium hover:border-emerald-700">{{ $tradeOption->name }} <span class="float-right text-zinc-400" aria-hidden="true">›</span></button>
                    @endforeach
                </div>
            @elseif ($stage === 'service')
                <div class="flex flex-wrap gap-2">
                    @foreach ($tradeServices as $option)
                        <button type="button" wire:key="service-{{ $option->key }}" wire:click="pickService(@js($option->key))" x-on:click="pending = @js($option->name)" class="{{ $chip }}">{{ $option->name }}</button>
                    @endforeach
                    <button type="button" wire:click="describeOther" x-on:click="pending = @js(__('Something else'))" class="{{ $chip }}">{{ __('Other') }}</button>
                </div>
            @elseif ($stage === 'suggested')
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="confirmService" x-on:click="pending = @js(__('Yes'))" class="rounded-full bg-emerald-700 px-5 py-2 text-sm font-medium text-white">{{ __('Yes') }}</button>
                    <button type="button" wire:click="rejectService" x-on:click="pending = @js(__('Something else'))" class="{{ $chip }}">{{ __('Something else') }}</button>
                </div>
            @elseif ($stage === 'questions' && $question)
                <div wire:key="q-{{ $question->key }}">
                    @if (in_array($question->type->value, ['single_choice', 'yes_no'], true))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($question->type->value === 'yes_no' ? ['yes' => __('Yes'), 'no' => __('No')] : array_combine($question->options, $question->options) as $value => $label)
                                <button type="button" wire:key="chip-{{ $question->key }}-{{ $loop->index }}" wire:click="answer(@js($question->key), @js($value))" x-on:click="pending = @js($label)" class="{{ $chip }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    @elseif ($question->type->value === 'multi_choice')
                        <div x-data="{ picked: [] }">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($question->options as $option)
                                    <button type="button" wire:key="multi-{{ $question->key }}-{{ $loop->index }}" x-on:click="picked.includes(@js($option)) ? picked = picked.filter(o => o !== @js($option)) : picked.push(@js($option))"
                                        x-bind:aria-pressed="picked.includes(@js($option))" x-bind:class="picked.includes(@js($option)) && 'border-emerald-700 bg-emerald-50'" class="{{ $chip }}">{{ $option }}</button>
                                @endforeach
                            </div>
                            <button type="button" x-bind:disabled="picked.length === 0" x-on:click="pending = picked.join(', '); $wire.answer(@js($question->key), picked)" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{{ __('Done') }}</button>
                        </div>
                    @else
                        <form x-data="{ value: '' }" x-on:submit.prevent="pending = value; $wire.answer(@js($question->key), value)" class="flex gap-2">
                            <input x-model="value" @if ($question->type->value === 'number') type="number" inputmode="numeric" min="0" @else type="text" maxlength="500" @endif aria-label="{{ $question->prompt }}" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2">
                            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">{{ __('OK') }}</button>
                        </form>
                    @endif
                    @unless ($question->required)
                        <button type="button" wire:click="skipQuestion" x-on:click="pending = @js(__('Skip'))" class="mt-3 text-sm text-zinc-600 underline underline-offset-4">{{ __('Skip') }}</button>
                    @endunless
                    @error('answer') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                </div>
            @elseif ($stage === 'details')
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="continueDetails" x-on:click="pending = @js(__('Continue'))" class="rounded-full bg-emerald-700 px-5 py-2 text-sm font-medium text-white">{{ __('Continue') }}</button>
                    <button type="button" wire:click="addDetails" x-on:click="pending = @js(__('Add more details'))" class="{{ $chip }}">{{ __('Add more details') }}</button>
                </div>
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
                            <button type="button" wire:key="property-{{ $property->public_id }}" wire:click="selectProperty(@js($property->public_id))" x-on:click="pending = @js($property->label.', '.$property->suburb->name)"
                                class="block w-full rounded-xl border border-zinc-200 px-4 py-3 text-left hover:border-emerald-700">
                                <span class="block font-medium">{{ $property->label }}</span>
                                <span class="block text-sm text-zinc-600">{{ $property->street_address }}, {{ $property->suburb->name }}</span>
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
                    @if ($addressManual || $pickedPlaceId !== null)
                        <label for="new-street" class="mt-4 block text-sm font-medium">{{ __('Street address') }}</label>
                        <input id="new-street" type="text" wire:model="newStreet" maxlength="200" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3">
                        @error('newStreet') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                        <label for="new-suburb" class="mt-4 block text-sm font-medium">{{ __('Suburb') }}</label>
                        <input id="new-suburb" type="text" wire:model.live.debounce.300ms="newSuburbQuery" autocomplete="off" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3">
                        @if ($newSuburbSuggestions->isNotEmpty())
                            <div class="mt-1 space-y-1" role="listbox">
                                @foreach ($newSuburbSuggestions as $suggestion)
                                    <button type="button" wire:key="new-suburb-{{ $suggestion->slug }}" wire:click="selectNewSuburb(@js($suggestion->slug))" class="block w-full rounded-lg border border-zinc-200 px-3 py-2 text-left hover:border-emerald-700">{{ $suggestion->name }}</button>
                                @endforeach
                            </div>
                        @endif
                        @error('newSuburb') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
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
                    <button type="button" wire:click="differentService" x-on:click="pending = @js(__('Choose a different service'))" class="{{ $chip }}">{{ __('Choose a different service') }}</button>
                    <button type="button" wire:click="noThanks" x-on:click="pending = @js(__('No thanks'))" class="{{ $chip }}">{{ __('No thanks') }}</button>
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ __('“Keep me updated” lets Sortd contact you about this service in your suburb.') }} <a href="{{ route('privacy') }}" wire:navigate class="underline">{{ __('Privacy notice') }}</a></p>
                @error('waitlist') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @elseif ($stage === 'closed')
                <button type="button" wire:click="differentService" x-on:click="pending = @js(__('Choose a different service'))" class="{{ $chip }}">{{ __('Choose a different service') }}</button>
            @elseif ($stage === 'when')
                <div class="rounded-xl border border-zinc-200 bg-white p-4" x-data="{ date: $wire.entangle('preferredDate') }">
                    @if (in_array(\App\Domain\ServiceJobs\Enums\TimeWindow::Today, $windows, true))
                        <button type="button" wire:click="chooseWhen('today')" x-on:click="pending = @js(__('Urgent — today'))" class="mb-4 w-full rounded-lg border border-red-300 bg-red-50 px-4 py-3 font-medium text-red-900">{{ __('Urgent — today') }}</button>
                    @endif
                    <p class="text-sm font-medium">{{ __('Pick a day') }}</p>
                    <div class="mt-2 grid grid-cols-7 gap-1 text-center text-xs text-zinc-500" aria-hidden="true">
                        @foreach ([__('Su'), __('Mo'), __('Tu'), __('We'), __('Th'), __('Fr'), __('Sa')] as $weekday) <span>{{ $weekday }}</span> @endforeach
                    </div>
                    <div class="mt-1 grid grid-cols-7 gap-1" role="group" aria-label="{{ __('Pick a day') }}">
                        @foreach ($days as $day)
                            @if ($day['date'] === null)
                                <span></span>
                            @else
                                <button type="button" wire:key="day-{{ $day['date'] }}" x-on:click="date = @js($day['date'])" aria-label="{{ $day['label'] }}"
                                    x-bind:aria-pressed="date === @js($day['date'])" x-bind:class="date === @js($day['date']) ? 'bg-emerald-700 text-white' : 'hover:bg-emerald-50'"
                                    class="rounded-lg py-2 text-sm">{{ $day['day'] }}</button>
                            @endif
                        @endforeach
                    </div>
                    <p class="mt-4 text-sm font-medium">{{ __('What time suits you?') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($windows as $window)
                            @continue($window === \App\Domain\ServiceJobs\Enums\TimeWindow::Today)
                            <button type="button" wire:click="chooseWhen(@js($window->value))" x-bind:disabled="! date" x-on:click="pending = @js($window->label())" class="{{ $chip }}">{{ $window->label() }}</button>
                        @endforeach
                    </div>
                    @error('when') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
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
                    <label for="notes-draft" class="text-sm font-medium">{{ __('Notes for your pro') }}</label>
                    <textarea id="notes-draft" rows="4" maxlength="{{ config('sortd.jobs.notes_max_length') }}" wire:model="notesDraft" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2"></textarea>
                    @error('notesDraft') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button type="button" wire:click="saveNotes" class="mt-3 {{ $primary }}">{{ __('Save') }}</button>
                </div>
            @elseif ($stage === 'summary' && $summary)
                <div class="rounded-xl border border-zinc-200 bg-white">
                    <p class="px-4 pt-4 text-xs font-medium uppercase tracking-widest text-emerald-800">{{ __('Booking summary') }}</p>
                    <dl class="divide-y divide-zinc-100">
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('Need help with') }}</dt><button type="button" wire:click="change('service')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="font-medium">{{ $service?->name }} · {{ $service?->trade->name }} @if ($summary['urgent']) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span> @endif</dd>
                        </div>
                        @if ($summary['answers'] !== [])
                            <div class="p-4">
                                <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('What’s wrong') }}</dt><button type="button" wire:click="change('answers')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                                <dd class="mt-1 space-y-0.5 text-sm">
                                    @foreach ($summary['answers'] as $answer) <p><span class="text-zinc-500">{{ $answer['prompt'] }}</span> {{ $answer['answer'] }}</p> @endforeach
                                </dd>
                            </div>
                        @endif
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('Notes for your pro') }}</dt><button type="button" wire:click="change('notes')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="mt-1 whitespace-pre-line text-sm">{{ $notes !== '' ? $notes : __('None') }}</dd>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between"><dt class="text-xs text-zinc-500">{{ __('Address') }}</dt><button type="button" wire:click="change('where')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                            <dd class="mt-1 text-sm">@if ($summary['property']){{ $summary['property']->street_address }}, {{ $summary['property']->suburb->name }}@endif</dd>
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
                        <p class="mt-2 text-center text-xs text-zinc-500">{{ __('By confirming, you agree to the') }} <a href="{{ route('terms') }}" wire:navigate class="underline">{{ __('Terms') }}</a> {{ __('and allow us to share your job (without your street address) with up to 3 vetted pros.') }}</p>
                    </div>
                </div>
            @elseif ($stage === 'posted' && $jobPublicId)
                <a href="{{ route('jobs.show', $jobPublicId) }}" wire:navigate class="block text-center {{ $primary }}">{{ __('See your job') }}</a>
            @endif
            <div wire:key="end-{{ count($messages) }}-{{ $stage }}" x-init="$nextTick(() => $el.scrollIntoView({ block: 'end' }))"></div>
        </div>

        @if (! in_array($stage, ['posted', 'closed', 'summary', 'add_property', 'notes'], true))
            <form wire:submit="send" x-on:submit="pending = $wire.message" class="sticky bottom-0 -mx-4 flex gap-2 border-t border-zinc-200 bg-stone-50 px-4 py-3">
                <label for="siya-message" class="sr-only">{{ __('Message Siya') }}</label>
                <input id="siya-message" type="text" wire:model="message" maxlength="1000" autocomplete="off" @disabled($limitReached)
                    placeholder="{{ $available || ! in_array($stage, ['trade', 'service', 'describe', 'suggested', 'questions'], true) ? __('Type here…') : __('Tap an option to continue') }}"
                    class="block w-full rounded-full border border-zinc-300 bg-white px-4 py-3 outline-none focus:ring-2 focus:ring-emerald-600">
                <button type="submit" wire:loading.attr="disabled" wire:target="send" @disabled($limitReached) class="rounded-full bg-emerald-700 px-5 py-3 font-medium text-white disabled:opacity-60" aria-label="{{ __('Send') }}">↑</button>
            </form>
            @error('message') <p class="pb-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <p class="pb-3 text-center text-xs text-zinc-500">{{ __('Don’t share phone numbers or addresses in the chat.') }}</p>
        @endif
    </section>
</main>
