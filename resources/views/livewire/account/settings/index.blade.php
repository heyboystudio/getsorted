<main class="flex items-start justify-center px-5 py-8">
    <section class="w-full max-w-xl">
        <div class="flex items-center gap-4">
            <span aria-hidden="true" class="flex size-14 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xl font-semibold text-emerald-900">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-semibold tracking-tight">{{ $user->fullName() }}</h1>
                <p class="truncate text-sm text-zinc-600">{{ $user->email }}</p>
            </div>
        </div>

        <ul class="mt-8 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white">
            @foreach ([
                ['account.profile', __('Profile'), __('Name, email and mobile number')],
                ['properties.index', __('Properties'), __('Addresses you book for')],
                ['account.notifications', __('Notifications'), __('Which messages you get, and where')],
                ['account.privacy', __('Privacy and data'), __('Download or delete your data')],
                ['contact', __('Help'), __('Talk to the GetSorted team')],
            ] as [$route, $title, $hint])
                <li>
                    <a wire:navigate.hover href="{{ route($route) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-zinc-50">
                        <span><span class="block font-medium">{{ $title }}</span><span class="block text-sm text-zinc-600">{{ $hint }}</span></span>
                        <span aria-hidden="true" class="text-zinc-400">→</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</main>
