{{--
    Signed-in shell (spec 021): header, and a tab bar that sits at the bottom on
    phones and becomes a side rail from `lg`. Pages pass `panel` (customer|pro)
    and optionally `focused` to hide the phone tab bar on a page that has its
    own bottom action bar.
--}}
@php
    $panel = $panel ?? \App\Support\PanelNavigation::CUSTOMER;
    $focused = $focused ?? false;
    $user = auth()->user();
    $items = \App\Support\PanelNavigation::items($panel, $user);
    $showTabs = \App\Support\PanelNavigation::showsTabs($user, $panel);
    $switch = \App\Support\PanelNavigation::switchTarget($user, $panel);
    $homeRoute = \App\Support\PanelNavigation::homeRoute($panel);
    $icons = [
        'home' => 'M2.25 12 12 3l9.75 9M4.5 9.75V20.25h5.25v-5.25h4.5v5.25h5.25V9.75',
        'briefcase' => 'M3.75 7.5h16.5v11.25H3.75zM8.25 7.5V5.25h7.5V7.5M3.75 12.75h16.5',
        'user' => 'M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm-7.5 8.25a7.5 7.5 0 0 1 15 0',
        'chat' => 'M2.25 12.76c0 1.6 1.12 2.98 2.7 3.2.9.12 1.8.22 2.7.28V21l3.8-3.8a48 48 0 0 0 5.1-.24c1.58-.22 2.7-1.6 2.7-3.2V6.74c0-1.6-1.12-2.98-2.7-3.2A48.4 48.4 0 0 0 12 3.25c-1.9 0-3.8.1-5.3.29-1.58.22-2.7 1.6-2.7 3.2z',
        'map-pin' => 'M12 21s-6.75-5.7-6.75-11.25a6.75 6.75 0 1 1 13.5 0C18.75 15.3 12 21 12 21Zm0-8.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z',
    ];
@endphp
<x-layouts.app :title="$title ?? null" :brand="__('GetSorted')" :hide-inbox-nav="true" :gs="true">
    <div @class(['lg:pl-56' => $showTabs, 'pb-24 lg:pb-0' => $showTabs && ! $focused, 'pb-8' => ! $showTabs || $focused])>
        <header class="gs-shell-head">
            <a wire:navigate.hover href="{{ route($homeRoute) }}" @class(['gs-logo', 'lg:hidden' => $showTabs]) aria-label="{{ __('GetSorted') }}"><img src="{{ asset('home/logo/getsorted-logo.svg') }}" alt="GetSorted" width="181" height="32">@if ($panel === 'pro')<span class="gs-pro-tag">{{ __('Pro') }}</span>@endif</a>
            <div class="gs-head-actions ml-auto">
                <span x-data="pushControl({ mode: 'silent' })" class="hidden" aria-hidden="true"></span>
                <span class="gs-bell"><livewire:notification-bell /></span>
                @if ($switch)
                    <a wire:navigate.hover href="{{ route($switch['route']) }}" class="gs-switch">{{ $switch['label'] }}</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="gs-logout">{{ __('Log out') }}</button>
                </form>
            </div>
        </header>

        {{ $slot }}
    </div>

    @if ($showTabs)
        <nav aria-label="{{ __('Main') }}" @class([
            'gs-rail fixed inset-x-0 bottom-0 z-5 border-t border-zinc-200 bg-white pb-[env(safe-area-inset-bottom)]',
            'lg:inset-y-0 lg:right-auto lg:block lg:w-56 lg:border-r lg:border-t-0 lg:px-3 lg:py-6',
            'hidden' => $focused,
        ])>
            <a wire:navigate.hover href="{{ route($homeRoute) }}" class="gs-logo mb-6 hidden px-3 lg:block" aria-label="{{ __('GetSorted') }}"><img src="{{ asset('home/logo/getsorted-logo.svg') }}" alt="GetSorted" width="181" height="32">@if ($panel === 'pro')<span class="gs-pro-tag">{{ __('Pro') }}</span>@endif</a>
            <ul class="flex lg:flex-col lg:gap-1">
                @foreach ($items as $item)
                    @php($active = request()->routeIs(...$item['active']))
                    <li class="flex-1 lg:flex-none">
                        <a wire:navigate.hover href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif @class([
                            'flex min-h-14 flex-col items-center justify-center gap-1 px-2 py-2 text-xs font-medium',
                            'lg:min-h-0 lg:flex-row lg:justify-start lg:gap-3 lg:rounded-lg lg:px-3 lg:py-2.5 lg:text-sm',
                            'text-emerald-800 lg:bg-emerald-50' => $active,
                            'text-zinc-600 hover:text-zinc-900 lg:hover:bg-zinc-100' => ! $active,
                        ])>
                            <svg aria-hidden="true" class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$item['icon']] }}"/></svg>
                            <span>{{ $item['label'] }}@if ($item['badge'] > 0)<span class="ml-1 gs-badge rounded-full bg-emerald-700 px-1.5 text-xs text-white"><span class="sr-only">{{ __('Unread:') }} </span>{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</x-layouts.app>
