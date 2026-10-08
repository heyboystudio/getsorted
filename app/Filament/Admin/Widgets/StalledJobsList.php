<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Operations\Support\StalledJobs;
use App\Filament\Admin\Resources\ServiceJobs\ServiceJobResource;
use Filament\Widgets\Widget;

/** Jobs that need a nudge from an admin, oldest first (spec 027, AC1–AC3). */
final class StalledJobsList extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.stalled-jobs-list';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'rows' => StalledJobs::all()->take(20)->map(fn (array $row): array => [
                'trade' => $row['job']->trade->name,
                'area' => $row['job']->area_label,
                'label' => $row['label'],
                'since' => $row['since']->diffForHumans(),
                'url' => ServiceJobResource::getUrl('view', ['record' => $row['job']]),
            ])->all(),
        ];
    }
}
