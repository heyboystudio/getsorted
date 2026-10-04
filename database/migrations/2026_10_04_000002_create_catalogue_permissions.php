<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue permissions (spec 003, decision 2): every admin role can view;
 * super-admin and support can edit. Reference data, so it lives in a migration.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $grants = [
        'catalogue.view' => ['admin_super', 'admin_support', 'admin_vetting', 'admin_finance'],
        'catalogue.edit' => ['admin_super', 'admin_support'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $tables = config('permission.table_names');

        foreach ($this->grants as $permission => $roles) {
            DB::table($tables['permissions'])->insertOrIgnore(['name' => $permission, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            $permissionId = DB::table($tables['permissions'])->where(['name' => $permission, 'guard_name' => 'web'])->value('id');

            foreach (DB::table($tables['roles'])->where('guard_name', 'web')->whereIn('name', $roles)->pluck('id') as $roleId) {
                DB::table($tables['role_has_permissions'])->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app()['cache']->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))->whereIn('name', array_keys($this->grants))->where('guard_name', 'web')->delete();

        app()['cache']->forget(config('permission.cache.key'));
    }
};
