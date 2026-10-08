<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Flux\Flux;

/**
 * After an action the workspace tells the person what happened and moves to it (spec 028, AC3): a short toast,
 * and the section that changed scrolls into view and is highlighted (see resources/js/app.js).
 */
trait ShowsFeedback
{
    private function feedback(string $text, ?string $heading = null, ?string $focus = null, string $variant = 'success'): void
    {
        Flux::toast(text: $text, heading: $heading, variant: $variant);

        if ($focus !== null) {
            $this->dispatch('focus-section', id: $focus);
        }
    }
}
