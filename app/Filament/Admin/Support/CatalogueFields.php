<?php

declare(strict_types=1);

namespace App\Filament\Admin\Support;

use App\Domain\Catalogue\Support\CatalogueKey;
use Closure;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Shared catalogue form fields. */
final class CatalogueFields
{
    /**
     * A key that is set once and never changes (spec 003, AC7).
     *
     * @template TModel of Model
     *
     * @param  Closure(): Builder<TModel>  $siblings  query for records whose keys must not clash
     */
    public static function key(Closure $siblings): TextInput
    {
        return TextInput::make('key')
            ->label(__('Key'))
            ->required()
            ->maxLength(64)
            ->regex(CatalogueKey::PATTERN)
            ->validationMessages(['regex' => __('Use lowercase letters, digits and underscores, starting with a letter.')])
            ->helperText(__('Keys never change; they link jobs and pros to this item.'))
            ->disabledOn('edit')
            ->dehydrated(fn (string $operation): bool => $operation === 'create')
            ->rules([
                fn (string $operation): Closure => function (string $attribute, mixed $value, Closure $fail) use ($siblings, $operation): void {
                    if ($operation === 'create' && $siblings()->where('key', $value)->exists()) {
                        $fail(__('This key is already used here.'));
                    }
                },
            ]);
    }
}
