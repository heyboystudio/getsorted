<?php

declare(strict_types=1);

namespace App\Livewire\Account\Properties;

use App\Domain\Properties\Actions\DeleteProperty;
use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** A customer's saved properties (spec 004). */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
#[Title('Saved properties')]
final class Index extends Component
{
    public function delete(string $publicId, DeleteProperty $deleteProperty): void
    {
        // Looked up through the owner, so another customer's property is never found.
        $property = $this->user()->properties()->where('public_id', $publicId)->first();

        if ($property instanceof Property) {
            $deleteProperty->handle($this->user(), $property);
        }
    }

    public function render(): View
    {
        return view('livewire.account.properties.index', [
            'properties' => $this->user()->properties()->latest()->get(),
            'limit' => (int) config('getsorted.properties.max_per_customer'),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
