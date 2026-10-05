<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Filament\Admin\Pages\Auth\EditProfile;
use App\Filament\Admin\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->create(['first_name' => 'Old', 'last_name' => 'Name']);
    $this->admin->assignRole(Role::AdminSuper->value);
    $this->actingAs($this->admin);
    session()->put(Login::SESSION_KEY, $this->admin->id);
});

it('lets an admin change their first and last name', function (): void {
    Livewire::test(EditProfile::class)
        ->assertSchemaStateSet(['first_name' => 'Old', 'last_name' => 'Name'])
        ->fillForm(['first_name' => 'New', 'last_name' => 'Person'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->admin->refresh()->fullName())->toBe('New Person');
});
