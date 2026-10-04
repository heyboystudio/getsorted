<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_job_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('allocated_at');
            $table->unique(['pro_id', 'service_job_id']);
            $table->index(['pro_id', 'allocated_at']);
        });

        Schema::create('pro_customer_exclusions', function (Blueprint $table): void {
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('upheld_at');
            $table->primary(['pro_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_customer_exclusions');
        Schema::dropIfExists('pro_job_allocations');
    }
};
