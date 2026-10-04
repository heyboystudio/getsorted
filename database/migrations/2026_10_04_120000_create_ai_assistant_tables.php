<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 007: job summary provenance and AI usage records (no customer text). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->string('ai_summary_source')->default('none')->after('ai_summary');
            $table->timestamp('ai_summary_generated_at')->nullable()->after('ai_summary_source');
            // Hash of service + answers + notes the summary was written for; detects stale summaries.
            $table->string('ai_summary_input_hash', 64)->nullable()->after('ai_summary_generated_at');
        });

        Schema::create('ai_usage', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose');
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('outcome');
            $table->foreignId('service_job_id')->nullable()->constrained('service_jobs')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn(['ai_summary_source', 'ai_summary_generated_at', 'ai_summary_input_hash']);
        });
    }
};
