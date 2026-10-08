<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Introductions\Enums\CreditPurchaseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pro's attempt to buy credit through PayFast (spec 023). Its status changes only from a verified
 * PayFast notification, never from the pro's browser coming back.
 *
 * @property int $id
 * @property string $public_id
 * @property int $pro_id
 * @property int $amount_cents
 * @property CreditPurchaseStatus $status
 * @property string|null $provider_reference
 * @property CarbonImmutable|null $completed_at
 */
final class CreditPurchase extends Model
{
    use HasUlids;

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

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CreditPurchaseStatus::class,
            'amount_cents' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
