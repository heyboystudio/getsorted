<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row per status change: the job timeline and audit trail. Append-only.
 *
 * @property int $id
 * @property int $service_job_id
 * @property ServiceJobStatus|null $from_status
 * @property ServiceJobStatus $to_status
 * @property string $event_type
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $created_at
 */
final class ServiceJobEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['from_status', 'to_status', 'event_type', 'actor_type', 'actor_id', 'payload'];

    /** @var array<string, mixed> */
    protected $attributes = ['payload' => '{}'];

    /** @return BelongsTo<ServiceJob, $this> */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Job events are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Job events are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => ServiceJobStatus::class,
            'to_status' => ServiceJobStatus::class,
            'actor_type' => ActorType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
