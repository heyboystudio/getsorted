<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One vetting document of a pro: one file, a status, and for registrations an
 * encrypted number and an expiry date (spec 008).
 *
 * @property int $id
 * @property string $public_id
 * @property int $pro_id
 * @property DocumentType $type
 * @property DocumentStatus $status
 * @property string|null $number
 * @property CarbonImmutable|null $verified_at
 * @property int|null $verified_by
 * @property CarbonImmutable|null $expires_at
 * @property string|null $flag_message
 * @property string|null $notes
 */
final class ProDocument extends Model implements HasMedia
{
    use HasUlids, InteractsWithMedia;

    public const string FILE_COLLECTION = 'file';

    /** Vetting fields are set by actions with forceFill; pros only choose the type. */
    /** @var list<string> */
    protected $fillable = ['type'];

    /** @var list<string> */
    protected $hidden = ['number', 'notes'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FILE_COLLECTION)->singleFile()->useDisk('media');
    }

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::FILE_COLLECTION);
    }

    /** A five-minute signed link; the controller re-checks who is asking (AC8). */
    public function temporaryUrl(): string
    {
        return URL::temporarySignedRoute('pro-documents.show', now()->addMinutes(5), ['document' => $this]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'number' => 'encrypted',
            'verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
