<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Properties\Enums\Region;
use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\SuburbFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property Region $region
 * @property string $municipality
 * @property Point $centroid
 * @property MultiPolygon|null $boundary
 * @property bool $is_active
 * @property list<string> $aliases
 */
final class Suburb extends Model
{
    /** @use HasFactory<SuburbFactory> */
    use HasFactory, LogsActivity;

    /** @var list<string> */
    protected $fillable = ['slug', 'name', 'region', 'municipality', 'centroid', 'boundary', 'is_active', 'aliases'];

    /** URLs use the slug, never the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** Slugs are stable identifiers, like catalogue keys. */
    protected static function booted(): void
    {
        self::updating(function (self $suburb): void {
            if ($suburb->isDirty('slug')) {
                throw new LogicException('Suburb slugs cannot be changed.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['slug', 'name', 'region', 'municipality', 'is_active'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'region' => Region::class,
            'centroid' => Point::class,
            'boundary' => MultiPolygon::class,
            'is_active' => 'boolean',
            'aliases' => 'array',
        ];
    }
}
