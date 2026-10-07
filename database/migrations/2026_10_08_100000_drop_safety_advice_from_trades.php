<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Founder, 2026-10-08 (decision 060): GetSorted gives no safety advice, so trades no longer carry any. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table): void {
            $table->dropColumn('safety_advice');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table): void {
            $table->jsonb('safety_advice')->default('[]');
        });
    }
};
