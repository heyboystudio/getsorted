<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 021: a pro can pause new invites without losing their approval. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pros', function (Blueprint $table): void {
            $table->timestampTz('paused_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pros', function (Blueprint $table): void {
            $table->dropColumn('paused_at');
        });
    }
};
