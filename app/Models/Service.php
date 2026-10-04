<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalogue\Enums\RegistrationType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A sub-type of a trade, e.g. "Blocked drain" (domain vocabulary).
 *
 * @property int $id
 * @property int $trade_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property RegistrationType|null $requires_registration
 * @property bool $emergency_capable
 * @property list<string> $safety_advice
 * @property bool $is_active
 * @property int $sort
 */
final class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, LogsActivity;

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'description', 'requires_registration', 'emergency_capable', 'safety_advice', 'is_active', 'sort'];

    /** @var array<string, mixed> */
    protected $attributes = ['safety_advice' => '[]'];

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return HasMany<ScopingQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(ScopingQuestion::class)->orderBy('sort');
    }

    /** URLs use the stable key, never the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /** Keys link jobs and pros to the catalogue and never change (spec 003). */
    protected static function booted(): void
    {
        self::updating(function (self $record): void {
            if ($record->isDirty('key')) {
                throw new LogicException('Catalogue keys cannot be changed.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requires_registration' => RegistrationType::class,
            'emergency_capable' => 'boolean',
            'safety_advice' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
