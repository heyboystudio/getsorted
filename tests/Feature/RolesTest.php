<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Filament\Admin\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

uses(RefreshDatabase::class);

it('creates every role in the database', function (): void {
    expect(RoleModel::query()->where('guard_name', 'web')->pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->map->value->sort()->values()->all());
});

it('does not duplicate roles when the role migration runs again', function (): void {
    $migration = require database_path('migrations/2026_10_03_000003_create_default_roles.php');
    $migration->up();

    expect(DB::table('roles')->count())->toBe(count(Role::cases()));
});

it('lets every admin role into the admin panel', function (Role $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('pro')))->toBeFalse();
})->with(Role::adminRoles());

it('keeps customers, pros and role-less users out of the admin panel', function (?Role $role): void {
    $user = User::factory()->create();

    if ($role instanceof Role) {
        $user->assignRole($role->value);
    }

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
})->with([Role::Customer, Role::Pro, null]);

it('lets an admin without MFA straight into the dashboard', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);

    $this->actingAs($admin)
        ->withSession([Login::SESSION_KEY => $admin->id])
        ->get('/admin')
        ->assertOk();
});
