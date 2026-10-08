@include('livewire.auth.partials.shell-start')
        @unless ($changing)
            <p class="auth-step"><b>2 / 2</b>{{ __('Last step') }}</p>
        @endunless

            <h1 class="auth-title">{{ $changing ? __('Change your mobile') : __('Add your mobile number') }}</h1>
            <p class="auth-sub">{{ __('Pros and GetSorted use this number to reach you about your jobs.') }}</p>

            <form wire:submit="save" class="auth-form" novalidate>
                <div class="auth-field">
                    <label for="phone">{{ __('Mobile number') }}</label>
                    <input id="phone" type="tel" inputmode="tel" autocomplete="tel" wire:model="phone" placeholder="082 123 4567"
                        @class(['auth-input', 'has-error' => $errors->has('phone')])
                        aria-describedby="phone-error" @error('phone') aria-invalid="true" @enderror>
                    @error('phone') <p id="phone-error" class="auth-err" role="alert">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-lime auth-submit">
                    <span wire:loading.remove wire:target="save">{{ __('Save number') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                    <span class="sq"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                </button>
            </form>

        @unless ($changing)
            <form method="POST" action="{{ route('logout') }}" class="auth-foot">
                @csrf
                <button type="submit" class="auth-text-btn">{{ __('Sign out') }}</button>
            </form>
        @endunless
    </section>
</main>
