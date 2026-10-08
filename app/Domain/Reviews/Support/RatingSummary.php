<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Support;

use App\Models\Pro;
use App\Models\Review;

/** A pro's average rating from visible reviews; null until they have one (nothing is invented, spec 025 AC8). */
final class RatingSummary
{
    /** @return array{average: float, count: int}|null */
    public static function for(Pro $pro): ?array
    {
        $row = Review::query()->visible()->where('pro_id', $pro->id)->selectRaw('count(*) as total, avg(rating) as average')->first();

        if (! $row instanceof Review || (int) $row->getAttribute('total') === 0) {
            return null;
        }

        return ['average' => round((float) $row->getAttribute('average'), 1), 'count' => (int) $row->getAttribute('total')];
    }

    /** "4.8 (12 reviews)" or "New on GetSorted". */
    public static function label(Pro $pro): string
    {
        $summary = self::for($pro);

        if ($summary === null) {
            return (string) __('New on GetSorted');
        }

        return '★ '.number_format($summary['average'], 1).' ('.trans_choice(':count review|:count reviews', $summary['count']).')';
    }
}
