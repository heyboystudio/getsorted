<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\Pro;
use App\Models\ServiceJobInvite;
use App\Models\User;

/**
 * What the signed-in shell shows for each panel (spec 021): the tab bar, who
 * gets it, and the switch between the customer and pro panels.
 */
final class PanelNavigation
{
    public const CUSTOMER = 'customer';

    public const PRO = 'pro';

    /**
     * Tab bar entries. `active` lists the route names (wildcards allowed) that
     * keep the entry highlighted.
     *
     * @return list<array{label: string, route: string, icon: string, active: list<string>, badge: int}>
     */
    public static function items(string $panel, ?User $user = null): array
    {
        if ($panel === self::PRO) {
            return [
                ['label' => __('Today'), 'route' => 'pros.welcome', 'icon' => 'home', 'active' => ['pros.welcome'], 'badge' => 0],
                ['label' => __('Jobs'), 'route' => 'pros.jobs', 'icon' => 'briefcase', 'active' => ['pros.jobs', 'pros.jobs.*'], 'badge' => $user instanceof User ? self::openInvites($user) : 0],
                ['label' => __('Profile'), 'route' => 'pros.profile', 'icon' => 'user', 'active' => ['pros.profile', 'pros.profile.*', 'pros.status'], 'badge' => 0],
            ];
        }

        return [
            ['label' => __('Home'), 'route' => 'account.home', 'icon' => 'home', 'active' => ['account.home'], 'badge' => 0],
            ['label' => __('Jobs'), 'route' => 'jobs.index', 'icon' => 'briefcase', 'active' => ['jobs.*'], 'badge' => 0],
            ['label' => __('Messages'), 'route' => 'messages', 'icon' => 'chat', 'active' => ['messages', 'notifications'], 'badge' => $user instanceof User ? JobChat::unreadTotal($user) : 0],
            ['label' => __('Account'), 'route' => 'account.settings', 'icon' => 'user', 'active' => ['account.settings', 'account.profile', 'account.notifications', 'account.privacy', 'properties.*'], 'badge' => 0],
        ];
    }

    /** Customers always get their tabs; pros only once approved (spec 021, AC1 and AC2). */
    public static function showsTabs(?User $user, string $panel): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($panel === self::PRO) {
            return $user->hasRole(Role::Pro->value)
                && Pro::query()->where('user_id', $user->id)->where('status', ProStatus::Approved)->exists();
        }

        return $user->hasRole(Role::Customer->value);
    }

    /**
     * The other panel, offered only to someone who holds both roles (AC3).
     *
     * @return array{label: string, route: string}|null
     */
    public static function switchTarget(?User $user, string $panel): ?array
    {
        if (! $user instanceof User || ! $user->hasRole(Role::Customer->value) || ! $user->hasRole(Role::Pro->value)) {
            return null;
        }

        return $panel === self::PRO
            ? ['label' => __('Customer account'), 'route' => 'account.home']
            : ['label' => __('Pro area'), 'route' => 'pros.welcome'];
    }

    public static function homeRoute(string $panel): string
    {
        return $panel === self::PRO ? 'pros.welcome' : 'account.home';
    }

    /** Invites still waiting for this pro, shown as a badge on their Jobs tab. */
    private static function openInvites(User $user): int
    {
        $pro = Pro::query()->where('user_id', $user->id)->where('status', ProStatus::Approved)->first();

        return $pro instanceof Pro
            ? ServiceJobInvite::query()->where('pro_id', $pro->id)->whereIn('status', InviteStatus::open())->where('expires_at', '>', now())->count()
            : 0;
    }
}
