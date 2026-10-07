<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Catalogue\Enums\RegistrationType;
use App\Domain\Catalogue\Enums\TradeStatus;
use App\Filament\Admin\Resources\Trades\Pages\CreateTrade;
use App\Filament\Admin\Resources\Trades\Pages\EditTrade;
use App\Filament\Admin\Resources\Trades\Pages\ListTrades;
use App\Filament\Admin\Resources\Trades\Pages\ViewTrade;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
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

it('lists trades with their pro counts (AC5, spec 020)', function (): void {
    catalogueAdmin();
    proNear(['plumbing'], 1);

    Livewire::test(ListTrades::class)
        ->assertCanSeeTableRecords(Trade::all())
        ->assertTableColumnStateSet('pros_count', 1, plumbing())
        ->assertSee('Plumbing');
});

it('edits a trade including its registration, and has no safety advice field (AC6, decision 060)', function (): void {
    catalogueAdmin(Role::AdminSupport);

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])
        ->fillForm([
            'name' => 'Plumbing & drains', 'status' => TradeStatus::Live->value, 'is_active' => false,
            'registration' => RegistrationType::Pirb->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(plumbing())->name->toBe('Plumbing & drains')->status->toBe(TradeStatus::Live)->is_active->toBeFalse()
        ->registration->toBe(RegistrationType::Pirb);
    Livewire::test(EditTrade::class, ['record' => 'plumbing'])->assertDontSee('Safety advice');
});

it('adds a trade with a new key (AC7)', function (): void {
    catalogueAdmin();

    Livewire::test(CreateTrade::class)
        ->fillForm(['name' => 'Roofing', 'key' => 'roofing', 'status' => TradeStatus::Demo->value, 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    expect(Trade::query()->where('key', 'roofing')->sole()->name)->toBe('Roofing');
});

it('rejects badly formed and duplicate keys (AC7)', function (string $key): void {
    catalogueAdmin();

    Livewire::test(CreateTrade::class)
        ->fillForm(['name' => 'Another', 'key' => $key, 'status' => TradeStatus::Demo->value, 'is_active' => true])
        ->call('create')->assertHasFormErrors(['key']);
})->with(['Has Spaces', 'UPPER', '1starts_with_digit', 'plumbing']);

it('never changes a key after creation (AC7)', function (): void {
    catalogueAdmin();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])
        ->assertFormFieldIsDisabled('key')
        ->fillForm(['key' => 'renamed', 'name' => 'Plumbing'])
        ->call('save');

    expect(plumbing()->key)->toBe('plumbing');
    expect(fn () => plumbing()->update(['key' => 'renamed']))->toThrow(LogicException::class);
});

it('cannot delete trades, only switch them off (AC8)', function (): void {
    $admin = catalogueAdmin();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])->assertActionDoesNotExist('delete');

    expect($admin->can('delete', plumbing()))->toBeFalse();
});

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

    expect($admin->can('update', plumbing()))->toBeFalse()->and($admin->can('create', Trade::class))->toBeFalse();

    Livewire::test(EditTrade::class, ['record' => 'plumbing'])->assertForbidden();
})->with([Role::AdminVetting, Role::AdminFinance]);

it('lets super-admin and support edit (AC12)', function (Role $role): void {
    $admin = catalogueAdmin($role);

    expect($admin->can('update', plumbing()))->toBeTrue()->and($admin->can('create', Trade::class))->toBeTrue();
})->with([Role::AdminSuper, Role::AdminSupport]);

it('keeps customers and pros out of the catalogue (AC13)', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/trades')->assertForbidden();
    expect($user->can('viewAny', Trade::class))->toBeFalse();
})->with(['customer', 'pro']);

it('uses keys, not numeric ids, in admin URLs', function (): void {
    expect(EditTrade::getUrl(['record' => plumbing()]))->toEndWith('/admin/trades/plumbing/edit');
});

it('lets view-only admins open a trade and see it (AC12)', function (): void {
    catalogueAdmin(Role::AdminFinance);

    Livewire::test(ViewTrade::class, ['record' => 'plumbing'])->assertOk()->assertActionHidden('edit');
});

it('no longer has services, questions or suburbs in the admin (spec 020)', function (): void {
    catalogueAdmin();

    $this->get('/admin/suburbs')->assertNotFound();
    $this->get('/admin/trades/plumbing/services/leak_repair')->assertNotFound();
});
