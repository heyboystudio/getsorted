<?php

declare(strict_types=1);

namespace App\Livewire\Pros\Jobs;

use App\Domain\Matching\Support\ProPipeline;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** A pro's pipeline: Invites to answer, Quoted, Booked and Done (spec 021, AC23; spec 009, AC7). */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Your jobs')]
final class Index extends Component
{
    use EnsuresApprovedPro;

    /** Links and tests from before the pipeline used these two names. */
    private const array LEGACY_TABS = ['new' => 'invites', 'past' => 'done'];

    #[Url]
    public string $tab = 'invites';

    public function mount(): void
    {
        $this->ensureApprovedPro();
    }

    public function render(): View
    {
        $pipeline = ProPipeline::for($this->currentPro());
        $requested = self::LEGACY_TABS[$this->tab] ?? $this->tab;
        $current = in_array($requested, ProPipeline::STAGES, true) ? $requested : 'invites';

        return view('livewire.pros.jobs.index', [
            'rows' => $pipeline[$current],
            'counts' => array_map(fn ($rows): int => $rows->count(), $pipeline),
            'current' => $current,
        ]);
    }
}
