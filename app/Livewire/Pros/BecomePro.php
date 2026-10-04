<?php

declare(strict_types=1);

namespace App\Livewire\Pros;

use App\Domain\Accounts\Actions\BecomePro as BecomeProAction;
use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** An existing customer accepts the pro agreement to add the pro role (spec 011, AC5). */
#[Layout('components.layouts.app')]
#[Title('Become a pro')]
final class BecomePro extends Component
{
    public bool $acceptProAgreement = false;

    public function mount(): void
    {
        if ($this->user()->hasRole(Role::Pro->value)) {
            $this->redirectRoute('pros.welcome');
        }
    }

    public function confirm(BecomeProAction $becomePro): void
    {
        $this->validate(
            ['acceptProAgreement' => ['accepted']],
            ['acceptProAgreement.accepted' => __('Please accept the pro agreement.')],
        );

        $becomePro->handle($this->user(), request()->ip(), request()->userAgent());

        $this->redirectRoute('pros.welcome');
    }

    public function render(): View
    {
        return view('livewire.pros.become-pro', ['firstName' => $this->user()->first_name]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
