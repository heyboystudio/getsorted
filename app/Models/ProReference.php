<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pros\Enums\ReferenceOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone vetting admins phone about a pro's work (spec 008). Third-party
 * personal data: phone encrypted, never shown outside vetting.
 *
 * @property int $id
 * @property int $pro_id
 * @property string $name
 * @property string $phone_e164
 * @property string $relationship
 * @property ReferenceOutcome $outcome
 * @property string|null $note
 * @property int|null $checked_by
 * @property CarbonImmutable|null $checked_at
 */
final class ProReference extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'phone_e164', 'relationship'];

    /** @var list<string> */
    protected $hidden = ['phone_e164', 'note'];

    /** @var array<string, mixed> */
    protected $attributes = ['outcome' => 'pending'];

    /** @return BelongsTo<Pro, $this> */
    public function pro(): BelongsTo
    {
        return $this->belongsTo(Pro::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'phone_e164' => 'encrypted',
            'outcome' => ReferenceOutcome::class,
            'checked_at' => 'immutable_datetime',
        ];
    }
}
