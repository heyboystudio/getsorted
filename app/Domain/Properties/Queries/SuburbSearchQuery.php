<?php

declare(strict_types=1);

namespace App\Domain\Properties\Queries;

use App\Models\Suburb;
use Illuminate\Support\Collection;

final class SuburbSearchQuery
{
    private const int LIMIT = 8;

    /**
     * Suburbs whose name has a word starting with the search text, active ones
     * first (spec 004: "morn" finds Morningside, "north" finds Durban North).
     *
     * @return Collection<int, Suburb>
     */
    public function handle(string $search): Collection
    {
        $search = trim($search);

        if ($search === '') {
            return new Collection;
        }

        $escaped = addcslashes(mb_strtolower($search), '\\%_');

        return Suburb::query()
            ->where(function ($query) use ($escaped): void {
                $query->whereRaw('lower(name) like ?', [$escaped.'%'])
                    ->orWhereRaw('lower(name) like ?', ['% '.$escaped.'%']);
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
    }
}
