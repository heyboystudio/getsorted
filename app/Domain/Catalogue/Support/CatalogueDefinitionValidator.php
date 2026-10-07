<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Domain\Catalogue\Exceptions\InvalidCatalogueDefinition;

/**
 * Checks parsed trade YAML (docs/product/trades/README.md) before anything is
 * written. Messages name the file and the trade key.
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

            $tradeKey = $this->key($file, $trade['trade'] ?? null);
            $this->required($file, $tradeKey, $trade, 'name');

            if (isset($trade['status']) && TradeStatus::tryFrom((string) $trade['status']) === null) {
                $this->fail($file, $tradeKey, 'unknown status "'.$trade['status'].'"');
            }

            $registration = $trade['registration'] ?? null;

            if ($registration !== null && RegistrationType::tryFrom((string) $registration) === null) {
                $this->fail($file, $tradeKey, 'unknown registration "'.$registration.'"');
            }

            if (in_array($tradeKey, $tradeKeys, true)) {
                $this->fail($file, $tradeKey, 'duplicate trade key "'.$tradeKey.'"');
            }

            $tradeKeys[] = $tradeKey;
        }
    }

    private function key(string $file, mixed $key): string
    {
        $key = is_string($key) ? $key : '';

        if (! CatalogueKey::isValid($key)) {
            $this->fail($file, $key, 'key must be lowercase letters, digits and underscores');
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

    private function fail(string $file, string $path, string $message): never
    {
        throw new InvalidCatalogueDefinition(trim($file.': '.($path === '' ? '' : $path.': ').$message));
    }
}
