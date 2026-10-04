<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalogue\Actions\ImportCatalogue;
use Illuminate\Database\Seeder;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads docs/product/scoping/*.yaml (spec 003). Safe in every environment and
 * on every run: it only adds new keys and never overwrites admin edits.
 */
final class CatalogueSeeder extends Seeder
{
    public function run(ImportCatalogue $importCatalogue): void
    {
        $files = glob(base_path('docs/product/scoping/*.yaml')) ?: [];
        sort($files);

        $definitions = [];

        foreach ($files as $file) {
            $definitions[basename($file)] = Yaml::parseFile($file);
        }

        $importCatalogue->handle($definitions);
    }
}
