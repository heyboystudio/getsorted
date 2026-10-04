<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Support\LocalTime;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

/**
 * One version of a pro's quote on a job (spec 010). Amounts are calculated on
 * the server from the lines; status changes only through Quote actions.
 *
 * @property int $id
 * @property string $public_id
 * @property int $service_job_id
 * @property int $pro_id
 * @property int $version
 * @property QuoteStatus $status
 * @property int $labour_cents
 * @property int $materials_cents
 * @property int $callout_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property int $deposit_percent
 * @property int $deposit_cents
 * @property CarbonImmutable $earliest_start_date
 * @property CarbonImmutable $valid_until
 * @property string|null $notes
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property string|null $withdraw_reason
 * @property int|null $supersedes_quote_id
 */
final class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, HasUlids;

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

    /** @return HasMany<QuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('sort');
    }

    /** The newest version of this pro's quote on this job. */
    public function latestVersion(): self
    {
        return self::query()->where('service_job_id', $this->service_job_id)->where('pro_id', $this->pro_id)->orderByDesc('version')->firstOrFail();
    }

    public function money(int $cents): Money
    {
        return Money::ofMinor($cents, 'ZAR');
    }

    public function isPastValidity(): bool
    {
        return $this->valid_until->toDateString() < LocalTime::today()->toDateString();
    }

    /** A five-minute link to the quoting pro's profile photo for the job's customer (AC7). */
    public function proPhotoUrl(): string
    {
        return URL::temporarySignedRoute('quotes.pro-photo', now()->addMinutes(5), ['quote' => $this]);
    }

    public function hasProPhoto(): bool
    {
        $photo = $this->pro->document(DocumentType::ProfilePhoto);

        return $photo?->status === DocumentStatus::Verified && $photo->file() !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'version' => 'integer',
            'labour_cents' => 'integer',
            'materials_cents' => 'integer',
            'callout_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'deposit_percent' => 'integer',
            'deposit_cents' => 'integer',
            'earliest_start_date' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
