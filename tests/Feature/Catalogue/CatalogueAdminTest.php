<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Catalogue\Enums\QuestionType;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Filament\Admin\Resources\Trades\Pages\CreateTrade;
use App\Filament\Admin\Resources\Trades\Pages\EditTrade;
use App\Filament\Admin\Resources\Trades\Pages\ListTrades;
use App\Filament\Admin\Resources\Trades\Pages\ViewTrade;
use App\Filament\Admin\Resources\Trades\RelationManagers\ServicesRelationManager;
use App\Filament\Admin\Resources\Trades\Resources\Services\Pages\CreateService;
use App\Filament\Admin\Resources\Trades\Resources\Services\Pages\EditService;
use App\Filament\Admin\Resources\Trades\Resources\Services\Pages\ViewService;
use App\Filament\Admin\Resources\Trades\Resources\Services\RelationManagers\QuestionsRelationManager;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    Filament::setCurrentPanel('admin');
});

function catalogueAdmin(Role $role = Role::AdminSuper): User
{
    $admin = User::factory()->create();
    $admin->assignRole($role->value);
    test()->actingAs($admin);

    return $admin;
}

function plumbing(): Trade
{
    return Trade::query()->where('key', 'plumbing')->sole();
}

function leakRepair(): Service
{
    return plumbing()->services()->where('key', 'leak_repair')->sole();
}

it('lists trades with their service counts (AC5)', function (): void {
    catalogueAdmin();

    Livewire::test(ListTrades::class)
        ->assertCanSeeTableRecords(Trade::all())
        ->assertTableColumnStateSet('services_count', 4, plumbing())
        ->assertSee('Plumbing');
});

it("shows a trade's services and a service's questions in order (AC5, AC10)", function (): void {
    catalogueAdmin();
    $trade = plumbing();

    Livewire::test(ServicesRelationManager::class, ['ownerRecord' => $trade, 'pageClass' => EditTrade::class])
        ->assertCanSeeTableRecords($trade->services, inOrder: true);

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => EditService::class])
        ->assertCanSeeTableRecords(leakRepair()->questions, inOrder: true)
        ->assertSee('Where is the leak coming from?');
});

it('edits a trade (AC6)', function (): void {
    catalogueAdmin(Role::AdminSupport);

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])
        ->fillForm(['name' => 'Plumbing & drains', 'status' => TradeStatus::Live->value, 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(plumbing())->name->toBe('Plumbing & drains')->status->toBe(TradeStatus::Live)->is_active->toBeFalse();
});

it('edits a service including safety advice (AC6)', function (): void {
    catalogueAdmin();
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(EditService::class, ['record' => 'leak_repair', 'parentRecord' => plumbing()])
        ->fillForm([
            'name' => 'Leaks',
            'description' => 'Any visible leak.',
            'requires_registration' => RegistrationType::Pirb->value,
            'emergency_capable' => false,
            'safety_advice' => [['line' => 'Turn off the water.'], ['line' => 'Keep away from wet electrics.']],
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(leakRepair()->fresh())
        ->name->toBe('Leaks')
        ->requires_registration->toBe(RegistrationType::Pirb)
        ->emergency_capable->toBeFalse()
        ->safety_advice->toBe(['Turn off the water.', 'Keep away from wet electrics.'])
        ->is_active->toBeFalse();

    $undoRepeaterFake();
});

it('edits a question (AC6)', function (): void {
    catalogueAdmin();
    $severity = leakRepair()->questions()->where('key', 'severity')->sole();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => EditService::class])
        ->callAction(TestAction::make('edit')->table($severity), data: [
            'prompt' => 'How bad is the leak?',
            'type' => QuestionType::SingleChoice->value,
            'options' => ['Dripping', 'Steady flow', 'Flooding', 'Burst pipe'],
            'required' => true,
            'flags' => ['urgent_if' => ['Flooding', 'Burst pipe']],
        ])
        ->assertHasNoFormErrors();

    expect($severity->fresh())
        ->prompt->toBe('How bad is the leak?')
        ->options->toBe(['Dripping', 'Steady flow', 'Flooding', 'Burst pipe'])
        ->flags->toBe(['urgent_if' => ['Flooding', 'Burst pipe']]);
});

