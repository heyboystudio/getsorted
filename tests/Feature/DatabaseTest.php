<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('runs on PostgreSQL 17 or newer', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(config('database.default'))->toBe('pgsql')
        ->and(config('queue.failed.database'))->toBe('pgsql')
        ->and(config('queue.batching.database'))->toBe('pgsql');

    $version = (int) DB::scalar('SHOW server_version_num');

    expect($version)->toBeGreaterThanOrEqual(170000);
});

it('has PostGIS enabled for distance queries', function (): void {
    expect(DB::scalar("SELECT extname FROM pg_extension WHERE extname = 'postgis'"))->toBe('postgis');

    // Durban City Hall to Umhlanga Rocks lighthouse: about 15 km.
    $metres = (float) DB::scalar(
        'SELECT ST_Distance(ST_MakePoint(31.0236, -29.8583)::geography, ST_MakePoint(31.0877, -29.7270)::geography)',
    );

    expect($metres)->toBeGreaterThan(15_000)->toBeLessThan(17_000);
});

it('defaults sessions, cache and queue to the database', function (string $key): void {
    expect(file_get_contents(base_path('.env.example')))->toContain($key);
})->with(['SESSION_DRIVER=database', 'CACHE_STORE=database', 'QUEUE_CONNECTION=database']);

it('stores sessions, cache and queued jobs in Postgres', function (): void {
    expect(Schema::hasTable('sessions'))->toBeTrue();

    Cache::store('database')->put('sortd-check', 'ok', 60);
    expect(Cache::store('database')->get('sortd-check'))->toBe('ok')
        ->and(DB::table('cache')->count())->toBe(1);

    Queue::connection('database')->pushRaw('{"job":"check"}', 'payments');
    expect(DB::table('jobs')->where('queue', 'payments')->count())->toBe(1);
});

it('dispatches database-queued jobs only after the transaction commits', function (): void {
    expect(config('queue.connections.database.after_commit'))->toBeTrue();
});
