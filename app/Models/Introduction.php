<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Introductions\Enums\IntroductionKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * The moment a client chose a pro to visit, which unlocks contact details (spec 023, decision 061).
 * One per job. Append-only.
 *
 * @property int $id
 * @property string $public_id
 * @property int $service_job_id
 * @property int $quote_id
 * @property int $pro_id
 * @property int $customer_id
 * @property IntroductionKind $kind
 * @property int $fee_cents
 * @property CarbonImmutable $created_at
 */
final class Introduction extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<ServiceJob, $this> */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Introductions are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Introductions are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => IntroductionKind::class,
            'fee_cents' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
