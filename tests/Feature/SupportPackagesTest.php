<?php

declare(strict_types=1);

use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Propaganistas\LaravelPhone\PhoneNumber;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('stores media on a private disk served only by signed URLs', function (): void {
    expect(config('media-library.disk_name'))->toBe('media')
        ->and(config('filesystems.disks.media.visibility'))->toBe('private')
        ->and(config('filesystems.disks.media.root'))->toStartWith(storage_path('app/private'));

    $disk = Storage::disk('media');
    $path = 'tests/'.Str::uuid().'.txt';
    $disk->put($path, 'private photo');

    try {
        $this->get($disk->url($path))->assertForbidden();

        $signed = $disk->temporaryUrl($path, now()->addMinutes(5));
        $this->get($signed)->assertOk();
    } finally {
        $disk->deleteDirectory('tests');
    }
});

it('validates South African phone numbers and formats them as E.164', function (): void {
    expect(Validator::make(['phone' => '082 123 4567'], ['phone' => 'phone:ZA'])->passes())->toBeTrue()
        ->and(Validator::make(['phone' => '12345'], ['phone' => 'phone:ZA'])->passes())->toBeFalse()
        ->and(new PhoneNumber('082 123 4567', 'ZA')->formatE164())->toBe('+27821234567');
});

it('stores geography points and measures distances with PostGIS', function (): void {
    Schema::create('magellan_checks', function (Blueprint $table): void {
        $table->id();
        $table->magellanPoint('location', 4326, 'GEOGRAPHY');
    });

    DB::table('magellan_checks')->insert(['location' => Point::makeGeodetic(-29.7270, 31.0877)]);

    $metres = DB::table('magellan_checks')
        ->select(ST::distance(Point::makeGeodetic(-29.8583, 31.0236), 'location')->as('metres'))
        ->value('metres');

    expect((float) $metres)->toBeGreaterThan(15_000)->toBeLessThan(17_000);
});

it('records activity in the audit log', function (): void {
    $user = User::factory()->create();

    activity()->causedBy($user)->performedOn($user)->log('checked the audit log');

    expect(Activity::query()->sole())
        ->description->toBe('checked the audit log')
        ->causer_id->toBe($user->id);
});

it('has a settings table ready for configurable values', function (): void {
    expect(Schema::hasTable('settings'))->toBeTrue()
        ->and(Schema::hasTable('media'))->toBeTrue()
        ->and(Schema::hasTable('activity_log'))->toBeTrue();
});
