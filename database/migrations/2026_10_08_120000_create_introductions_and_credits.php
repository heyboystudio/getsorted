<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 023: the introduction record, a pro's prepaid credit ledger and PayFast credit purchases. Money in integer cents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            // The top of an estimate range; null means the total is the price.
            $table->bigInteger('high_total_cents')->nullable()->after('total_cents');
        });

        Schema::create('introductions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_job_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->constrained()->restrictOnDelete();
            $table->foreignId('pro_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            // free: inside the pro's free allowance or the fee is switched off; credit: paid from prepaid credit.
            $table->string('kind', 10);
            $table->bigInteger('fee_cents')->default(0);
            $table->timestampTz('created_at');
            $table->index(['pro_id', 'created_at']);
        });

        Schema::create('credit_purchases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('pro_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->string('status', 12);
            $table->string('provider_reference', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['pro_id', 'status']);
        });

        Schema::create('pro_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_id')->constrained()->restrictOnDelete();
            $table->string('type', 14);
            // Signed: credit bought is positive, an introduction fee is negative.
            $table->bigInteger('amount_cents');
            $table->foreignId('credit_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('introduction_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('note', 200)->nullable();
            $table->string('idempotency_key', 80)->unique();
            $table->timestampTz('created_at');
            $table->index(['pro_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_credit_entries');
        Schema::dropIfExists('credit_purchases');
        Schema::dropIfExists('introductions');
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn('high_total_cents');
        });
    }
};
