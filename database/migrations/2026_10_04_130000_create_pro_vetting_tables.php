<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Spec 008: pro application, vetting checks, references and status history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pros', function (Blueprint $table): void {
            $table->string('business_type', 20)->nullable()->after('business_name');
            $table->string('vat_number', 20)->nullable()->after('business_type');
            $table->text('bio')->nullable()->after('vat_number');
            $table->timestampTz('vetting_consent_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampTz('reapply_after')->nullable();
            $table->timestampTz('last_activity_at')->nullable()->index();
        });

        // Spec 006 created pros as "applied"; the application now starts as a draft.
        DB::table('pros')->where('status', 'applied')->update(['status' => 'draft']);
        DB::table('pros')->update(['last_activity_at' => DB::raw('updated_at')]);

        Schema::table('pros', function (Blueprint $table): void {
            $table->string('status', 20)->default('draft')->change();
            $table->string('business_name')->nullable()->change();
        });

        Schema::table('pro_documents', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique()->after('id');
            // Registration number, encrypted (security baseline §6).
            $table->text('number')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            // Shown to the pro.
            $table->string('flag_message', 500)->nullable();
            // Private to vetting admins.
            $table->text('notes')->nullable();
        });

        foreach (DB::table('pro_documents')->whereNull('public_id')->pluck('id') as $id) {
            DB::table('pro_documents')->where('id', $id)->update(['public_id' => (string) Str::ulid()]);
        }

        Schema::table('pro_documents', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable(false)->change();
            $table->unique(['pro_id', 'type']);
        });

        Schema::create('pro_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            // Encrypted E.164 number (third-party personal data).
            $table->text('phone_e164');
            $table->string('relationship', 120);
            $table->string('outcome', 20)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('checked_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('pro_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['pro_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_events');
        Schema::dropIfExists('pro_references');

        Schema::table('pro_documents', function (Blueprint $table): void {
            $table->dropUnique(['pro_id', 'type']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['public_id', 'number', 'flag_message', 'notes']);
        });

        DB::table('pros')->where('status', 'draft')->update(['status' => 'applied']);

        Schema::table('pros', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['business_type', 'vat_number', 'bio', 'vetting_consent_at', 'submitted_at', 'decision_reason', 'decided_at', 'suspended_at', 'reapply_after', 'last_activity_at']);
            $table->string('status', 20)->default('applied')->change();
        });
    }
};
