<x-filament-panels::page>
    <p class="text-sm text-gray-600">Assistant calls over the last 30 days, by Durban day. Customer text is never stored here.</p>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50"><tr><th class="px-4 py-3">Day</th><th class="px-4 py-3">Purpose</th><th class="px-4 py-3">Outcome</th><th class="px-4 py-3">Calls</th><th class="px-4 py-3">Avg latency</th><th class="px-4 py-3">Tokens in / out</th></tr></thead>
            <tbody>
                @forelse ($this->usage() as $row)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3">{{ $row->day }}</td>
                        <td class="px-4 py-3">{{ \App\Domain\Assistant\Enums\AiPurpose::from($row->purpose)->label() }}</td>
                        <td class="px-4 py-3">{{ \App\Domain\Assistant\Enums\AiOutcome::from($row->outcome)->label() }}</td>
                        <td class="px-4 py-3">{{ number_format($row->calls) }}</td>
                        <td class="px-4 py-3">{{ number_format($row->avg_latency_ms) }} ms</td>
                        <td class="px-4 py-3">{{ number_format($row->input_tokens) }} / {{ number_format($row->output_tokens) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No assistant calls yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
