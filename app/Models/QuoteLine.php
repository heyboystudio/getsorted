<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Quotes\Enums\LineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quote_id
 * @property LineKind $kind
 * @property string $description
 * @property string $quantity
 * @property int $unit_price_cents
 * @property int $line_total_cents
 * @property int $sort
 */
final class QuoteLine extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => LineKind::class,
            'quantity' => 'decimal:2',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
            'sort' => 'integer',
        ];
    }
}
