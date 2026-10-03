<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Accounts\Enums\ConsentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property ConsentType $type
 * @property string $version
 * @property CarbonImmutable $granted_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property string|null $ip
 * @property string|null $user_agent
 */
final class Consent extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'type', 'version', 'granted_at', 'withdrawn_at', 'ip', 'user_agent'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'granted_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
