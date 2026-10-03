<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * Default: an email/password account (the shape admins have).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone_e164' => null,
            'phone_verified_at' => null,
            'locale' => 'en',
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => ['email_verified_at' => null]);
    }

    /** A phone-login customer: verified SA mobile, no email or password. */
    public function customer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
            'phone_e164' => '+2782'.fake()->unique()->numerify('#######'),
            'phone_verified_at' => now(),
        ])->afterCreating(fn (User $user): User => $user->assignRole(Role::Customer->value));
    }
}
