<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Domain\Catalogue\Exceptions\InvalidCatalogueDefinition;

/**
 * Checks parsed scoping YAML (docs/product/scoping/README.md) before anything is
 * written. Messages name the file and the trade.service.question path.
 */
final class CatalogueDefinitionValidator
{
    /**
     * @param  array<string, mixed>  $definitions  parsed YAML keyed by file name
     *
     * @throws InvalidCatalogueDefinition
     */
    public function validate(array $definitions): void
    {
        $tradeKeys = [];

        foreach ($definitions as $file => $trade) {
            if (! is_array($trade)) {
                $this->fail($file, '', 'not a trade definition');
            }

            $tradeKey = $this->key($file, '', $trade['trade'] ?? null);
            $this->required($file, $tradeKey, $trade, 'name');

            if (isset($trade['status']) && TradeStatus::tryFrom((string) $trade['status']) === null) {
                $this->fail($file, $tradeKey, 'unknown status "'.$trade['status'].'"');
            }

            if (in_array($tradeKey, $tradeKeys, true)) {
                $this->fail($file, $tradeKey, 'duplicate trade key "'.$tradeKey.'"');
            }

            $tradeKeys[] = $tradeKey;
            $serviceKeys = [];

            foreach ($this->list($file, $tradeKey, $trade, 'services') as $service) {
                $serviceKey = $this->key($file, $tradeKey.'.', $service['key'] ?? null);
                $path = $tradeKey.'.'.$serviceKey;

                if (in_array($serviceKey, $serviceKeys, true)) {
                    $this->fail($file, $tradeKey, 'duplicate service key "'.$serviceKey.'"');
                }

                $serviceKeys[] = $serviceKey;
                $this->required($file, $path, $service, 'name');

                $registration = $service['requires_registration'] ?? null;

                if ($registration !== null && RegistrationType::tryFrom((string) $registration) === null) {
                    $this->fail($file, $path, 'unknown registration "'.$registration.'"');
                }

                foreach ($this->list($file, $path, $service, 'safety_advice') as $advice) {
                    if (! is_string($advice) || trim($advice) === '') {
                        $this->fail($file, $path, 'safety_advice lines must be text');
                    }
                }

                $this->validateQuestions($file, $path, $this->list($file, $path, $service, 'questions'));
            }
        }
    }

    /**
     * @param  list<mixed>  $questions
     */
    private function validateQuestions(string $file, string $servicePath, array $questions): void
    {
        $questionKeys = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                $this->fail($file, $servicePath, 'questions must be a list of definitions');
            }

            $questionKey = $this->key($file, $servicePath.'.', $question['key'] ?? null);
            $path = $servicePath.'.'.$questionKey;

            if (in_array($questionKey, $questionKeys, true)) {
                $this->fail($file, $servicePath, 'duplicate question key "'.$questionKey.'"');
            }

            $questionKeys[] = $questionKey;
            $this->required($file, $path, $question, 'prompt');

            $type = QuestionType::tryFrom((string) ($question['type'] ?? ''));

            if ($type === null) {
                $this->fail($file, $path, 'unknown question type "'.($question['type'] ?? '').'"');
            }

            $options = $this->list($file, $path, $question, 'options');
            $problem = self::optionsProblem($type, $options);

            if ($problem !== null) {
                $this->fail($file, $path, $problem);
            }

            $problem = self::urgentIfProblem($type, $options, $this->list($file, $path, $question['flags'] ?? [], 'urgent_if'));

            if ($problem !== null) {
                $this->fail($file, $path, $problem);
            }
        }
    }

    /**
     * Shared with the admin forms (spec 003, AC9).
     *
     * @param  list<mixed>  $options
     */
    public static function optionsProblem(QuestionType $type, array $options): ?string
    {
        if (! $type->hasOptions()) {
            return $options === [] ? null : $type->value.' questions cannot have options';
        }

        if (count($options) < 2) {
            return $type->value.' questions need at least two options';
        }

        foreach ($options as $option) {
            if (! is_string($option) && ! is_int($option)) {
                return 'options must be text';
            }
        }

        return count(array_unique(array_map(strval(...), $options))) === count($options) ? null : 'options must be unique';
    }

    /**
     * @param  list<mixed>  $options
     * @param  list<mixed>  $urgentIf
     */
    public static function urgentIfProblem(QuestionType $type, array $options, array $urgentIf): ?string
    {
        $allowed = $type->urgentCandidates(array_map(strval(...), $options));

        foreach ($urgentIf as $value) {
            if (! in_array((string) $value, $allowed, true)) {
                return 'urgent_if value "'.$value.'" is not one of the options';
            }
        }

        return null;
    }

    private function key(string $file, string $prefix, mixed $key): string
    {
        $key = is_string($key) ? $key : '';

        if (! CatalogueKey::isValid($key)) {
            $this->fail($file, $prefix.$key, 'key must be lowercase letters, digits and underscores');
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function required(string $file, string $path, array $definition, string $field): void
    {
        if (! is_string($definition[$field] ?? null) || trim($definition[$field]) === '') {
            $this->fail($file, $path, $field.' is required');
        }
    }

    /**
     * @return list<mixed>
     */
    private function list(string $file, string $path, mixed $definition, string $field): array
    {
        $value = is_array($definition) ? ($definition[$field] ?? []) : [];

        if (! is_array($value) || ! array_is_list($value)) {
            $this->fail($file, $path, $field.' must be a list');
        }

        return $value;
    }

    private function fail(string $file, string $path, string $message): never
    {
        throw new InvalidCatalogueDefinition(trim($file.': '.($path === '' ? '' : $path.': ').$message));
    }
}
