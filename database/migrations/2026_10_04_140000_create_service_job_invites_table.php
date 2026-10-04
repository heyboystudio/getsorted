<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 009: invite waves. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_job_invites', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('wave');
            $table->string('status', 20)->default('invited');
            $table->timestampTz('invited_at');
            $table->timestampTz('viewed_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('expires_at');
            $table->string('decline_reason', 20)->nullable();
            $table->string('decline_note', 300)->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['service_job_id', 'pro_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['pro_id', 'invited_at']);
        });

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->timestampTz('last_wave_at')->nullable();
            $table->timestampTz('matching_stopped_at')->nullable();
            $table->string('matching_stopped_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropColumn(['last_wave_at', 'matching_stopped_at', 'matching_stopped_reason']);
        });

        Schema::dropIfExists('service_job_invites');
    }
};
