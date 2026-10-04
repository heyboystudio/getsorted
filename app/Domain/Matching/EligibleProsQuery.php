<?php

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\Suburb;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EligibleProsQuery
{
    /** @return Builder<Pro> */
    public function for(Service $service, Suburb $suburb, ?User $customer = null): Builder
    {
        return Pro::query()
            ->where('status', ProStatus::Approved)
            ->whereNotNull('approved_at')
            ->whereHas('services', fn (Builder $query): Builder => $query->whereKey($service->id))
            ->whereHas('serviceAreas', fn (Builder $query): Builder => $query->whereKey($suburb->id))
            ->where(function (Builder $query): void {
                $query->whereNull('weekly_job_cap')->orWhereRaw('(select count(*) from pro_job_allocations where pro_job_allocations.pro_id = pros.id and allocated_at >= ?) < pros.weekly_job_cap', [now()->subDays(7)]);
            })
            ->when($customer instanceof User, function (Builder $query) use ($customer): void {
                $query->whereNotExists(function (QueryBuilder $excluded) use ($customer): void {
                    $excluded->selectRaw('1')->from('pro_customer_exclusions')
                        ->whereColumn('pro_customer_exclusions.pro_id', 'pros.id')
                        ->where('pro_customer_exclusions.customer_id', $customer->id);
                });
            })
            ->when($service->requires_registration !== null, function (Builder $query) use ($service): void {
                $query->whereHas('documents', fn (Builder $documents): Builder => $documents
                    ->where('type', $service->requires_registration->value)
                    ->where('status', DocumentStatus::Verified)
                    ->whereNotNull('verified_at')
                    ->where(fn (Builder $valid): Builder => $valid->whereNull('expires_at')->orWhere('expires_at', '>', now())));
            });
    }

    /**
     * Eligible pros for a posted job who were not invited yet, fewest invites in
     * the last 7 days first, random tie-break (spec 009, AC2; founder decision 2).
     *
     * @return Collection<int, Pro>
     */
    public function rankedFor(ServiceJob $job, int $limit): Collection
    {
        $job->loadMissing(['service.trade', 'property.suburb', 'customer']);

        if (! $job->property?->suburb instanceof Suburb || ! $this->servable($job->service, $job->property->suburb)) {
            return new Collection;
        }

        return $this->for($job->service, $job->property->suburb, $job->customer)
            ->whereDoesntHave('invites', fn (Builder $invites): Builder => $invites->where('service_job_id', $job->id))
            ->withCount(['invites as recent_invites' => fn (Builder $invites): Builder => $invites->where('invited_at', '>=', now()->subDays(7))])
            ->orderBy('recent_invites')
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function exists(Service $service, Suburb $suburb, ?User $customer = null): bool
    {
        return $this->servable($service, $suburb) && $this->for($service, $suburb, $customer)->exists();
    }

    private function servable(Service $service, Suburb $suburb): bool
    {
        return $suburb->is_active && $service->is_active && $service->trade->is_active;
    }
}
