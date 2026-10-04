<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Settings\AiSettings;
use Carbon\CarbonImmutable;
use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One assistant call: what it cost and how it ended. Never holds customer text (spec 007, AC13).
 *
 * @property int $id
 * @property AiPurpose $purpose
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $latency_ms
 * @property AiOutcome $outcome
 * @property int|null $service_job_id
 * @property CarbonImmutable $created_at
 */
final class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory, Prunable;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    /** @var list<string> */
    protected $fillable = ['purpose', 'provider', 'model', 'input_tokens', 'output_tokens', 'latency_ms', 'outcome', 'service_job_id'];

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(app(AiSettings::class)->usage_retention_days));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => AiPurpose::class,
            'outcome' => AiOutcome::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
