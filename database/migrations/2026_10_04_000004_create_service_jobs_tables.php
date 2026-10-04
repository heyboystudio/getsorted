<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_jobs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            // Written only by ServiceJobStateMachine (job-lifecycle.md).
            $table->string('status', 32)->default('draft');
            $table->string('urgency', 16)->default('normal');
            $table->date('preferred_date')->nullable();
            $table->string('time_window', 16)->nullable();
            $table->jsonb('scoping_answers')->default('{}');
            $table->text('customer_notes')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('quote_window_ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'posted_at']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('service_job_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('event_type', 64);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->jsonb('payload')->default('{}');
            // Append-only timeline: no updated_at.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['service_job_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_job_events');
        Schema::dropIfExists('service_jobs');
    }
};
