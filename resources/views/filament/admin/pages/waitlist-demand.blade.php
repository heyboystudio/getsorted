<x-filament-panels::page>
    <p class="text-sm text-gray-600">Requests by area and trade. Contact details are held privately for a later follow-up workflow.</p>
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50"><tr><th class="px-4 py-3">Area</th><th class="px-4 py-3">Trade</th><th class="px-4 py-3">Requests</th></tr></thead>
            <tbody>
                @forelse ($this->demand() as $row)
                    <tr class="border-t border-gray-100"><td class="px-4 py-3">{{ $row->area_name }}</td><td class="px-4 py-3">{{ $row->trade_name }}</td><td class="px-4 py-3">{{ $row->total }}</td></tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No waitlist requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
