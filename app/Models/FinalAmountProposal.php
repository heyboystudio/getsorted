<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Quotes\Enums\ProposalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The accepted pro's proposed final amount (spec 018, AC9–AC14). Amounts are
 * server-calculated integer cents; rows are only ever moved forward by Quotes actions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $service_job_id
 * @property int $quote_id
 * @property int $pro_id
 * @property int $version
 * @property list<array{kind: string, description: string, quantity: string, unit_price_cents: int, line_total_cents: int}> $lines
 * @property int $labour_cents
 * @property int $materials_cents
 * @property int $callout_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property int $previous_total_cents
 * @property string $reason
 * @property ProposalStatus $status
 * @property CarbonImmutable|null $decided_at
 * @property int|null $decided_by
 * @property string|null $customer_note
 * @property CarbonImmutable $created_at
 */
final class FinalAmountProposal extends Model
{
    use HasUlids;

    /** Everything is set by actions. */
    /** @var list<string> */
    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ServiceJob, $this> */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    /** Positive for an increase, negative for a decrease. */
    public function differenceCents(): int
    {
        return $this->total_cents - $this->previous_total_cents;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'status' => ProposalStatus::class,
            'version' => 'integer',
            'labour_cents' => 'integer',
            'materials_cents' => 'integer',
            'callout_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'previous_total_cents' => 'integer',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
