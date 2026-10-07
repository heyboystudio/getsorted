<?php

declare(strict_types=1);

use App\Domain\Catalogue\Actions\ImportCatalogue;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Domain\Catalogue\Exceptions\InvalidCatalogueDefinition;
use App\Models\Trade;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $overrides */
function tradeYaml(array $overrides = []): array
{
    return array_replace_recursive([
        'trade' => 'plumbing',
        'name' => 'Plumbing',
        'status' => 'demo',
        'registration' => 'pirb',
    ], $overrides);
}

function importDefinitions(array ...$definitions): void
{
    app(ImportCatalogue::class)->handle(array_combine(
        array_map(fn (int $i): string => "file{$i}.yaml", array_keys($definitions)),
        $definitions,
    ));
}

it('seeds every trade from the YAML files with its registration (AC1, spec 020)', function (): void {
    $this->seed(CatalogueSeeder::class);

    expect(Trade::query()->orderBy('sort')->pluck('key')->all())->toBe(['electrical', 'painting', 'plumbing', 'tiling']);

    $plumbing = tradeOf('plumbing');
    expect($plumbing->name)->toBe('Plumbing')->and($plumbing->status)->toBe(TradeStatus::Demo)->and($plumbing->is_active)->toBeTrue()
        ->and($plumbing->registration)->toBe(RegistrationType::Pirb);

    expect(tradeOf('electrical')->registration)->toBe(RegistrationType::ElectricalRegisteredPerson)
        ->and(tradeOf('painting')->registration)->toBeNull();
});

it('rejects invalid YAML with a clear message and writes nothing (AC2)', function (array $definition, string $message): void {
    expect(fn () => importDefinitions(tradeYaml(['trade' => 'electrical', 'name' => 'Electrical', 'registration' => null]), $definition))
        ->toThrow(InvalidCatalogueDefinition::class, $message);

    expect(Trade::query()->count())->toBe(0);
})->with([
    'missing name' => [tradeYaml(['name' => '']), 'file1.yaml: plumbing: name is required'],
    'bad key' => [tradeYaml(['trade' => 'Leak Trade']), 'file1.yaml: Leak Trade: key must be lowercase letters, digits and underscores'],
    'unknown status' => [tradeYaml(['status' => 'beta']), 'file1.yaml: plumbing: unknown status "beta"'],
    'unknown registration' => [tradeYaml(['registration' => 'gas_licence']), 'file1.yaml: plumbing: unknown registration "gas_licence"'],
]);

it('rejects duplicate trade keys (AC2)', function (): void {
    expect(fn () => importDefinitions(tradeYaml(), tradeYaml()))->toThrow(InvalidCatalogueDefinition::class, 'duplicate trade key "plumbing"');
    expect(Trade::query()->count())->toBe(0);
});

it('only adds new trades when run again, keeping admin edits (AC3)', function (): void {
    importDefinitions(tradeYaml());
    Trade::query()->sole()->update(['name' => 'Plumbing (edited by an admin)']);

    importDefinitions(tradeYaml(['name' => 'Plumbing renamed in YAML']), tradeYaml(['trade' => 'tiling', 'name' => 'Tiling', 'registration' => null]));

    expect(tradeOf('plumbing')->name)->toBe('Plumbing (edited by an admin)')
        ->and(Trade::query()->where('key', 'tiling')->exists())->toBeTrue();
});

it('is part of the database seeder and safe to run repeatedly (AC4)', function (): void {
    $this->seed();
    $this->seed();

    expect(Trade::query()->count())->toBe(4);
});

it('writes catalogue changes to the audit log with before and after (AC11)', function (): void {
    importDefinitions(tradeYaml());
    $trade = Trade::query()->sole();

    $trade->update(['name' => 'Plumbers']);

    $entry = Activity::query()->where('subject_type', $trade->getMorphClass())->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->attribute_changes['old']['name'] ?? $entry->properties['old']['name'] ?? null)->toBe('Plumbing')
        ->and($entry->attribute_changes['attributes']['name'] ?? $entry->properties['attributes']['name'] ?? null)->toBe('Plumbers');
});

it('never lets a trade key change', function (): void {
    importDefinitions(tradeYaml());

    expect(fn () => Trade::query()->sole()->update(['key' => 'renamed']))->toThrow(LogicException::class, 'Catalogue keys cannot be changed.');
});
