<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Properties\Enums\PropertyType;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A place where work is done. The street address is encrypted and only shown
 * to its owner (and, later, to the pro whose quote is accepted).
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $label
 * @property string $street_address
 * @property int $suburb_id
 * @property Point|null $location
 * @property string|null $postal_code
 * @property PropertyType $property_type
 */
final class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['label', 'street_address', 'postal_code', 'property_type'];

    /** @var list<string> */
    protected $hidden = ['street_address'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Suburb, $this> */
    public function suburb(): BelongsTo
    {
        return $this->belongsTo(Suburb::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'street_address' => 'encrypted',
            'location' => Point::class,
            'property_type' => PropertyType::class,
        ];
    }
}
