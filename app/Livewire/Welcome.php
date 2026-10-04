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

    public function find(SuggestService $suggestService): void
    {
        $this->resetErrorBag();
        $this->suggestedServiceId = null;
        $this->showFallback = false;

        $service = $suggestService->handle($this->description, hash_hmac('sha256', (string) request()->ip(), (string) config('app.key')));

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
