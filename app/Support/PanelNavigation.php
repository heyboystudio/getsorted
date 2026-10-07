<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Enums\ProStatus;
use App\Models\Pro;
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
     * @return list<array{label: string, route: string, icon: string, active: list<string>}>
     */
    public static function items(string $panel): array
    {
        if ($panel === self::PRO) {
            return [
                ['label' => __('Today'), 'route' => 'pros.welcome', 'icon' => 'home', 'active' => ['pros.welcome']],
                ['label' => __('Jobs'), 'route' => 'pros.jobs', 'icon' => 'briefcase', 'active' => ['pros.jobs', 'pros.jobs.*']],
                ['label' => __('Application'), 'route' => 'pros.status', 'icon' => 'user', 'active' => ['pros.status']],
            ];
        }

        return [
            ['label' => __('Home'), 'route' => 'account.home', 'icon' => 'home', 'active' => ['account.home']],
            ['label' => __('Jobs'), 'route' => 'jobs.index', 'icon' => 'briefcase', 'active' => ['jobs.*']],
            ['label' => __('Properties'), 'route' => 'properties.index', 'icon' => 'map-pin', 'active' => ['properties.*']],
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
}
