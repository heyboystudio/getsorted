<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

final class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory, Prunable;

    /** @var list<string> */
    protected $fillable = ['first_name', 'phone_e164', 'suburb_text', 'suburb_key', 'suburb_id', 'service_id', 'privacy_version', 'consented_at'];

    /** @var list<string> */
    protected $hidden = ['phone_e164', 'first_name'];

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subMonths((int) config('sortd.waitlist.retention_months')));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['consented_at' => 'immutable_datetime'];
    }
}
