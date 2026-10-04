<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Data\MessageChannel;
use App\Domain\Accounts\Enums\OtpPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * @property int $id
 * @property string $phone_e164
 * @property string $code_hash
 * @property MessageChannel $channel
 * @property OtpPurpose $purpose
 * @property CarbonImmutable $expires_at
 * @property int $attempts
 * @property CarbonImmutable|null $consumed_at
 * @property string|null $ip
 */
final class PhoneOtp extends Model
{
    use Prunable;

    /** @var list<string> */
    protected $fillable = ['phone_e164', 'code_hash', 'channel', 'purpose', 'expires_at', 'attempts', 'consumed_at', 'ip'];

    /** @var list<string> */
    protected $hidden = ['code_hash'];

    /**
     * POPIA retention: OTP records are deleted after the configured number of days.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('sortd.otp.retention_days')));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'purpose' => OtpPurpose::class,
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
