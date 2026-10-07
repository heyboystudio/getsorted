<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProChangeStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A change an approved pro asked for that needs an admin's decision (spec 021, AC27): a new
 * trade, a new registration badge, or a renewed registration document. Until it is approved the pro's
 * current approved state stays in force; only DecideProChange applies it.
 *
 * @property int $id
 * @property string $public_id
 * @property int $pro_id
 * @property int|null $trade_id
 * @property DocumentType|null $document_type
 * @property string|null $registration_number
 * @property ProChangeStatus $status
 * @property string|null $decision_reason
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable $created_at
 */
final class ProChangeRequest extends Model implements HasMedia
{
    use HasUlids, InteractsWithMedia;

    public const string FILE_COLLECTION = 'file';

    /** Everything is set by actions. */
    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['registration_number'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
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

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function file(): ?Media
    {
        return $this->getFirstMedia(self::FILE_COLLECTION);
    }

    /** A five-minute signed link; the controller re-checks who is asking. */
    public function temporaryUrl(): string
    {
        return URL::temporarySignedRoute('pro-changes.file', now()->addMinutes(5), ['change' => $this]);
    }

    /** What was asked for, in words both the pro and the admin can read. */
    public function summary(): string
    {
        $parts = [];

        if ($this->trade_id !== null) {
            $parts[] = __('Add :trade', ['trade' => (string) data_get($this, 'trade.name', __('a trade'))]);
        }

        if ($this->document_type instanceof DocumentType) {
            $parts[] = __('Registration: :type', ['type' => $this->document_type->label()]);
        }

        return implode(' · ', $parts);
    }

    public function isPending(): bool
    {
        return $this->status === ProChangeStatus::Pending;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => ProChangeStatus::class,
            'registration_number' => 'encrypted',
            'decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
