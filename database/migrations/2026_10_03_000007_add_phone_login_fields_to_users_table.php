<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_e164', 16)->nullable()->unique();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->string('locale', 5)->default('en');
            $table->softDeletesTz();
        });

        // Existing accounts (admins) get a public ID and their name split in two.
        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [''];

            DB::table('users')->where('id', $user->id)->update([
                'public_id' => (string) Str::ulid(),
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable(false)->change();
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations. Customers without an email or password cannot be
     * restored to the old schema, so this only works before customers exist.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable();
        });

        DB::table('users')->update(['name' => DB::raw("trim(first_name || ' ' || last_name)")]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
            $table->dropSoftDeletesTz();
            $table->dropColumn(['public_id', 'first_name', 'last_name', 'phone_e164', 'phone_verified_at', 'locale']);
        });
    }
};
