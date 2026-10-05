{{-- Spec 018, AC15: a read-only chat transcript for admins. Text is escaped. --}}
<div class="space-y-3 text-sm">
    @forelse ($messages as $message)
        <div @class(['rounded-lg p-3', 'bg-gray-50 dark:bg-white/5' => $message->reported_at === null, 'bg-danger-50 dark:bg-danger-500/10' => $message->reported_at !== null])>
            <p class="text-xs text-gray-500">
                {{ $message->sender_type === \App\Domain\ServiceJobs\Enums\MessageSender::Customer ? __('Customer') : __('Pro') }} · {{ $message->created_at->format('j M H:i') }}
                @if ($message->reported_at) · <strong>{{ __('Reported: :reason', ['reason' => $message->report_reason?->label()]) }}</strong>@endif
            </p>
            <p class="mt-1 whitespace-pre-line">{{ $message->deleted_at ? __('Message deleted') : $message->body }}</p>
            @if (! $message->deleted_at)
                @foreach ($photoUrls($message) as $url)
                    <img src="{{ $url }}" alt="" class="mt-2 inline-block h-24 rounded object-cover">
                @endforeach
            @endif
        </div>
    @empty
        <p>{{ __('No messages.') }}</p>
    @endforelse
</div>
