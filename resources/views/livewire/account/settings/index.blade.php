<x-workspace.account-shell current="account.settings">
    <div class="flex items-center gap-4">
        <span aria-hidden="true" class="flex size-14 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-xl font-semibold text-white">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</span>
        <div class="min-w-0">
            <flux:heading size="xl" level="1" class="truncate">{{ $user->fullName() }}</flux:heading>
            <p class="truncate text-sm text-zinc-600">{{ $user->email }}</p>
        </div>
    </div>

    <ul class="mt-8 grid gap-3 sm:grid-cols-2">
        @foreach ([
            ['account.profile', __('Profile'), __('Name, email and mobile number'), 'user'],
            ['properties.index', __('Properties'), __('Addresses you book for'), 'map-pin'],
            ['account.notifications', __('Notifications'), __('Which messages you get, and where'), 'bell'],
            ['account.privacy', __('Privacy and data'), __('Download or delete your data'), 'shield-check'],
            ['contact', __('Help'), __('Talk to the GetSorted team'), 'lifebuoy'],
        ] as [$route, $title, $hint, $icon])
            <li>
                <a wire:navigate.hover href="{{ route($route) }}" class="flex h-full items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-400 hover:shadow-xs">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-700"><flux:icon :name="$icon" class="size-5" /></span>
                    <span><span class="block font-medium">{{ $title }}</span><span class="block text-sm text-zinc-600">{{ $hint }}</span></span>
                </a>
            </li>
        @endforeach
    </ul>
</x-workspace.account-shell>
