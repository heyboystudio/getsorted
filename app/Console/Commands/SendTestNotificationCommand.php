<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Notify;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Sends one test notice to an account, so pop-up notifications can be checked on a real phone or desktop
 * without running a whole job (spec 022). It goes through the same path as every real notice.
 */
final class SendTestNotificationCommand extends Command
{
    protected $signature = 'sortd:send-test-notification {email : The account to notify}';

    protected $description = 'Send a test notice (inbox and, if the device is subscribed, a pop-up) to one account';

    public function handle(): int
    {
        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if (! $user instanceof User) {
            $this->error('No account has that email.');

            return self::FAILURE;
        }

        $devices = $user->pushSubscriptions()->count();

        Notify::user(
            $user,
            'test',
            __('Test notification'),
            __('If you can see this pop-up, notifications work on this device.'),
            route($user->homeRoute()),
        );

        $this->info($devices > 0
            ? "Sent. {$devices} subscribed device(s) will get a pop-up (the queue worker sends it within seconds)."
            : 'Sent to the inbox only: this account has no subscribed device yet.');

        return self::SUCCESS;
    }
}
