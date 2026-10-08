<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Domain\Operations\Support\StalledJobs;
use App\Domain\Pros\Enums\ProChangeStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Filament\Admin\Resources\DataRequests\DataRequestResource;
use App\Filament\Admin\Resources\ProApplications\ProApplicationResource;
use App\Filament\Admin\Resources\ProChangeRequests\ProChangeRequestResource;
use App\Filament\Admin\Resources\ServiceJobs\ServiceJobResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** What needs an admin today: stalled jobs, vetting queue, privacy requests. */
final class AdminStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = null;

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $jobs = fn (ServiceJobStatus ...$statuses): int => ServiceJobResource::getEloquentQuery()->whereIn('status', array_map(fn (ServiceJobStatus $s): string => $s->value, $statuses))->count();

        return [
            Stat::make(__('Finding pros'), $jobs(ServiceJobStatus::Open))
                ->description(__('Jobs open for quotes'))
                ->url(ServiceJobResource::getUrl()),
            Stat::make(__('Stalled jobs'), StalledJobs::all()->count())
                ->description(__('No estimate yet, or booked and not done'))
                ->color(StalledJobs::all()->isNotEmpty() ? 'warning' : 'gray')
                ->url(ServiceJobResource::getUrl()),
            Stat::make(__('Underway'), $jobs(ServiceJobStatus::Scheduled, ServiceJobStatus::InProgress, ServiceJobStatus::AwaitingFinalPayment))
                ->description(__('Booked or in progress'))
                ->url(ServiceJobResource::getUrl()),
            Stat::make(__('Pro applications'), ProApplicationResource::getEloquentQuery()->where('status', ProStatus::Submitted->value)->count())
                ->description(__('Waiting for review'))
                ->url(ProApplicationResource::getUrl()),
            Stat::make(__('Profile changes'), ProChangeRequestResource::getEloquentQuery()->where('status', ProChangeStatus::Pending->value)->count())
                ->description(__('Pending approval'))
                ->url(ProChangeRequestResource::getUrl()),
            Stat::make(__('Data requests'), DataRequestResource::getEloquentQuery()->where('status', DataRequestStatus::Open->value)->count())
                ->description(__('Open privacy requests'))
                ->url(DataRequestResource::getUrl()),
        ];
    }
}
