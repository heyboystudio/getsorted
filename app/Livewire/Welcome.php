<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Assistant\Actions\SuggestService;
use App\Models\Service;
use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Home: describe the problem (spec 007) or pick a trade to start a booking (spec 005, AC1). */
#[Layout('components.layouts.app')]
final class Welcome extends Component
{
    /** Session key the booking wizard reads to prefill its notes step (spec 007, AC4). */
    public const string DESCRIPTION_KEY = 'booking.description';

    public string $description = '';

    #[Locked]
    public ?int $suggestedServiceId = null;

    #[Locked]
    public bool $showFallback = false;

    /** An unused description expires after 30 minutes; drop it rather than keep it in the session (spec 007). */
    public function mount(): void
    {
        $stored = session(self::DESCRIPTION_KEY);

        if (is_array($stored) && ($stored['expires_at'] ?? 0) <= now()->getTimestamp()) {
            session()->forget(self::DESCRIPTION_KEY);
        }
    }

    public function find(SuggestService $suggestService): void
    {
        $this->resetErrorBag();
        $this->suggestedServiceId = null;
        $this->showFallback = false;

        // Signed-in customers are limited per account; guests per (hashed) network address.
        $visitor = auth()->id() !== null ? 'user:'.auth()->id() : hash_hmac('sha256', (string) request()->ip(), (string) config('app.key'));
        $service = $suggestService->handle($this->description, $visitor);

        session()->put(self::DESCRIPTION_KEY, [
            'text' => trim($this->description),
            'expires_at' => now()->addMinutes((int) config('sortd.ai.description_ttl_minutes'))->getTimestamp(),
        ]);

        $this->suggestedServiceId = $service?->id;
        $this->showFallback = ! $service instanceof Service;
    }

    public function chooseSomethingElse(): void
    {
        $this->suggestedServiceId = null;
        $this->showFallback = true;
    }

    public function render(): View
    {
        return view('livewire.welcome', [
            'trades' => Trade::query()->where('is_active', true)
                ->whereHas('services', fn ($query) => $query->where('is_active', true))
                ->orderBy('sort')->get(),
            'suggestion' => $this->suggestedServiceId === null ? null : Service::query()->with('trade')
                ->where('is_active', true)->find($this->suggestedServiceId),
        ]);
    }
}
