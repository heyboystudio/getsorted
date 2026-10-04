<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalogue\Enums\QuestionType;
use Database\Factories\ScopingQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $service_id
 * @property string $key
 * @property string $prompt
 * @property QuestionType $type
 * @property list<string> $options
 * @property bool $required
 * @property array{urgent_if?: list<string>} $flags
 * @property int $sort
 */
final class ScopingQuestion extends Model
{
    /** @use HasFactory<ScopingQuestionFactory> */
    use HasFactory, LogsActivity;

    /** @var list<string> */
    protected $fillable = ['key', 'prompt', 'type', 'options', 'required', 'flags', 'sort'];

    /** @var array<string, mixed> */
    protected $attributes = ['options' => '[]', 'flags' => '{}'];

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
            'type' => QuestionType::class,
            'options' => 'array',
            'required' => 'boolean',
            'flags' => 'array',
            'sort' => 'integer',
        ];
    }
}
