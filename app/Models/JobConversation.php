<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JobConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The chat between a job's customer and one invited pro (spec 018). Created by
 * the first message from either side; changed only through ServiceJobs actions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $service_job_id
 * @property int $pro_id
 * @property string $status
 * @property string|null $closed_reason
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $customer_read_at
 * @property CarbonImmutable|null $pro_read_at
 * @property CarbonImmutable|null $customer_notified_at
 * @property CarbonImmutable|null $pro_notified_at
 * @property CarbonImmutable $created_at
 */
final class JobConversation extends Model
{
    /** @use HasFactory<JobConversationFactory> */
    use HasFactory, HasUlids;

    /** Statuses a job can chat in: from posting until the final invoice (spec 018, AC5). */
    public const array CHAT_STATUSES = [
        ServiceJobStatus::Open, ServiceJobStatus::AwaitingDeposit, ServiceJobStatus::Scheduled,
        ServiceJobStatus::InProgress, ServiceJobStatus::AwaitingFinalPayment, ServiceJobStatus::Disputed,
    ];

    /** Everything is set by actions. */
    /** @var list<string> */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

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

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    /** @return HasMany<JobMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(JobMessage::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'closed_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'customer_read_at' => 'immutable_datetime',
            'pro_read_at' => 'immutable_datetime',
            'customer_notified_at' => 'immutable_datetime',
            'pro_notified_at' => 'immutable_datetime',
        ];
    }
}
