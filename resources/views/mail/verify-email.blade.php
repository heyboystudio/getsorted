<x-mail::message>
# {{ __('Hi :name,', ['name' => $firstName]) }}

{{ __('Please confirm your email address to finish setting up your Get Sorted account.') }}

<x-mail::button :url="$link">
{{ __('Confirm my email') }}
</x-mail::button>

{{ __('This link works for 60 minutes. If you didn\'t create a Get Sorted account, you can ignore this email.') }}

{{ __('Get Sorted') }}
</x-mail::message>
