<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

/**
 * Role names stored in spatie/laravel-permission's `roles` table
 * (docs/architecture/data-model.md). Admin roles are least-privilege.
 */
enum Role: string
{
    case Customer = 'customer';
    case Pro = 'pro';
    case AdminSuper = 'admin_super';
    case AdminSupport = 'admin_support';
    case AdminVetting = 'admin_vetting';
    case AdminFinance = 'admin_finance';

    /** @return list<self> */
    public static function adminRoles(): array
    {
        return [self::AdminSuper, self::AdminSupport, self::AdminVetting, self::AdminFinance];
    }

    public function isAdmin(): bool
    {
        return in_array($this, self::adminRoles(), true);
    }
}
