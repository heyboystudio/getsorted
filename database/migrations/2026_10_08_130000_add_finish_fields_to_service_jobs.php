<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Spec 024: jobs can be finished or cancelled after booking, and a booking never waits for a deposit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->timestampTz('completed_at')->nullable();
            $table->string('completed_by', 10)->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->timestampTz('finish_nudged_at')->nullable();
        });

        // Model B takes no deposits (decision 058): anything left waiting for one is simply booked.
        DB::table('service_jobs')->where('status', 'awaiting_deposit')->update(['status' => 'scheduled']);
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn(['completed_at', 'completed_by', 'cancelled_by', 'finish_nudged_at']);
        });
    }
};
