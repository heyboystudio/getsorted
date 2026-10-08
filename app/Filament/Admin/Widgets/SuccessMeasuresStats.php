<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Operations\Support\SuccessMeasures;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The PRD's success measures over the last 90 days, each against its target (spec 027, AC4–AC5). */
final class SuccessMeasuresStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Success measures, last 90 days';

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        return array_values(array_map(
            fn (array $measure): Stat => Stat::make($measure['label'], $measure['display'])
                ->description(__('Target :target · :count jobs', ['target' => $measure['target'], 'count' => $measure['sample']]))
                ->color($measure['met'] === null ? 'gray' : ($measure['met'] ? 'success' : 'warning')),
            SuccessMeasures::over(90),
        ));
    }
}
