<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One address-provider call and how it ended. Never holds address text (spec 015, AC11).
 *
 * @property int $id
 * @property string $purpose autocomplete|resolve
 * @property string $outcome ok|error|throttled
 * @property int $latency_ms
 * @property CarbonImmutable $created_at
 */
final class GeocoderUsage extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    public const int RETENTION_DAYS = 90;

    protected $table = 'geocoder_usage';

    /** @var list<string> */
    protected $fillable = ['purpose', 'outcome', 'latency_ms'];

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['latency_ms' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
