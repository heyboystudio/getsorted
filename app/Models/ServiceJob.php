<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Enums\Urgency;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceJobFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A customer's job ("Job" in the UI; `jobs` is Laravel's queue table).
 * `status` is never set directly: use ServiceJobStateMachine.
 *
 * @property int $id
 * @property string $public_id
 * @property int $customer_id
 * @property int $service_id
 * @property int|null $property_id
 * @property ServiceJobStatus $status
 * @property Urgency $urgency
 * @property CarbonImmutable|null $preferred_date
 * @property TimeWindow|null $time_window
 * @property array<string, array{prompt: string, type: string, answer: string|int|list<string>}> $scoping_answers
 * @property string|null $customer_notes
 * @property string|null $ai_summary
 * @property SummarySource $ai_summary_source
 * @property CarbonImmutable|null $ai_summary_generated_at
 * @property string|null $ai_summary_input_hash
 * @property CarbonImmutable|null $posted_at
 * @property int $quotes_count
 * @property int|null $accepted_quote_id
 * @property CarbonImmutable|null $scheduled_for
 * @property CarbonImmutable|null $last_wave_at
 * @property CarbonImmutable|null $matching_stopped_at
 * @property string|null $matching_stopped_reason
 * @property CarbonImmutable|null $quote_window_ends_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property CarbonImmutable $updated_at
 */
final class ServiceJob extends Model implements HasMedia
{
    /** @use HasFactory<ServiceJobFactory> */
    use HasFactory, HasUlids, InteractsWithMedia;

    public const string PHOTO_COLLECTION = 'job_photos';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION)->useDisk('media');
    }

    public function photoUrl(Media $photo): string
    {
        return URL::temporarySignedRoute('job-photos.show', now()->addMinutes(5), ['job' => $this, 'photo' => $photo->uuid]);
    }

    /** Status is deliberately absent: only the state machine writes it. */
    /** @var list<string> */
    protected $fillable = ['urgency', 'preferred_date', 'time_window', 'scoping_answers', 'customer_notes'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'urgency' => 'normal', 'scoping_answers' => '{}', 'ai_summary_source' => 'none'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return BelongsTo<Quote, $this> */
    public function acceptedQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'accepted_quote_id');
    }

    /** @return HasMany<ServiceJobInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(ServiceJobInvite::class);
    }

    /** @return HasMany<JobConversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(JobConversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /** @return HasMany<ServiceJobEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceJobEvent::class)->orderBy('id');
    }

    /**
     * Answers in the service's question order. The database (jsonb) doesn't keep
     * key order; answers to questions removed since are listed last.
     *
     * @return list<array{prompt: string, type: string, answer: string|int|list<string>}>
     */
    public function orderedAnswers(): array
    {
        $answers = $this->scoping_answers;
        $ordered = [];

        foreach ($this->service->questions as $question) {
            if (isset($answers[$question->key])) {
                $ordered[] = $answers[$question->key];
                unset($answers[$question->key]);
            }
        }

        return [...$ordered, ...array_values($answers)];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ServiceJobStatus::class,
            'urgency' => Urgency::class,
            'preferred_date' => 'immutable_date',
            'time_window' => TimeWindow::class,
            'scoping_answers' => 'array',
            'ai_summary_source' => SummarySource::class,
            'ai_summary_generated_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'last_wave_at' => 'immutable_datetime',
            'quotes_count' => 'integer',
            'scheduled_for' => 'immutable_date',
            'matching_stopped_at' => 'immutable_datetime',
            'quote_window_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
