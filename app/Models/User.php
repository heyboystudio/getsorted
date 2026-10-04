<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Accounts\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $public_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone_e164
 * @property CarbonImmutable|null $phone_verified_at
 * @property string $locale
 */
final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUlids, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['first_name', 'last_name', 'email', 'locale'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * The ULID used in URLs; the numeric id never leaves the server.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Default deny. The admin panel needs an admin role (MFA is enforced by
     * the panel itself); the pro panel stays closed until pro phone login.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            default => false,
        };
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(array_map(fn (Role $role): string => $role->value, Role::adminRoles()));
    }

    /**
     * Where a phone-login user lands (spec 011): pros go to the pro area; someone
     * who came to join as a pro but isn't one yet goes to the pro-agreement step.
     */
    public function homeRoute(bool $joiningAsPro = false): string
    {
        if ($this->hasRole(Role::Pro->value)) {
            return 'pros.welcome';
        }

        return $joiningAsPro ? 'pros.become' : 'account.home';
    }

    public function getFilamentName(): string
    {
        return $this->fullName();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'phone_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }
}
