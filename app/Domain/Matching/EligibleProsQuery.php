<?php

declare(strict_types=1);

namespace App\Domain\Matching;

use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use App\Settings\MatchingSettings;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Who can take a job (spec 020): approved pros of the job's trade whose base address is within their
 * own travel radius of the job, plus a soft edge (default 2 km) that only fills invites that nearer pros leave open.
 * Registration is never a gate: verified pros get a badge, unverified pros can still quote.
 */
final readonly class EligibleProsQuery
{
    /** Candidates fetched before ranking; far above any invite count. */
    private const int CANDIDATE_LIMIT = 200;

    public function __construct(private MatchingSettings $settings) {}

    /**
     * Approved pros of the trade within their radius plus the soft edge of the point, with distance_m.
     *
     * @return Builder<Pro>
     */
    public function near(Trade $trade, Point $point, ?User $customer = null): Builder
    {
        $target = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';
        $bindings = [$point->getLongitude(), $point->getLatitude()];

        return Pro::query()
            ->select('pros.*')
            ->selectRaw("ST_Distance(pros.base_location, {$target}) as distance_m", $bindings)
            ->where('status', ProStatus::Approved)
            ->whereNotNull('approved_at')
            ->whereNotNull('base_location')
            ->whereHas('trades', fn (Builder $query): Builder => $query->whereKey($trade->id))
            ->whereRaw("ST_DWithin(pros.base_location, {$target}, (pros.service_radius_km + ?) * 1000)", [...$bindings, $this->settings->soft_edge_km])
            ->where(function (Builder $query): void {
                $query->whereNull('weekly_job_cap')->orWhereRaw('(select count(*) from pro_job_allocations where pro_job_allocations.pro_id = pros.id and allocated_at >= ?) < pros.weekly_job_cap', [now()->subDays(7)]);
            })
            ->when($customer instanceof User, function (Builder $query) use ($customer): void {
                $query->whereNotExists(function (QueryBuilder $excluded) use ($customer): void {
                    $excluded->selectRaw('1')->from('pro_customer_exclusions')
                        ->whereColumn('pro_customer_exclusions.pro_id', 'pros.id')
                        ->where('pro_customer_exclusions.customer_id', $customer->id);
                });
            });
    }

    /**
     * Pros to invite to a posted job who were not invited yet: pros inside their own radius first,
     * fewest invites in the last 7 days, then nearest; soft-edge pros only fill what is left.
     *
     * @return Collection<int, Pro>
     */
    public function rankedFor(ServiceJob $job, int $limit): Collection
    {
        $job->loadMissing(['trade', 'customer']);

        if ($job->location === null || ! $job->trade->is_active || $limit < 1) {
            return new Collection;
        }

        $candidates = $this->near($job->trade, $job->location, $job->customer)
            ->whereDoesntHave('invites', fn (Builder $invites): Builder => $invites->where('service_job_id', $job->id))
            ->withCount(['invites as recent_invites' => fn (Builder $invites): Builder => $invites->where('invited_at', '>=', now()->subDays(7))])
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        $inside = $candidates->filter(fn (Pro $pro): bool => $this->withinRadius($pro));
        $edge = $candidates->reject(fn (Pro $pro): bool => $this->withinRadius($pro));

        $ranked = fn (Collection $pros): Collection => $pros->shuffle()->sortBy([['recent_invites', 'asc'], ['distance_m', 'asc']])->values();

        return new Collection($ranked($inside)->concat($ranked($edge))->take($limit)->all());
    }

    /** Whether any eligible pro could take a job of this trade at this point. */
    public function exists(Trade $trade, Point $point, ?User $customer = null): bool
    {
        return $trade->is_active && $this->near($trade, $point, $customer)->exists();
    }

    /**
     * Whether a customer may book this trade at this point. Before launch (decision 044) a job
     * posts even with no pros signed up; set SORTD_REQUIRE_PROS=true to require a nearby pro.
     */
    public function covers(Trade $trade, Point $point, ?User $customer = null): bool
    {
        if ((bool) config('sortd.coverage.require_pros')) {
            return $this->exists($trade, $point, $customer);
        }

        return $trade->is_active;
    }

    private function withinRadius(Pro $pro): bool
    {
        return (float) $pro->getAttribute('distance_m') <= $pro->service_radius_km * 1000;
    }
}
