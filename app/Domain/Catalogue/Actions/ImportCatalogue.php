<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Actions;

use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Domain\Catalogue\Exceptions\InvalidCatalogueDefinition;
use App\Domain\Catalogue\Support\CatalogueDefinitionValidator;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;

final readonly class ImportCatalogue
{
    public function __construct(
        private CatalogueDefinitionValidator $validator,
    ) {}

    /**
     * Loads validated scoping definitions, adding only trades, services and
     * questions whose keys are new. Existing rows are never changed: after the
     * first seed the admin panel is the source of truth (spec 003, decision 1).
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
                $trade = Trade::query()->where('key', $definition['trade'])->first()
                    ?? Trade::query()->create([
                        'key' => $definition['trade'],
                        'name' => $definition['name'],
                        'status' => TradeStatus::tryFrom((string) ($definition['status'] ?? '')) ?? TradeStatus::Demo,
                        'is_active' => true,
                        'sort' => ++$tradeSort,
                    ]);

                $this->importServices($trade, $definition['services'] ?? []);
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $services
     */
    private function importServices(Trade $trade, array $services): void
    {
        $sort = (int) $trade->services()->max('sort');

        foreach ($services as $definition) {
            $service = $trade->services()->where('key', $definition['key'])->first()
                ?? $trade->services()->create([
                    'key' => $definition['key'],
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'requires_registration' => isset($definition['requires_registration']) ? RegistrationType::from($definition['requires_registration']) : null,
                    'emergency_capable' => (bool) ($definition['emergency_capable'] ?? false),
                    'safety_advice' => array_values($definition['safety_advice'] ?? []),
                    'is_active' => true,
                    'sort' => ++$sort,
                ]);

            $this->importQuestions($service, $definition['questions'] ?? []);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    private function importQuestions(Service $service, array $questions): void
    {
        $sort = (int) $service->questions()->max('sort');

        foreach ($questions as $definition) {
            if ($service->questions()->where('key', $definition['key'])->exists()) {
                continue;
            }

            $flags = [];

            if (($definition['flags']['urgent_if'] ?? []) !== []) {
                $flags['urgent_if'] = array_map(strval(...), $definition['flags']['urgent_if']);
            }

            /** @var ScopingQuestion $question */
            $question = $service->questions()->make([
                'key' => $definition['key'],
                'prompt' => $definition['prompt'],
                'type' => QuestionType::from($definition['type']),
                'options' => array_map(strval(...), $definition['options'] ?? []),
                'required' => (bool) ($definition['required'] ?? false),
                'flags' => $flags,
                'sort' => ++$sort,
            ]);
            $question->save();
        }
    }
}
