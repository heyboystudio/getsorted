@include('livewire.auth.partials.shell-start')
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Choose a new password') }}</h1>
        <form wire:submit="save" class="mt-8 space-y-4" novalidate>
            @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email'])
            @include('livewire.auth.partials.field', ['name' => 'password', 'label' => __('New password'), 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => __('At least 10 characters.')])
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="flex w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-3 font-medium text-white hover:bg-emerald-800 disabled:opacity-60">{{ __('Save password') }}</button>
        </form>
    </section>
</main>
