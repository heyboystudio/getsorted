<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec 010: quotes, quote lines, acceptance fields. Money in integer cents. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pro_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status', 20);
            $table->bigInteger('labour_cents');
            $table->bigInteger('materials_cents');
            $table->bigInteger('callout_cents');
            $table->bigInteger('vat_cents');
            $table->bigInteger('total_cents');
            $table->unsignedTinyInteger('deposit_percent');
            $table->bigInteger('deposit_cents');
            $table->date('earliest_start_date');
            $table->date('valid_until');
            $table->text('notes')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->string('withdraw_reason', 300)->nullable();
            $table->foreignId('supersedes_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['service_job_id', 'pro_id', 'version']);
            $table->index(['service_job_id', 'status']);
            $table->index(['status', 'valid_until']);
        });

        Schema::create('quote_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('description', 120);
            $table->decimal('quantity', 10, 2);
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('line_total_cents');
            $table->unsignedSmallInteger('sort');
        });

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('quotes_count')->default(0);
            $table->foreignId('accepted_quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->date('scheduled_for')->nullable();
        });

        Schema::table('pros', function (Blueprint $table): void {
            $table->unsignedInteger('contact_masking_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('pros', fn (Blueprint $table) => $table->dropColumn('contact_masking_count'));

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_quote_id');
            $table->dropColumn(['quotes_count', 'scheduled_for']);
        });

        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
    }
};
