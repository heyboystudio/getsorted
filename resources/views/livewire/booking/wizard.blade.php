@php($input = $question ? \App\Livewire\Booking\Wizard::inputFor($question) : null)
<main class="flex min-h-dvh items-start justify-center px-5 py-10">
    <section class="w-full max-w-md">
        <div class="mb-6 flex items-center justify-between text-sm">
            @if ($step === 'coverage')
                <a href="{{ route('trades.show', $service->trade) }}" class="text-zinc-600 underline underline-offset-4">← {{ $service->trade->name }}</a>
            @else
                <button type="button" wire:click="back" class="text-zinc-600 underline underline-offset-4">← {{ __('Back') }}</button>
            @endif
            <span class="text-zinc-500">{{ __('Step :n of :total', ['n' => $stepNumber, 'total' => $stepTotal]) }}</span>
        </div>
        <div class="mb-8 h-1.5 w-full overflow-hidden rounded-full bg-zinc-200" aria-hidden="true">
            <div class="h-full bg-emerald-700" style="width: {{ (int) round($stepNumber / max(1, $stepTotal) * 100) }}%"></div>
        </div>

        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">{{ $service->name }}</p>

        @error('post') <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">{{ $message }}</p> @enderror

        @if ($step === 'coverage')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Where do you need help?') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Check whether pros cover this service in your suburb.') }}</p>
            <label for="coverage-suburb" class="mt-6 block text-sm font-medium">{{ __('Suburb') }}</label>
            <input id="coverage-suburb" type="text" wire:model.live.debounce.300ms="suburbQuery" autocomplete="off" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3">
            @if ($suburbSuggestions->isNotEmpty())
                <div class="mt-2 space-y-1" role="listbox">
                    @foreach ($suburbSuggestions as $suggestion)
                        <button type="button" wire:key="coverage-{{ $suggestion->slug }}" wire:click="selectSuburb('{{ $suggestion->slug }}')" class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-3 text-left hover:border-emerald-700">{{ $suggestion->name }} @unless ($suggestion->is_active) · {{ __('Coming soon') }} @endunless</button>
                    @endforeach
                </div>
            @endif
            @error('suburbQuery') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="next" wire:loading.attr="disabled" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Check availability') }}</button>
            <p wire:loading wire:target="next" class="mt-2 text-sm text-zinc-500">{{ __('Checking coverage…') }}</p>
        @elseif ($step === 'waitlist')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('We’re not available there for this service yet') }}</h1>
            <p class="mt-2 text-sm text-zinc-600">{{ __('Leave your details and we can contact you when availability changes. We cannot promise a date.') }}</p>
            <label for="waitlist-name" class="mt-6 block text-sm font-medium">{{ __('First name') }}</label>
            <input id="waitlist-name" type="text" wire:model="waitlistFirstName" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3">
            @error('firstName') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <label for="waitlist-phone" class="mt-4 block text-sm font-medium">{{ __('Mobile number') }}</label>
            <input id="waitlist-phone" type="tel" wire:model="waitlistPhone" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3">
            @error('phone') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <label class="mt-5 flex items-start gap-3 text-sm"><input type="checkbox" wire:model="waitlistConsent" class="mt-1 size-4 rounded"> <span>{{ __('I agree that Sortd may contact me about availability for this service and suburb.') }} <a href="{{ route('privacy') }}" class="text-emerald-800 underline">{{ __('Privacy notice') }}</a></span></label>
            @error('consent') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @error('waitlist') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="joinWaitlist" wire:loading.attr="disabled" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white disabled:opacity-60">{{ __('Join waitlist') }}</button>
        @elseif ($step === 'waitlist_done')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('You’re on the waitlist') }}</h1>
            <p class="mt-2 text-zinc-600">{{ __('We’ll contact you if availability changes. No job has been posted.') }}</p>
        @elseif ($step === 'questions' && $question)
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $question->prompt }}</h1>
            @unless ($question->required) <p class="mt-1 text-sm text-zinc-500">{{ __('Optional') }}</p> @endunless

            <div class="mt-6 space-y-3" wire:key="q-{{ $question->key }}">
                @if ($input === 'tap')
                    @foreach ($question->type === \App\Domain\Catalogue\Enums\QuestionType::YesNo ? ['yes' => __('Yes'), 'no' => __('No')] : array_combine($question->options, $question->options) as $value => $label)
                        <button type="button" wire:click="choose(@js($value))" wire:loading.attr="disabled" aria-pressed="{{ ($answers[$question->key] ?? null) === $value ? 'true' : 'false' }}"
                            @class(['block w-full rounded-xl border px-4 py-4 text-left text-lg', 'border-emerald-700 bg-emerald-50' => ($answers[$question->key] ?? null) === $value, 'border-zinc-300 bg-white hover:border-emerald-700' => ($answers[$question->key] ?? null) !== $value])>{{ $label }}</button>
                    @endforeach
                    @unless ($question->required)
                        <button type="button" wire:click="next" class="mt-2 text-sm text-zinc-600 underline underline-offset-4">{{ __('Skip') }}</button>
                    @endunless
                @elseif ($input === 'multi')
                    @foreach ($question->options as $option)
                        <label class="flex items-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 py-4 text-lg has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                            <input type="checkbox" wire:model="answers.{{ $question->key }}" value="{{ $option }}" class="size-5 rounded text-emerald-700"> {{ $option }}
                        </label>
                    @endforeach
                @elseif ($input === 'number')
                    <input type="number" inputmode="numeric" min="0" wire:model="answers.{{ $question->key }}" aria-label="{{ $question->prompt }}" class="block w-40 rounded-lg border border-zinc-300 bg-white px-3 py-3 text-lg">
                @else
                    <textarea rows="3" maxlength="500" wire:model="answers.{{ $question->key }}" aria-label="{{ $question->prompt }}" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3"></textarea>
                @endif
            </div>

            @error('answer') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror

            @if ($urgentAdvice && $service->safety_advice !== [])
                <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900" role="note">
                    <p class="font-medium">{{ __('This sounds urgent. While you wait:') }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach ($service->safety_advice as $line) <li>{{ $line }}</li> @endforeach
                    </ul>
                </div>
            @endif

            @if ($input !== 'tap' || $urgentAdvice)
                <button type="button" wire:click="next" wire:loading.attr="disabled" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Next') }}</button>
            @endif
        @elseif ($step === 'notes')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Anything else pros should know?') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Optional. Please don’t include phone numbers or your address here.') }}</p>
            <textarea rows="5" maxlength="{{ config('sortd.jobs.notes_max_length') }}" wire:model="notes" aria-label="{{ __('Notes for your pro') }}" class="mt-6 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3"></textarea>
            @error('notes') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            <button type="button" wire:click="next" wire:loading.attr="disabled" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Next') }}</button>
        @elseif ($step === 'photos')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Add photos of the problem') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('Optional. Up to 5 photos, 10 MB each. JPEG, PNG, WebP or HEIC.') }}</p>
            @if ($isGuest)
                <p class="mt-5 text-zinc-600">{{ __('Log in to add photos. Your answers will be kept.') }}</p>
                <button type="button" wire:click="logInToContinue" class="mt-4 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white">{{ __('Log in to add photos') }}</button>
            @elseif ($isCustomer)
                @if ($photos->isNotEmpty())
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        @foreach ($photos as $photo)
                            <div wire:key="photo-{{ $photo->uuid }}" class="overflow-hidden rounded-xl border border-zinc-200">
                                <img src="{{ $photoUrls[$photo->uuid] }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full object-cover">
                                <button type="button" wire:click="removePhoto('{{ $photo->uuid }}')" class="w-full px-3 py-2 text-sm text-red-700 underline">{{ __('Remove photo') }}</button>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($photos->count() < config('sortd.job_photos.max_count'))
                    <label for="job-photo" class="mt-5 block text-sm font-medium">{{ __('Choose a photo') }}</label>
                    <input id="job-photo" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" wire:model="photoUpload" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3 text-sm">
                    @error('photoUpload') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    <button type="button" wire:click="addPhoto" wire:loading.attr="disabled" wire:target="photoUpload,addPhoto" class="mt-3 rounded-lg border border-emerald-700 px-4 py-2 font-medium text-emerald-800 disabled:opacity-60">{{ __('Add photo') }}</button>
                    <p wire:loading wire:target="photoUpload,addPhoto" class="mt-2 text-sm text-zinc-500">{{ __('Uploading photo…') }}</p>
                @endif
            @endif
            <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="photoUpload,addPhoto,next" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Next') }}</button>
        @elseif ($step === 'property')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Where is the work?') }}</h1>
            @if ($isGuest)
                <p class="mt-3 text-zinc-600">{{ __('Log in or sign up with your phone number to choose an address. Your answers are kept.') }}</p>
                <button type="button" wire:click="logInToContinue" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800">{{ __('Log in to continue') }}</button>
            @elseif (! $isCustomer)
                <p class="mt-3 text-zinc-600">{{ __('Bookings need a customer account.') }}</p>
            @else
                <p class="mt-1 text-sm text-zinc-500">{{ __('We only share your street address with the pro you choose.') }}</p>
                <div class="mt-6 space-y-3">
                    @foreach ($properties as $property)
                        <button type="button" wire:click="selectProperty('{{ $property->public_id }}')" aria-pressed="{{ $propertyPublicId === $property->public_id ? 'true' : 'false' }}"
                            @class(['block w-full rounded-xl border px-4 py-4 text-left', 'border-emerald-700 bg-emerald-50' => $propertyPublicId === $property->public_id, 'border-zinc-300 bg-white hover:border-emerald-700' => $propertyPublicId !== $property->public_id])>
                            <span class="block font-medium">{{ $property->label }}</span>
                            <span class="block text-sm text-zinc-600">{{ $property->street_address }}, {{ $property->suburb->name }}</span>
                            @unless ($property->suburb->is_active) <span class="mt-1 block text-xs text-amber-800">{{ __('Coming soon') }}</span> @endunless
                        </button>
                    @endforeach
                    <a href="{{ route('properties.create', ['return' => $bookingUrl]) }}" class="block rounded-xl border border-dashed border-zinc-300 px-4 py-4 text-center text-emerald-800">+ {{ __('Add a property') }}</a>
                </div>
                @error('property') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                @error('suburbQuery') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                @if ($pendingPropertySuburb && $selectedProperty)
                    <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4">
                        <p class="text-sm text-amber-950">{{ __('This property is in :suburb. Check coverage there instead?', ['suburb' => $selectedProperty->suburb->name]) }}</p>
                        <button type="button" wire:click="confirmPropertySuburb" class="mt-3 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">{{ __('Check this suburb') }}</button>
                    </div>
                @endif
                <button type="button" wire:click="next" wire:loading.attr="disabled" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Next') }}</button>
            @endif
        @elseif ($step === 'when')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('When do you need it?') }}</h1>
            <fieldset class="mt-6 space-y-3">
                <legend class="sr-only">{{ __('Time') }}</legend>
                @foreach ($windows as $window)
                    <label class="flex items-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 py-4 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                        <input type="radio" wire:model.live="timeWindow" value="{{ $window->value }}" class="size-5 text-emerald-700"> {{ $window->label() }}
                    </label>
                @endforeach
            </fieldset>
            @error('timeWindow') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @if ($timeWindow !== 'today')
                <label for="preferredDate" class="mt-6 block text-sm font-medium">{{ __('Preferred day') }}</label>
                <input id="preferredDate" type="date" wire:model="preferredDate" min="{{ $minDate }}" max="{{ $maxDate }}" class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-3">
                @error('preferredDate') <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            @endif
            <button type="button" wire:click="next" wire:loading.attr="disabled" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Next') }}</button>
        @elseif ($step === 'review')
            <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ __('Check and post') }}</h1>
            @if ($summary)
                <livewire:booking.job-summary-card :job-public-id="$jobPublicId" :key="'job-summary-'.$summary['key']" />
            @endif
            @if ($summary && $summary['guidance'])
                <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    @if ($summary['advice'] !== [])
                        <ul class="list-disc space-y-1 pl-5">@foreach ($summary['advice'] as $line) <li>{{ $line }}</li> @endforeach</ul>
                    @endif
                    <p class="mt-2">{{ __('This is guidance, not a guarantee.') }}</p>
                </div>
            @endif
            <dl class="mt-6 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white">
                <div class="p-4">
                    <div class="flex justify-between"><dt class="font-medium">{{ __('Your answers') }}</dt><button type="button" wire:click="change('questions')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                    <dd class="mt-2 space-y-1 text-sm text-zinc-700">
                        @foreach ($reviewAnswers as $answer)
                            <p><span class="text-zinc-500">{{ $answer['prompt'] }}</span> {{ is_array($answer['answer']) ? implode(', ', $answer['answer']) : ($answer['type'] === 'yes_no' ? __(ucfirst((string) $answer['answer'])) : $answer['answer']) }}</p>
                        @endforeach
                    </dd>
                </div>
                @if ($notes !== '')
                    <div class="p-4">
                        <div class="flex justify-between"><dt class="font-medium">{{ __('Notes') }}</dt><button type="button" wire:click="change('notes')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                        <dd class="mt-2 whitespace-pre-line text-sm text-zinc-700">{{ $notes }}</dd>
                    </div>
                @endif
                @if ($photos->isNotEmpty())
                    <div class="p-4">
                        <div class="flex justify-between"><dt class="font-medium">{{ __('Photos') }}</dt><button type="button" wire:click="change('photos')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                        <dd class="mt-3 grid grid-cols-3 gap-2">
                            @foreach ($photos as $photo)
                                <img wire:key="review-photo-{{ $photo->uuid }}" src="{{ $photoUrls[$photo->uuid] }}" alt="{{ __('Job photo :number', ['number' => $loop->iteration]) }}" class="aspect-square w-full rounded-lg object-cover">
                            @endforeach
                        </dd>
                    </div>
                @endif
                <div class="p-4">
                    <div class="flex justify-between"><dt class="font-medium">{{ __('Where') }}</dt><button type="button" wire:click="change('property')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                    <dd class="mt-2 text-sm text-zinc-700">@if ($selectedProperty){{ $selectedProperty->label }} — {{ $selectedProperty->street_address }}, {{ $selectedProperty->suburb->name }}@endif</dd>
                </div>
                <div class="p-4">
                    <div class="flex justify-between"><dt class="font-medium">{{ __('When') }}</dt><button type="button" wire:click="change('when')" class="text-sm text-emerald-800 underline">{{ __('Change') }}</button></div>
                    <dd class="mt-2 text-sm text-zinc-700">
                        {{ \App\Domain\ServiceJobs\Enums\TimeWindow::tryFrom($timeWindow)?->label() }}@if ($preferredDateLabel && $timeWindow !== 'today'), {{ $preferredDateLabel }}@endif
                        @if ($isUrgent) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span> @endif
                    </dd>
                </div>
            </dl>
            <button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-3 text-lg font-medium text-white hover:bg-emerald-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="post">{{ __('Post job') }}</span>
                <span wire:loading wire:target="post">{{ __('Posting…') }}</span>
            </button>
        @endif
    </section>
</main>