it('adds a trade, a service and a question with new keys (AC7)', function (): void {
    catalogueAdmin();

    Livewire::test(CreateTrade::class)
        ->fillForm(['name' => 'Roofing', 'key' => 'roofing', 'status' => TradeStatus::Demo->value, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();
    $roofing = Trade::query()->where('key', 'roofing')->sole();

    Livewire::test(CreateService::class, ['parentRecord' => $roofing])
        ->fillForm(['name' => 'Leaking roof', 'key' => 'leak_repair', 'emergency_capable' => true, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();
    $service = $roofing->services()->where('key', 'leak_repair')->sole();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $service, 'pageClass' => EditService::class])
        ->callAction(TestAction::make('create')->table(), data: [
            'prompt' => 'Is water coming inside?',
            'key' => 'water_inside',
            'type' => QuestionType::YesNo->value,
            'required' => true,
            'flags' => ['urgent_if' => ['yes']],
        ])
        ->assertHasNoFormErrors();

    expect($service->questions()->sole())->key->toBe('water_inside')->options->toBe([])->flags->toBe(['urgent_if' => ['yes']]);
});

it('rejects badly formed and duplicate keys (AC7)', function (string $key): void {
    catalogueAdmin();

    Livewire::test(CreateService::class, ['parentRecord' => plumbing()])
        ->fillForm(['name' => 'Another', 'key' => $key, 'is_active' => true])
        ->call('create')->assertHasFormErrors(['key']);
})->with(['Has Spaces', 'UPPER', '1starts_with_digit', 'leak_repair']);

it('never changes a key after creation (AC7)', function (): void {
    catalogueAdmin();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])
        ->assertFormFieldIsDisabled('key')
        ->fillForm(['key' => 'renamed', 'name' => 'Plumbing'])
        ->call('save');

    expect(plumbing()->key)->toBe('plumbing');
    expect(fn () => plumbing()->update(['key' => 'renamed']))->toThrow(LogicException::class);
});

it('cannot delete trades or services, only switch them off (AC8)', function (): void {
    $admin = catalogueAdmin();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])->assertActionDoesNotExist('delete');
    Livewire::test(EditService::class, ['record' => 'leak_repair', 'parentRecord' => plumbing()])->assertActionDoesNotExist('delete');

    expect($admin->can('delete', plumbing()))->toBeFalse()->and($admin->can('delete', leakRepair()))->toBeFalse();
});

it('lets editors delete a question while no jobs exist (AC8)', function (): void {
    catalogueAdmin();
    $question = leakRepair()->questions()->first();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => EditService::class])
        ->callAction(TestAction::make('delete')->table($question));

    expect(ScopingQuestion::query()->find($question->id))->toBeNull();
});

it('validates options and urgent answers against the answer type (AC9)', function (array $data, array $errors): void {
    catalogueAdmin();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => EditService::class])
        ->callAction(TestAction::make('create')->table(), data: ['prompt' => 'Test?', 'key' => 'test_q', 'required' => false, ...$data])
        ->assertHasFormErrors($errors);

    expect(ScopingQuestion::query()->where('key', 'test_q')->exists())->toBeFalse();
})->with([
    'one option' => [['type' => 'single_choice', 'options' => ['Only one']], ['options']],
    'urgent value not an option' => [['type' => 'single_choice', 'options' => ['A', 'B'], 'flags' => ['urgent_if' => ['C']]], ['flags.urgent_if']],
    'yes/no urgent must be yes or no' => [['type' => 'yes_no', 'flags' => ['urgent_if' => ['maybe']]], ['flags.urgent_if']],
]);

it('writes admin edits to the audit log with the admin as causer (AC11)', function (): void {
    $admin = catalogueAdmin();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])->fillForm(['name' => 'Plumbing (edited)'])->call('save');

    $entry = Activity::query()->where('subject_type', plumbing()->getMorphClass())->where('event', 'updated')->latest('id')->firstOrFail();
    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->attribute_changes['attributes']['name'])->toBe('Plumbing (edited)')
        ->and($entry->attribute_changes['old']['name'])->toBe('Plumbing');
});

it('gives vetting and finance admins read-only access (AC12)', function (Role $role): void {
    $admin = catalogueAdmin($role);

    Livewire::test(ListTrades::class)->assertCanSeeTableRecords(Trade::all())->assertActionHidden('create');

    expect($admin->can('update', plumbing()))->toBeFalse()
        ->and($admin->can('create', Trade::class))->toBeFalse()
        ->and($admin->can('update', leakRepair()))->toBeFalse()
        ->and($admin->can('delete', leakRepair()->questions()->first()))->toBeFalse();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])->assertForbidden();
})->with([Role::AdminVetting, Role::AdminFinance]);

