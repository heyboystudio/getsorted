<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 021: an approved pro asks to add a service or renew a registration; an admin decides. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // A registration the pro proposes: its type, number (encrypted by the model) and, as media, its file.
            $table->string('document_type', 40)->nullable();
            $table->text('registration_number')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('decision_reason', 1000)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
            $table->index(['pro_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_change_requests');
    }
};
