<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 025: one review per finished job, with at most one reply from the pro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_job_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('pro_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('reply')->nullable();
            $table->timestampTz('replied_at')->nullable();
            // Set by an admin to take a review off the site; the review itself is kept.
            $table->timestampTz('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hide_reason', 300)->nullable();
            $table->timestampsTz();
            $table->index(['pro_id', 'hidden_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
