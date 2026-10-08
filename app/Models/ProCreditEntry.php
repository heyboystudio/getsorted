<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Introductions\Enums\CreditEntryType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line in a pro's prepaid credit ledger (spec 023). Append-only; the balance is the sum, and the
 * idempotency key means a repeated PayFast notification cannot add credit twice.
 *
 * @property int $id
 * @property int $pro_id
 * @property CreditEntryType $type
 * @property int $amount_cents
 * @property int|null $credit_purchase_id
 * @property int|null $introduction_id
 * @property string|null $note
 * @property string $idempotency_key
 * @property CarbonImmutable $created_at
 */
final class ProCreditEntry extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Credit entries are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Credit entries are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => CreditEntryType::class,
            'amount_cents' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
