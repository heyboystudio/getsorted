<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Home: describe the problem (spec 007) or pick a trade to start a booking (spec 005, AC1). */
#[Layout('components.layouts.app', ['brand' => 'Get Sorted', 'description' => 'Describe the job, compare quotes from vetted Durban pros, and keep everything in one place.'])]
final class Welcome extends Component
{
    /** Session key the booking wizard reads to prefill its notes step (spec 007, AC4). */
    public const string DESCRIPTION_KEY = 'booking.description';

    private const int MIN_LENGTH = 3;

    private const int MAX_LENGTH = 500;

    public string $description = '';

    /** Trade picked with the chips next to the home page box ('' for any trade). */
    public string $tradeKey = '';

    /** An unused description expires after 30 minutes; drop it rather than keep it in the session (spec 007). */
    public function mount(): void
    {
        $stored = session(self::DESCRIPTION_KEY);

        if (is_array($stored) && ($stored['expires_at'] ?? 0) <= now()->getTimestamp()) {
            session()->forget(self::DESCRIPTION_KEY);
        }
    }

    /** Home page box: hand the description to the Siya booking thread (it reads DESCRIPTION_KEY once) and go there. */
    public function start(): mixed
    {
        $description = trim($this->description);

        if ($description !== '') {
            $this->validate(
                ['description' => ['string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH]],
                ['description.*' => __('Describe the problem in :min to :max characters.', ['min' => self::MIN_LENGTH, 'max' => self::MAX_LENGTH])],
            );

            session()->put(self::DESCRIPTION_KEY, [
                'text' => $description,
                'expires_at' => now()->addMinutes((int) config('sortd.ai.description_ttl_minutes'))->getTimestamp(),
            ]);
        }

        $trade = $this->tradeKey === '' ? null : Trade::query()->where('is_active', true)->where('key', $this->tradeKey)->first();

        return $this->redirect($trade instanceof Trade ? route('book.trade', $trade) : route('book'), navigate: true);
    }

    public function render(): View
    {
        $trades = Trade::query()->where('is_active', true)
            ->orderBy('sort')->get()
            ->sortBy(fn (Trade $trade): int => (int) (array_search($trade->key, ['plumbing', 'electrical', 'painting', 'tiling'], true) === false ? 99 : array_search($trade->key, ['plumbing', 'electrical', 'painting', 'tiling'], true)))
            ->values();

        $icons = ['plumbing' => 'drop', 'electrical' => 'lightning', 'painting' => 'paint-roller', 'tiling' => 'squares-four'];
        $blurbs = [
            'plumbing' => 'Leaks, geysers, drains and new fittings',
            'electrical' => 'Fault finding, DB boards, certificates',
            'painting' => 'Interior and exterior',
            'tiling' => 'Floors and walls',
        ];

        return view('livewire.welcome', [
            'trades' => $trades,
            'tradeLinks' => $trades->map(fn (Trade $trade): array => [
                'key' => $trade->key,
                'name' => $trade->name,
                'url' => route('book.trade', $trade),
                'show' => route('trades.show', $trade),
                'icon' => $icons[$trade->key] ?? 'sparkle',
                'blurb' => $blurbs[$trade->key] ?? '',
                'short' => $blurbs[$trade->key] ?? '',
            ])->all(),
        ]);
    }
}
