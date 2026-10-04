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
        Schema::create('suburbs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('region', 32);
            $table->string('municipality')->default('eThekwini');
            $table->magellanPoint('centroid', 4326, 'GEOGRAPHY');
            $table->magellanMultiPolygon('boundary', 4326, 'GEOGRAPHY')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();

            $table->unique(['municipality', 'name']);
            $table->spatialIndex('centroid');
        });

        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('label', 50);
            // Encrypted by the model cast (security baseline §6).
            $table->text('street_address');
            $table->foreignId('suburb_id')->constrained()->restrictOnDelete();
            $table->magellanPoint('location', 4326, 'GEOGRAPHY')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('property_type', 16);
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['user_id', 'deleted_at']);
            $table->spatialIndex('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
        Schema::dropIfExists('suburbs');
    }
};
