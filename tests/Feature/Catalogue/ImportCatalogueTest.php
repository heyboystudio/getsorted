<?php

declare(strict_types=1);

use App\Domain\Catalogue\Actions\ImportCatalogue;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Domain\Catalogue\Exceptions\InvalidCatalogueDefinition;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\Trade;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
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
        'services' => [[
            'key' => 'leak_repair',
            'name' => 'Leak repair',
            'description' => 'Fix a leak.',
            'requires_registration' => null,
            'emergency_capable' => true,
            'questions' => [[
                'key' => 'severity',
                'prompt' => 'How bad is it?',
                'type' => 'single_choice',
                'options' => ['Dripping', 'Flooding'],
                'required' => true,
                'flags' => ['urgent_if' => ['Flooding']],
            ]],
            'safety_advice' => ['Close the stopcock.'],
        ]],
    ], $overrides);
}

function importDefinitions(array ...$definitions): void
{
    app(ImportCatalogue::class)->handle(array_combine(
        array_map(fn (int $i): string => "file{$i}.yaml", array_keys($definitions)),
        $definitions,
    ));
}

it('seeds every trade, service and question from the YAML files (AC1)', function (): void {
    $this->seed(CatalogueSeeder::class);

    expect(Trade::query()->orderBy('sort')->pluck('key')->all())->toBe(['electrical', 'painting', 'plumbing', 'tiling'])
        ->and(Service::query()->count())->toBe(16)
        ->and(ScopingQuestion::query()->count())->toBe(26);

    $plumbing = Trade::query()->where('key', 'plumbing')->sole();
    expect($plumbing->name)->toBe('Plumbing')->and($plumbing->status)->toBe(TradeStatus::Demo)->and($plumbing->is_active)->toBeTrue();

    $geyser = $plumbing->services()->where('key', 'geyser')->sole();
    expect($geyser->requires_registration)->toBe(RegistrationType::Pirb)
        ->and($geyser->emergency_capable)->toBeTrue()
        ->and($plumbing->services()->orderBy('sort')->pluck('key')->first())->toBe('leak_repair');

    $leak = $plumbing->services()->where('key', 'leak_repair')->sole();
    $severity = $leak->questions()->where('key', 'severity')->sole();
    expect($leak->safety_advice)->toBe(['If water is flooding, close the main stopcock first.'])
        ->and($severity->type)->toBe(QuestionType::SingleChoice)
        ->and($severity->options)->toBe(['Dripping', 'Steady flow', 'Flooding'])
        ->and($severity->required)->toBeTrue()
        ->and($severity->flags)->toBe(['urgent_if' => ['Flooding']])
        ->and($leak->questions()->orderBy('sort')->pluck('key')->all())->toBe(['leak_location', 'severity']);

    $points = ScopingQuestion::query()->where('key', 'count')->sole();
    expect($points->type)->toBe(QuestionType::Number)->and($points->options)->toBe([])->and($points->required)->toBeFalse();
});

it('rejects invalid YAML with a clear message and writes nothing (AC2)', function (array $definition, string $message): void {
    expect(fn () => importDefinitions(tradeYaml(['trade' => 'electrical', 'name' => 'Electrical', 'services' => [['key' => 'ok_service', 'name' => 'OK', 'questions' => []]]]), $definition))
        ->toThrow(InvalidCatalogueDefinition::class, $message);

    expect(Trade::query()->count())->toBe(0)->and(Service::query()->count())->toBe(0);
})->with([
    'unknown type' => [tradeYaml(['services' => [['questions' => [['type' => 'dropdown']]]]]), 'file1.yaml: plumbing.leak_repair.severity: unknown question type "dropdown"'],
    'missing name' => [tradeYaml(['services' => [['name' => '']]]), 'file1.yaml: plumbing.leak_repair: name is required'],
    'urgent_if not an option' => [tradeYaml(['services' => [['questions' => [['flags' => ['urgent_if' => ['Burst']]]]]]]), 'file1.yaml: plumbing.leak_repair.severity: urgent_if value "Burst" is not one of the options'],
    'bad key' => [tradeYaml(['services' => [['key' => 'Leak Repair']]]), 'file1.yaml: plumbing.Leak Repair: key must be lowercase letters, digits and underscores'],
    'unknown registration' => [tradeYaml(['services' => [['requires_registration' => 'gas_licence']]]), 'file1.yaml: plumbing.leak_repair: unknown registration "gas_licence"'],
    'options on yes/no' => [tradeYaml(['services' => [['questions' => [['type' => 'yes_no', 'flags' => []]]]]]), 'file1.yaml: plumbing.leak_repair.severity: yes_no questions cannot have options'],
]);

it('rejects duplicate keys (AC2)', function (): void {
    $duplicateService = tradeYaml();
    $duplicateService['services'][] = $duplicateService['services'][0];

    expect(fn () => importDefinitions($duplicateService))->toThrow(InvalidCatalogueDefinition::class, 'duplicate service key "leak_repair"');
    expect(fn () => importDefinitions(tradeYaml(), tradeYaml()))->toThrow(InvalidCatalogueDefinition::class, 'duplicate trade key "plumbing"');
    expect(Trade::query()->count())->toBe(0);
});

it('only adds new keys when run again, keeping admin edits (AC3)', function (): void {
    importDefinitions(tradeYaml());
    Service::query()->sole()->update(['name' => 'Leaks (edited by admin)']);
    ScopingQuestion::query()->sole()->update(['prompt' => 'Edited prompt']);

    $next = tradeYaml(['name' => 'Plumbing renamed in YAML']);
    $next['services'][0]['questions'][] = ['key' => 'photos_ok', 'prompt' => 'Can you send photos?', 'type' => 'yes_no', 'required' => false];
    $next['services'][] = ['key' => 'blocked_drain', 'name' => 'Blocked drain', 'questions' => []];
    importDefinitions($next);

    expect(Trade::query()->sole()->name)->toBe('Plumbing')
        ->and(Service::query()->where('key', 'leak_repair')->sole()->name)->toBe('Leaks (edited by admin)')
        ->and(ScopingQuestion::query()->where('key', 'severity')->sole()->prompt)->toBe('Edited prompt')
        ->and(ScopingQuestion::query()->where('key', 'photos_ok')->exists())->toBeTrue()
        ->and(Service::query()->where('key', 'blocked_drain')->exists())->toBeTrue();
});

it('is part of the database seeder and safe to run repeatedly (AC4)', function (): void {
    $this->seed();
    $this->seed();

    expect(Trade::query()->count())->toBe(4)->and(Service::query()->count())->toBe(16);
});

it('keeps service codes unique within a trade in the database', function (): void {
    importDefinitions(tradeYaml());
    $service = Service::query()->sole();

    Service::factory()->for($service->trade)->create(['key' => 'leak_repair']);
})->throws(UniqueConstraintViolationException::class);

it('keeps question codes unique within a service in the database', function (): void {
    importDefinitions(tradeYaml());

    ScopingQuestion::factory()->for(Service::query()->sole())->create(['key' => 'severity']);
})->throws(UniqueConstraintViolationException::class);

it('writes catalogue changes to the audit log with before and after (AC11)', function (): void {
    importDefinitions(tradeYaml());
    $service = Service::query()->sole();

    $service->update(['name' => 'Leak repairs']);

    $entry = Activity::query()->where('subject_type', $service->getMorphClass())->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->attribute_changes['old']['name'] ?? $entry->properties['old']['name'] ?? null)->toBe('Leak repair')
        ->and($entry->attribute_changes['attributes']['name'] ?? $entry->properties['attributes']['name'] ?? null)->toBe('Leak repairs');
});
