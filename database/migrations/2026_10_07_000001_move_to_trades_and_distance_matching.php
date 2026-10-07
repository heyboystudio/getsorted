<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 020: services, scoping questions, suburbs and suburb coverage are replaced by trades,
 * facts extracted from the customer's own words, and distance matching from geocoded addresses.
 * Existing rows are carried over first; the old tables are dropped last. Not reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table): void {
            $table->string('registration', 40)->nullable();
            $table->jsonb('safety_advice')->default('[]');
        });

        DB::table('trades')->where('key', 'electrical')->update([
            'registration' => 'electrical_registered_person',
            'safety_advice' => json_encode([
                'If you smell burning or see sparks, switch off the main switch at the DB board and keep away.',
                'Don’t keep resetting a breaker that trips repeatedly.',
            ]),
        ]);
        DB::table('trades')->where('key', 'plumbing')->update([
            'registration' => 'pirb',
            'safety_advice' => json_encode(['If water is flooding, close the main stopcock first.']),
        ]);

        Schema::table('pros', function (Blueprint $table): void {
            $table->magellanPoint('base_location', 4326, 'GEOGRAPHY')->nullable();
            // Encrypted by the model cast (security baseline §6).
            $table->text('base_address')->nullable();
            $table->string('base_place_id', 300)->nullable();
            $table->string('base_area_label', 160)->nullable();
            $table->unsignedSmallInteger('service_radius_km')->default(15);
            $table->spatialIndex('base_location');
        });

        Schema::create('pro_trades', function (Blueprint $table): void {
            $table->foreignId('pro_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained()->restrictOnDelete();
            $table->primary(['pro_id', 'trade_id']);
        });

        DB::statement('insert into pro_trades (pro_id, trade_id) select distinct ps.pro_id, s.trade_id from pro_services ps join services s on s.id = ps.service_id');

        Schema::table('properties', function (Blueprint $table): void {
            $table->string('area_label', 160)->nullable();
        });
        DB::statement('update properties set area_label = (select su.name from suburbs su where su.id = properties.suburb_id), location = coalesce(location, (select su.centroid from suburbs su where su.id = properties.suburb_id))');

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->foreignId('trade_id')->nullable()->constrained()->restrictOnDelete();
            // [{id, text, turn}]: short facts Siya extracted from the customer's words, highlighted for pros.
            $table->jsonb('facts')->default('[]');
            $table->magellanPoint('location', 4326, 'GEOGRAPHY')->nullable();
            $table->string('area_label', 160)->nullable();
        });

        DB::statement('update service_jobs set trade_id = (select s.trade_id from services s where s.id = service_jobs.service_id)');
        DB::statement('update service_jobs set location = (select p.location from properties p where p.id = service_jobs.property_id), area_label = (select p.area_label from properties p where p.id = service_jobs.property_id)');

        Schema::table('service_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('scoping_answers');
            $table->spatialIndex('location');
        });
        DB::statement('alter table service_jobs alter column trade_id set not null');

        Schema::table('properties', fn (Blueprint $table) => $table->dropConstrainedForeignId('suburb_id'));

        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->foreignId('trade_id')->nullable()->constrained()->restrictOnDelete();
            $table->magellanPoint('location', 4326, 'GEOGRAPHY')->nullable();
            $table->string('area_label', 160)->nullable();
        });
        DB::statement('update waitlist_entries set trade_id = (select s.trade_id from services s where s.id = waitlist_entries.service_id), area_label = suburb_text, location = (select su.centroid from suburbs su where su.id = waitlist_entries.suburb_id)');
        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->dropUnique(['phone_e164', 'suburb_key', 'service_id']);
            $table->dropIndex(['service_id', 'suburb_key']);
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('suburb_id');
            $table->dropColumn(['suburb_text', 'suburb_key']);
        });
        DB::statement('alter table waitlist_entries alter column trade_id set not null');
        Schema::table('waitlist_entries', function (Blueprint $table): void {
            $table->unique(['phone_e164', 'trade_id', 'area_label']);
            $table->index(['trade_id', 'area_label']);
        });

        Schema::dropIfExists('pro_service_areas');
        Schema::dropIfExists('pro_services');
        Schema::dropIfExists('scoping_questions');
        Schema::dropIfExists('services');
        Schema::dropIfExists('suburbs');
    }

    public function down(): void
    {
        throw new RuntimeException('Spec 020 removed services, scoping questions and suburbs; this migration cannot be reversed.');
    }
};
