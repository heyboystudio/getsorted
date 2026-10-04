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
        Schema::create('trades', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('status', 16)->default('demo');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestampsTz();
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            // Services are switched off, never deleted, so jobs keep their history (spec 003, decision 3).
            $table->foreignId('trade_id')->constrained()->restrictOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('requires_registration', 64)->nullable();
            $table->boolean('emergency_capable')->default(false);
            $table->jsonb('safety_advice')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestampsTz();

            $table->unique(['trade_id', 'key']);
        });

        Schema::create('scoping_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->text('prompt');
            $table->string('type', 16);
            $table->jsonb('options')->default('[]');
            $table->boolean('required')->default(false);
            $table->jsonb('flags')->default('{}');
            $table->unsignedInteger('sort')->default(0);
            $table->timestampsTz();

            $table->unique(['service_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoping_questions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('trades');
    }
};
