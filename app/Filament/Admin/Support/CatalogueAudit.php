<?php

declare(strict_types=1);

namespace App\Filament\Admin\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament saves a new order with one bulk query that fires no model events,
 * so reordering is written to the audit log here (spec 003, AC11).
 */
final class CatalogueAudit
{
    /**
     * @param  class-string<Model>  $model
     * @return Closure(array<int|string, int|string>): void
     */
    public static function reordered(string $model, ?Model $parent = null): Closure
    {
        return function (array $order) use ($model, $parent): void {
            $logger = activity()->causedBy(auth()->user())
                ->withProperties(['model' => class_basename($model), 'order' => array_values($order)]);

            if ($parent instanceof Model) {
                $logger->performedOn($parent);
            }

            $logger->event('reordered')->log('reordered '.str(class_basename($model))->plural()->snake(' '));
        };
    }

    /**
     * Next position at the end of a list.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    public static function nextSort(string $model, array $scope = []): int
    {
        return ((int) $model::query()->where($scope)->max('sort')) + 1;
    }
}
