<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Support\ProPublicProfile;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The pro's own profile through the customer's eyes: the same view, the same allow-list (spec 021, AC28). */
#[Layout('components.layouts.panel', ['panel' => 'pro'])]
#[Title('Profile preview')]
final class ProfilePreview extends Component
{
    use EnsuresApprovedPro;

    public function mount(): void
    {
        $this->ensureApprovedPro();
    }

    public function render(): View
    {
        $pro = $this->currentPro()->load(['trades', 'documents']);
        $photo = $pro->document(DocumentType::ProfilePhoto);

        return view('livewire.pros.profile-preview', [
            'profile' => ProPublicProfile::from($pro, $photo?->file() !== null && $photo->status === DocumentStatus::Verified ? $photo->temporaryUrl() : null),
        ]);
    }
}
