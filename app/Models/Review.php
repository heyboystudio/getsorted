<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's rating of the pro after a finished job, and the pro's one reply (spec 025). Created and
 * changed only by the review actions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $service_job_id
 * @property int $pro_id
 * @property int $customer_id
 * @property int $rating
 * @property string|null $comment
 * @property string|null $reply
 * @property CarbonImmutable|null $replied_at
 * @property CarbonImmutable|null $hidden_at
 * @property int|null $hidden_by
 * @property string|null $hide_reason
 * @property CarbonImmutable $created_at
 */
final class Review extends Model
{
    use HasUlids;

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

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'replied_at' => 'immutable_datetime',
            'hidden_at' => 'immutable_datetime',
        ];
    }
}
