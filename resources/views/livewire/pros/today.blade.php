<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">{{ __('Today') }}</p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ __('Hi :name', ['name' => $firstName]) }} <span aria-hidden="true">👋</span></h1>

        <x-push-card class="mt-4" />

        <div @class(['mt-5 flex items-center justify-between gap-4 rounded-xl border p-4', 'border-amber-300 bg-amber-50' => $paused, 'border-zinc-200 bg-white' => ! $paused])>
            <div>
                <p class="font-medium">{{ $paused ? __("You're paused") : __("You're available") }}</p>
                <p class="text-sm text-zinc-600">
                    {{ $paused ? __('You will not get new invites. Jobs you already have carry on.') : __('You get invites for jobs that fit.') }}
                </p>
            </div>
            <button type="button" wire:click="setPaused({{ $paused ? 'false' : 'true' }})" @class(['shrink-0 rounded-lg px-4 py-2 text-sm font-medium', 'bg-emerald-700 text-white hover:bg-emerald-800' => $paused, 'border border-zinc-300 hover:bg-zinc-50' => ! $paused])>
                {{ $paused ? __('Resume') : __('Pause') }}
            </button>
        </div>

        @foreach ($expiring as $document)
            <a wire:navigate.hover href="{{ route('pros.profile') }}" wire:key="expiring-{{ $document->public_id }}" class="mt-4 block rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 hover:border-amber-500">
                <span class="font-medium">{{ $document->isExpired() ? __(':type has expired', ['type' => $document->type->label()]) : __(':type expires on :date', ['type' => $document->type->label(), 'date' => $document->expires_at->translatedFormat('j M Y')]) }}</span>
                <span class="block">{{ $document->isExpired() ? __('Your "Registration verified" badge is off until it is renewed.') : __('Renew it to keep your "Registration verified" badge.') }}</span>
            </a>
        @endforeach

        @if ($missingBio)
            <a wire:navigate.hover href="{{ route('pros.profile') }}" class="mt-4 block rounded-xl border border-zinc-200 bg-white p-4 text-sm hover:border-emerald-700">
                <span class="font-medium">{{ __('Finish your profile') }}</span>
                <span class="block text-zinc-600">{{ __('Add a short line about your business. Customers read it when they compare quotes.') }}</span>
            </a>
        @endif

        @if ($invites->isNotEmpty())
            <section class="mt-8" aria-labelledby="today-invites">
                <div class="flex items-baseline justify-between">
                    <h2 id="today-invites" class="text-lg font-semibold">{{ __('Answer these first') }}</h2>
                    @if ($invitesTotal > $invites->count())<a wire:navigate.hover href="{{ route('pros.jobs') }}" class="text-sm text-emerald-800 underline underline-offset-4">{{ __('All :count', ['count' => $invitesTotal]) }}</a>@endif
                </div>
                <ul class="mt-3 space-y-3">
                    @foreach ($invites as $row)
                        <li wire:key="invite-{{ $row['invite']->public_id }}">
                            <a wire:navigate.hover href="{{ route('pros.jobs.show', $row['invite']) }}" class="block rounded-xl border border-emerald-200 bg-white p-4 hover:border-emerald-700">
                                <span class="flex items-start justify-between gap-3">
                                    <span class="font-medium">{{ \App\Domain\ServiceJobs\Support\JobLabel::for($row['job']) }}</span>
                                    @if ($row['job']->urgency === \App\Domain\ServiceJobs\Enums\Urgency::Urgent)<span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Urgent') }}</span>@endif
                                </span>
                                <span class="mt-1 block text-sm text-zinc-600">{{ \App\Domain\ServiceJobs\Support\JobLabel::area($row['job']) }} · {{ $row['job']->time_window?->label() }}</span>
                                <span class="mt-2 block text-xs font-medium text-emerald-900">{{ __(':time left to reply', ['time' => $row['invite']->expires_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($booked->isNotEmpty())
            <section class="mt-8" aria-labelledby="today-booked">
                <h2 id="today-booked" class="text-lg font-semibold">{{ __('Coming up') }}</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($booked as $row)
                        <li wire:key="booked-{{ $row['invite']->public_id }}" class="rounded-xl border border-zinc-200 bg-white p-4">
                            <a wire:navigate.hover href="{{ route('pros.jobs.show', $row['invite']) }}" class="block hover:underline">
                                <span class="flex items-start justify-between gap-3">
                                    <span class="font-medium">{{ \App\Domain\ServiceJobs\Support\JobLabel::for($row['job']) }}</span>
                                    @if ($row['job']->scheduled_for)<span class="shrink-0 text-sm text-zinc-600">{{ $row['job']->scheduled_for->translatedFormat('D j M') }}</span>@endif
                                </span>
                            </a>
                            @if ($row['contact'])
                                <dl class="mt-2 text-sm text-zinc-700">
                                    <div>{{ $row['contact']['name'] }} · <a href="tel:{{ $row['contact']['phone'] }}" class="text-emerald-800 underline underline-offset-4">{{ $row['contact']['phone'] }}</a></div>
                                    @if ($row['contact']['address'])<div>{{ $row['contact']['address'] }}, {{ \App\Domain\ServiceJobs\Support\JobLabel::area($row['job']) }}</div>@endif
                                </dl>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($quoted->isNotEmpty())
            <section class="mt-8" aria-labelledby="today-quoted">
                <h2 id="today-quoted" class="text-lg font-semibold">{{ __('Waiting for the customer') }}</h2>
                <ul class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
                    @foreach ($quoted as $row)
                        <li wire:key="quoted-{{ $row['invite']->public_id }}">
                            <a wire:navigate.hover href="{{ route('pros.jobs.show', $row['invite']) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-zinc-50">
                                <span><span class="block font-medium">{{ \App\Domain\ServiceJobs\Support\JobLabel::for($row['job']) }}</span><span class="block text-zinc-600">{{ \App\Domain\ServiceJobs\Support\JobLabel::area($row['job']) }}</span></span>
                                <span class="shrink-0 text-zinc-700">R {{ number_format($row['quote']->total_cents / 100, 2, '.', ' ') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($invites->isEmpty() && $booked->isEmpty() && $quoted->isEmpty())
            <p class="mt-8 rounded-xl border border-dashed border-zinc-300 p-6 text-center text-zinc-600">{{ $paused ? __('Nothing needs you. Resume when you want new invites.') : __("No new jobs right now. We'll email you when one fits.") }}</p>
        @endif

        <a wire:navigate.hover href="{{ route('pros.jobs') }}" class="mt-8 inline-block w-full rounded-lg bg-emerald-700 px-4 py-3 text-center text-lg font-medium text-white hover:bg-emerald-800">{{ __('Your jobs') }}</a>
    </section>
</main>
