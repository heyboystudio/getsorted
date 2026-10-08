<x-filament-widgets::widget>
    <x-filament::section :heading="__('Jobs that need a nudge')" :description="__('Open jobs with no estimate yet, and booked jobs nobody has marked done.')">
        @if ($rows === [])
            <p class="text-sm text-gray-500">{{ __('Nothing is stuck right now.') }}</p>
        @else
            <ul class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                @foreach ($rows as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span><a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:underline">{{ $row['trade'] }}</a> · {{ $row['area'] ?? '—' }}</span>
                        <span class="text-gray-600 dark:text-gray-300">{{ $row['label'] }} · {{ $row['since'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
