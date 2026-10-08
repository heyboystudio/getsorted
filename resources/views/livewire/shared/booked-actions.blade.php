{{--
    "Mark as done" and "Cancel this booking" for a booked job (spec 024), shared by the client and pro workspaces.
    Both components expose the same properties and methods, so one partial serves both.
    Needs: $job, $audience ('client'|'pro').
--}}
@if (\App\Domain\ServiceJobs\Support\BookedJob::isOpen($job))
    <flux:card class="space-y-4" aria-label="{{ __('Finish or cancel this booking') }}">
        @error('finish') <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" /> @enderror

        @if ($confirmingDone)
            <flux:callout variant="success" icon="check-circle" role="alertdialog" aria-labelledby="done-title">
                <flux:callout.heading id="done-title">{{ __('Is the work finished?') }}</flux:callout.heading>
                <flux:callout.text>{{ $audience === 'client' ? __('Your pro will be told. Make sure you are happy with the work and have a receipt.') : __('The client will be told.') }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button variant="primary" wire:click="markDone" wire:loading.attr="disabled">{{ __('Yes, it is done') }}</flux:button>
                    <flux:button wire:click="keepBooking">{{ __('Not yet') }}</flux:button>
                </x-slot>
            </flux:callout>
        @elseif ($confirmingBookedCancel)
            <flux:callout variant="danger" icon="exclamation-triangle" role="alertdialog" aria-labelledby="booked-cancel-title">
                <flux:callout.heading id="booked-cancel-title">{{ __('Cancel this booking?') }}</flux:callout.heading>
                <flux:callout.text>{{ $audience === 'client' ? __('Your pro will be told. You can post the job again and choose someone else.') : __('The client will be told and can choose another pro. Please only cancel if you really cannot do the job.') }}</flux:callout.text>
                <div class="mt-3">
                    <flux:field>
                        <flux:label for="bookedCancelReason">{{ __('Why are you cancelling?') }}</flux:label>
                        <flux:input id="bookedCancelReason" wire:model="bookedCancelReason" maxlength="300" />
                        <flux:error name="bookedCancelReason" />
                    </flux:field>
                </div>
                <x-slot name="actions">
                    <flux:button variant="danger" wire:click="cancelBooked" wire:loading.attr="disabled">{{ __('Yes, cancel booking') }}</flux:button>
                    <flux:button wire:click="keepBooking">{{ __('Keep booking') }}</flux:button>
                </x-slot>
            </flux:callout>
        @else
            <flux:button variant="primary" class="w-full" wire:click="confirmDone">{{ __('Mark as done') }}</flux:button>
            <flux:button variant="subtle" size="sm" class="!text-red-700" wire:click="confirmBookedCancel">{{ __('Cancel this booking') }}</flux:button>
        @endif
    </flux:card>
@endif
