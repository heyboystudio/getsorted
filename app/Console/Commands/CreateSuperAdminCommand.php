<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounts\Actions\CreateSuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * The password is always typed at a hidden prompt, never passed as an
 * argument, so it does not end up in shell history or process lists.
 */
final class CreateSuperAdminCommand extends Command
{
    protected $signature = 'sortd:create-super-admin';

    protected $description = 'Create a super-admin account for the Filament admin panel';

    public function handle(CreateSuperAdmin $createSuperAdmin): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command must be run interactively so the password can be typed privately.');

            return self::FAILURE;
        }

        $firstName = text('First name', required: true);
        $lastName = text('Last name', required: true);
        $email = mb_strtolower(trim(text('Email address', required: true)));
        $password = password('Password (at least 12 characters, upper and lower case, a number and a symbol)', required: true);
        $confirmation = password('Confirm password', required: true);

        if ($password !== $confirmation) {
            $this->error('The passwords do not match. Nothing was created.');

            return self::FAILURE;
        }

        try {
            $user = $createSuperAdmin->handle($firstName, $lastName, $email, $password);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            $this->error('Nothing was created.');

            return self::FAILURE;
        }

        $this->info("Super-admin {$user->email} created.");
        $this->line('Sign in at '.url('/admin').' and set up your authenticator app when asked.');

        return self::SUCCESS;
    }
}
