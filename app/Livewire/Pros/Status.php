<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Enums\DocumentType;
use App\Models\Pro;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** What vetting has checked and what still needs the pro (spec 008, AC5, AC12). Never shows internal notes. */
#[Layout('components.layouts.app')]
#[Title('Your application')]
final class Status extends Component
{
    public function mount(): void
    {
        $user = $this->user();

        if (! $user->hasRole(Role::Pro->value)) {
            $this->redirectRoute('pros.join');
            $this->skipRender();

            return;
        }

        if (! Pro::query()->where('user_id', $user->id)->exists()) {
            $this->redirectRoute('pros.welcome');
            $this->skipRender();
        }
    }

    public function render(): View
    {
        $pro = Pro::query()->where('user_id', $this->user()->id)
            ->with(['documents.media', 'references', 'trades'])->firstOrFail();

        return view('livewire.pros.status', [
            'pro' => $pro,
            'documentTypes' => [...DocumentType::required(), ...$pro->offeredRegistrations()],
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
