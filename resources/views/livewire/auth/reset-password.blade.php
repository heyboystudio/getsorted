@include('livewire.auth.partials.shell-start')
        <span class="auth-kicker"><i class="ph-bold ph-key" aria-hidden="true"></i>{{ __('Password help') }}</span>
        <h1 class="auth-title">{{ __('Choose a new password') }}</h1>
        <form wire:submit="save" class="auth-form" novalidate>
            @include('livewire.auth.partials.field', ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'autocomplete' => 'email'])
            @include('livewire.auth.partials.field', ['name' => 'password', 'label' => __('New password'), 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => __('At least 10 characters.')])
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-lime auth-submit">
                <span>{{ __('Save password') }}</span>
                <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            </button>
        </form>
    </section>
</main>
