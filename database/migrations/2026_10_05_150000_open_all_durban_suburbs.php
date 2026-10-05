<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Decision 044: the whole of Durban is open, so every eThekwini suburb is active. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('suburbs')->where('municipality', config('sortd.places.municipality'))->update(['is_active' => true]);
    }

    public function down(): void
    {
        // Not reversible: earlier per-suburb choices aren't recorded.
    }
};
