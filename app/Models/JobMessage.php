<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\ServiceJobs\Enums\MessageKind;
use App\Domain\ServiceJobs\Enums\MessageReportReason;
use App\Domain\ServiceJobs\Enums\MessageSender;
use Carbon\CarbonImmutable;
use Database\Factories\JobMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One chat message (spec 018). Text is masked when stored if contact details
 * aren't shared yet; messages are never edited, only deleted by their sender.
 *
 * @property int $id
 * @property string $public_id
 * @property int $job_conversation_id
 * @property MessageSender $sender_type
 * @property int|null $sender_id
 * @property string|null $body
 * @property MessageKind $kind
 * @property int|null $quote_id
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $reported_at
 * @property MessageReportReason|null $report_reason
 * @property int|null $reported_by
 * @property CarbonImmutable $created_at
 */
final class JobMessage extends Model implements HasMedia
{
    /** @use HasFactory<JobMessageFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, Prunable;

    public const string PHOTO_COLLECTION = 'message_photos';

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

    /** @return BelongsTo<JobConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(JobConversation::class, 'job_conversation_id');
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION)->useDisk('media');
    }

    /** A five-minute link to one of this message's photos, checked against the viewer. */
    public function photoUrl(Media $photo): string
    {
        return URL::temporarySignedRoute('message-photos.show', now()->addMinutes(5), ['message' => $this, 'photo' => $photo->uuid]);
    }

    /**
     * Chats are kept for the retention period after the job ends (spec 018, decision 6).
     * Prunable (not mass) so the media library also deletes each message's photo files.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subMonths((int) config('sortd.chat.retention_months'));

        return self::query()->whereHas('conversation.serviceJob', fn (Builder $job) => $job
            ->whereIn('status', ['closed', 'cancelled', 'expired'])->where('updated_at', '<', $cutoff));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sender_type' => MessageSender::class,
            'kind' => MessageKind::class,
            'report_reason' => MessageReportReason::class,
            'deleted_at' => 'immutable_datetime',
            'reported_at' => 'immutable_datetime',
        ];
    }
}
