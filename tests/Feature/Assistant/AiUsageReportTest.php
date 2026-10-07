<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\AiUsageReport;
use App\Models\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('groups AI usage by the Durban calendar day, with midnight at SAST (decision 052)', function (): void {
    $this->travelTo(now('Africa/Johannesburg')->setTime(12, 0));
    $lateEvening = now('Africa/Johannesburg')->subDay()->setTime(23, 30);
    $justAfterMidnight = now('Africa/Johannesburg')->setTime(0, 30);

    AiUsage::factory()->create(['created_at' => $lateEvening]);
    AiUsage::factory()->create(['created_at' => $justAfterMidnight]);

    $days = (new AiUsageReport)->usage()->pluck('calls', 'day')->all();

    expect($days)->toBe([
        $justAfterMidnight->toDateString() => 1,
        $lateEvening->toDateString() => 1,
    ]);
});
