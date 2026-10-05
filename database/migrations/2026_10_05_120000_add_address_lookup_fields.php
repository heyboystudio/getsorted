<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 015: where a property's location came from, suburb name aliases and Places usage. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->string('google_place_id', 300)->nullable();
            $table->string('location_source', 20)->default('suburb_centroid');
        });

        Schema::table('suburbs', function (Blueprint $table): void {
            $table->jsonb('aliases')->default('[]');
        });

        Schema::create('geocoder_usage', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose', 20);
            $table->string('outcome', 20);
            $table->unsignedInteger('latency_ms');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoder_usage');
        Schema::table('suburbs', fn (Blueprint $table) => $table->dropColumn('aliases'));
        Schema::table('properties', fn (Blueprint $table) => $table->dropColumn(['google_place_id', 'location_source']));
    }
};
