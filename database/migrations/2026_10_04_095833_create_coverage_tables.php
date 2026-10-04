<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pros', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('applied')->index();
            $table->string('business_name');
            $table->unsignedSmallInteger('weekly_job_cap')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('pro_services', function (Blueprint $table): void {
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->primary(['pro_id', 'service_id']);
        });
        Schema::create('pro_service_areas', function (Blueprint $table): void {
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suburb_id')->constrained()->restrictOnDelete();
            $table->primary(['pro_id', 'suburb_id']);
        });
        Schema::create('pro_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('pending');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->index(['pro_id', 'type', 'status']);
        });
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 80);
            $table->string('phone_e164', 20);
            $table->string('suburb_text', 120);
            $table->string('suburb_key', 120);
            $table->foreignId('suburb_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('privacy_version', 40);
            $table->timestampTz('consented_at');
            $table->timestampsTz();
            $table->unique(['phone_e164', 'suburb_key', 'service_id']);
            $table->index(['service_id', 'suburb_key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('pro_documents');
        Schema::dropIfExists('pro_service_areas');
        Schema::dropIfExists('pro_services');
        Schema::dropIfExists('pros');
    }
};
