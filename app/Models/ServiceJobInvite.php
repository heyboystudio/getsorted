<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Matching\Enums\DeclineReason;
use App\Domain\Matching\Enums\InviteStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceJobInviteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A pro invited to quote on a job (spec 009). Status changes only through
 * Matching actions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $service_job_id
 * @property int $pro_id
 * @property int $wave
 * @property InviteStatus $status
 * @property CarbonImmutable $invited_at
 * @property CarbonImmutable|null $viewed_at
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable $expires_at
 * @property DeclineReason|null $decline_reason
 * @property string|null $decline_note
 * @property int|null $invited_by
 */
final class ServiceJobInvite extends Model
{
    /** @use HasFactory<ServiceJobInviteFactory> */
    use HasFactory, HasUlids;

    /** Everything is set by actions. */
    /** @var list<string> */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'invited'];

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

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Still waiting for the pro and not past its expiry. */
    public function isAvailable(): bool
    {
        return $this->status->isOpen() && $this->expires_at->isFuture();
    }

    /** A five-minute link to a job photo, checked against this invite (AC8). */
    public function photoUrl(Media $photo): string
    {
        return URL::temporarySignedRoute('pros.jobs.photo', now()->addMinutes(5), ['invite' => $this, 'photo' => $photo->uuid]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InviteStatus::class,
            'decline_reason' => DeclineReason::class,
            'wave' => 'integer',
            'invited_at' => 'immutable_datetime',
            'viewed_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
