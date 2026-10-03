<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles are reference data needed in every environment (including
 * production, where seeders do not run), so they are created here.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $roles = ['customer', 'pro', 'admin_super', 'admin_support', 'admin_vetting', 'admin_finance'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table(config('permission.table_names.roles'))->insertOrIgnore(array_map(
            fn (string $name): array => ['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            $this->roles,
        ));

        app()['cache']->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table(config('permission.table_names.roles'))
            ->where('guard_name', 'web')
            ->whereIn('name', $this->roles)
            ->delete();

        app()['cache']->forget(config('permission.cache.key'));
    }
};
