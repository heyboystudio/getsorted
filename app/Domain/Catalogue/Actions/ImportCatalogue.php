<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Domain\Catalogue\Exceptions\InvalidCatalogueDefinition;
use App\Domain\Catalogue\Support\CatalogueDefinitionValidator;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;

final readonly class ImportCatalogue
{
    public function __construct(
        private CatalogueDefinitionValidator $validator,
    ) {}

    /**
     * Loads validated trade definitions, adding only trades whose keys are new.
     * Existing rows are never changed: after the first seed the admin panel is
     * the source of truth (spec 003, decision 1).
     *
     * @param  array<string, mixed>  $definitions  parsed YAML keyed by file name, in file order
     *
     * @throws InvalidCatalogueDefinition
     */
    public function handle(array $definitions): void
    {
        $this->validator->validate($definitions);

        DB::transaction(function () use ($definitions): void {
            $tradeSort = (int) Trade::query()->max('sort');

            /** @var array<string, mixed> $definition */
            foreach ($definitions as $definition) {
                if (Trade::query()->where('key', $definition['trade'])->exists()) {
                    continue;
                }

                Trade::query()->create([
                    'key' => $definition['trade'],
                    'name' => $definition['name'],
                    'status' => TradeStatus::tryFrom((string) ($definition['status'] ?? '')) ?? TradeStatus::Demo,
                    'registration' => isset($definition['registration']) ? RegistrationType::from($definition['registration']) : null,
                    'is_active' => true,
                    'sort' => ++$tradeSort,
                ]);
            }
        });
    }
}
