<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property TradeStatus $status
 * @property RegistrationType|null $registration the registration a pro can verify for this trade (a badge, never a gate)
 * @property bool $is_active
 * @property int $sort
 */
final class Trade extends Model
{
    /** @use HasFactory<TradeFactory> */
    use HasFactory, LogsActivity;

    /** @var list<string> */
    protected $fillable = ['key', 'name', 'status', 'registration', 'is_active', 'sort'];

    /** @return BelongsToMany<Pro, $this> */
    public function pros(): BelongsToMany
    {
        return $this->belongsToMany(Pro::class, 'pro_trades');
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
            'status' => TradeStatus::class,
            'registration' => RegistrationType::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
