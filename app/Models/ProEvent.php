<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pros\Enums\ProStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only history of a pro's status changes (spec 008, AC11).
 *
 * @property int $id
 * @property int $pro_id
 * @property ProStatus|null $from_status
 * @property ProStatus $to_status
 * @property int|null $actor_id
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 */
final class ProEvent extends Model
{
    public const UPDATED_AT = null;

    /** The history panel always shows who made each change. */
    /** @var list<string> */
    protected $with = ['actor'];

    /** @var list<string> */
    protected $fillable = ['from_status', 'to_status', 'actor_id', 'reason'];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Pro events are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Pro events are append-only.'));
    }

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => ProStatus::class,
            'to_status' => ProStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
