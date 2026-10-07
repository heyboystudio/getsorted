<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Domain\Accounts\Enums\DataRequestType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request to download or delete their data. Admins fulfil it by hand (spec 021);
 * only the account actions change it.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property DataRequestType $type
 * @property DataRequestStatus $status
 * @property int|null $handled_by
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable $created_at
 */
final class DataRequest extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DataRequestType::class,
            'status' => DataRequestStatus::class,
            'handled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