it('lets super-admin and support edit (AC12)', function (Role $role): void {
    $admin = catalogueAdmin($role);

    expect($admin->can('update', plumbing()))->toBeTrue()->and($admin->can('create', Service::class))->toBeTrue();
})->with([Role::AdminSuper, Role::AdminSupport]);

it('keeps customers and pros out of the catalogue (AC13)', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/trades')->assertForbidden();
    expect($user->can('viewAny', Trade::class))->toBeFalse();
})->with(['customer', 'pro']);

it('uses keys, not numeric ids, in admin URLs', function (): void {
    expect(EditTrade::getUrl(['record' => plumbing()]))->toEndWith('/admin/trades/plumbing/edit')
        ->and(EditService::getUrl(['record' => leakRepair(), 'trade' => plumbing()]))->toEndWith('/admin/trades/plumbing/services/leak_repair/edit');
});

it('keeps a question in place when it is edited (AC10)', function (): void {
    catalogueAdmin();
    $service = Service::query()->where('key', 'geyser')->sole();
    $before = $service->questions()->pluck('key')->all();
    $first = $service->questions()->get()->last();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $service, 'pageClass' => EditService::class])
        ->callAction(TestAction::make('edit')->table($first), data: ['prompt' => 'Edited?', 'type' => $first->type->value, 'options' => $first->options, 'required' => true])
        ->assertHasNoFormErrors();

    expect($service->questions()->pluck('key')->all())->toBe($before);
});

it('adds new trades, services and questions at the end of their lists', function (): void {
    catalogueAdmin();

    Livewire::test(CreateService::class, ['parentRecord' => plumbing()])
        ->fillForm(['name' => 'Taps', 'key' => 'taps', 'is_active' => true])->call('create')->assertHasNoFormErrors();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => EditService::class])
        ->callAction(TestAction::make('create')->table(), data: ['prompt' => 'Photos?', 'key' => 'photos', 'type' => 'yes_no', 'required' => false]);

    expect(plumbing()->services()->pluck('key')->last())->toBe('taps')
        ->and(leakRepair()->questions()->pluck('key')->last())->toBe('photos');
});

it('reorders services and questions and writes it to the audit log (AC10, AC11)', function (): void {
    $admin = catalogueAdmin();
    $questions = leakRepair()->questions()->get();
    $reversed = $questions->reverse()->map(fn (ScopingQuestion $question): string => (string) $question->getKey())->values()->all();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => EditService::class])
        ->call('reorderTable', $reversed);

    expect(leakRepair()->questions()->pluck('key')->all())->toBe($questions->reverse()->pluck('key')->values()->all());

    $entry = Activity::query()->where('event', 'reordered')->sole();
    expect($entry->causer_id)->toBe($admin->id)->and($entry->subject_id)->toBe(leakRepair()->id);
});

it('does not let view-only admins reorder', function (): void {
    catalogueAdmin(Role::AdminVetting);
    $before = leakRepair()->questions()->pluck('key')->all();

    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => ViewService::class])
        ->call('reorderTable', array_reverse(leakRepair()->questions()->pluck('id')->map(fn ($id): string => (string) $id)->all()));

    expect(leakRepair()->questions()->pluck('key')->all())->toBe($before)
        ->and(Activity::query()->where('event', 'reordered')->exists())->toBeFalse();
});

it('lets view-only admins open trades and services and see their contents (AC12)', function (): void {
    catalogueAdmin(Role::AdminFinance);

    Livewire::test(ViewTrade::class, ['record' => 'plumbing'])->assertOk()->assertActionHidden('edit');
    Livewire::test(ServicesRelationManager::class, ['ownerRecord' => plumbing(), 'pageClass' => ViewTrade::class])
        ->assertCanSeeTableRecords(plumbing()->services);
    Livewire::test(ViewService::class, ['record' => 'leak_repair', 'parentRecord' => plumbing()])->assertOk();
    Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => leakRepair(), 'pageClass' => ViewService::class])
        ->assertCanSeeTableRecords(leakRepair()->questions)
        ->assertActionHidden(TestAction::make('create')->table());
});
