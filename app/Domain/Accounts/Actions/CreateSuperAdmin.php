<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Creates a super-admin account. Only reachable from the
 * `sortd:create-super-admin` console command, never from a seeder or the web.
 */
final class CreateSuperAdmin
{
    /**
     * @throws ValidationException
     */
    public function handle(string $firstName, string $lastName, string $email, #[SensitiveParameter] string $password): User
    {
        $validated = Validator::make(
            ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'password' => $password],
            [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'string', 'lowercase', 'email:strict', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', self::passwordRule()],
            ],
        )->validate();

        return DB::transaction(function () use ($validated): User {
            $user = new User;
            $user->forceFill([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'email_verified_at' => now(),
            ])->save();

            $user->assignRole(Role::AdminSuper->value);

            return $user;
        });
    }

    /** Admin passwords: strong and not in known breaches (security baseline §1). */
    public static function passwordRule(): Password
    {
        return Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised();
    }
}
