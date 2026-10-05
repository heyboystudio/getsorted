<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 018: chat between a job's customer and each invited pro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_conversations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            // Only an admin closes a conversation; read-only after another pro is chosen is worked out from the job.
            $table->string('status', 20)->default('open');
            $table->string('closed_reason', 300)->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('customer_read_at')->nullable();
            $table->timestampTz('pro_read_at')->nullable();
            $table->timestampTz('customer_notified_at')->nullable();
            $table->timestampTz('pro_notified_at')->nullable();
            $table->timestampsTz();
            $table->unique(['service_job_id', 'pro_id']);
        });

        Schema::create('job_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('job_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('body', 1000)->nullable();
            $table->string('kind', 20)->default('text');
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampTz('reported_at')->nullable();
            $table->string('report_reason', 30)->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['job_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_messages');
        Schema::dropIfExists('job_conversations');
    }
};
