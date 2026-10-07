<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Spec 018 part 2: the pro proposes a final amount; the customer approves increases. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_amount_proposals', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained();
            $table->foreignId('pro_id')->constrained();
            $table->unsignedSmallInteger('version');
            // [{kind, description, quantity, unit_price_cents, line_total_cents}], descriptions masked as in quotes.
            $table->jsonb('lines');
            $table->unsignedBigInteger('labour_cents');
            $table->unsignedBigInteger('materials_cents');
            $table->unsignedBigInteger('callout_cents');
            $table->unsignedBigInteger('vat_cents');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('previous_total_cents');
            $table->string('reason', 500);
            $table->string('status', 20);
            $table->timestampTz('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_note', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['service_job_id', 'version']);
            $table->index(['service_job_id', 'status']);
        });

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('agreed_final_cents')->nullable();
        });

        // Jobs booked before this change agree on their accepted estimate's total.
        DB::statement('update service_jobs set agreed_final_cents = quotes.total_cents from quotes where quotes.id = service_jobs.accepted_quote_id');
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn('agreed_final_cents');
        });

        Schema::dropIfExists('final_amount_proposals');
    }
};
