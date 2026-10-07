<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Pros\Actions\UpdateProProfile;
use App\Domain\Pros\Enums\DocumentType;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\Suburb;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A pro's shop front (spec 021, AC25–AC29): bio, weekly cap and suburbs save at once; services
 * and registrations go through review; documents show their expiry.
 */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Profile')]
final class Profile extends Component
{
    use EnsuresApprovedPro;

    public string $bio = '';

    public ?string $weeklyCap = null;

    /** @var list<int|string> */
    public array $suburbIds = [];

    public ?string $saved = null;

    public function mount(): void
    {
        if (! $this->ensureApprovedPro()) {
            return;
        }

        $pro = $this->currentPro()->load('serviceAreas');
        $this->bio = (string) $pro->bio;
        $this->weeklyCap = $pro->weekly_job_cap === null ? null : (string) $pro->weekly_job_cap;
        $this->suburbIds = $pro->serviceAreas->pluck('id')->all();
    }

    public function saveBio(UpdateProProfile $update): void
    {
        $this->saved = null;
        $update->bio($this->currentUser(), $this->currentPro(), $this->bio);
        $this->saved = 'bio';
    }

    public function saveCap(UpdateProProfile $update): void
    {
        $this->saved = null;
        $cap = trim((string) $this->weeklyCap);
        $update->weeklyCap($this->currentUser(), $this->currentPro(), $cap === '' ? null : (ctype_digit($cap) ? (int) $cap : 0));
        $this->saved = 'cap';
    }

    public function saveAreas(UpdateProProfile $update): void
    {
        $this->saved = null;
        $update->areas($this->currentUser(), $this->currentPro(), array_map(intval(...), $this->suburbIds));
        $this->saved = 'areas';
    }

    public function render(): View
    {
        $pro = $this->currentPro()->load(['services.trade', 'documents']);
        $photo = $pro->document(DocumentType::ProfilePhoto);

        return view('livewire.pros.profile', [
            'pro' => $pro,
            'photoUrl' => $photo?->file() !== null && $photo->status->value === 'verified' ? $photo->temporaryUrl() : null,
            'services' => $pro->services->sortBy('name')->groupBy(fn ($service): string => $service->trade->name)->sortKeys(),
            'suburbsByRegion' => Suburb::query()->where('is_active', true)->orderBy('region')->orderBy('name')->get()->groupBy('region'),
            'documents' => $pro->documents->sortBy(fn ($document): string => $document->type->value),
            'attention' => $pro->registrationsNeedingAttention()->pluck('id')->all(),
            'maxCap' => UpdateProProfile::MAX_WEEKLY_CAP,
        ]);
    }
}
