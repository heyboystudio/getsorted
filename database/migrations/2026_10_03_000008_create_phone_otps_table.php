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
        Schema::create('phone_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_e164', 16);
            $table->string('code_hash', 64);
            $table->string('channel', 16);
            $table->string('purpose', 16);
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampsTz();

            $table->index(['phone_e164', 'purpose', 'consumed_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_otps');
    }
};
