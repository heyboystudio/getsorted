<?php

declare(strict_types=1);

namespace App\Domain\Pros\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
use App\Settings\VettingSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Deletes documents, references and vetting notes of applications rejected or
 * abandoned longer ago than the retention period; the account stays
 * (spec 008, AC14; founder decision 3). Safe to run repeatedly.
 */
final readonly class PruneVettingRecords
{
    public function __construct(private VettingSettings $settings) {}

    public function handle(): int
    {
        $cutoff = now()->subMonths($this->settings->retention_months);
        $pruned = 0;

        Pro::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $rejected) => $rejected->where('status', ProStatus::Rejected)->where('decided_at', '<', $cutoff))
                ->orWhere(fn (Builder $abandoned) => $abandoned->whereIn('status', [ProStatus::Draft, ProStatus::ChangesRequested])->where('last_activity_at', '<', $cutoff)))
            ->where(fn (Builder $query) => $query->whereHas('documents')->orWhereHas('references')->orWhereNotNull('bio'))
            ->each(function (Pro $pro) use (&$pruned): void {
                DB::transaction(function () use ($pro): void {
                    // Deleting each document also removes its file.
                    $pro->documents()->each(fn ($document) => $document->delete());
                    $pro->references()->delete();
                    $pro->forceFill(['bio' => null, 'vat_number' => null])->save();
                });
                $pruned++;
            });

        return $pruned;
    }
}
