<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Pro extends Model
{
    /** @use HasFactory<ProFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = ['business_name', 'status', 'weekly_job_cap', 'approved_at'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'pro_services');
    }

    /** @return BelongsToMany<Suburb, $this> */
    public function serviceAreas(): BelongsToMany
    {
        return $this->belongsToMany(Suburb::class, 'pro_service_areas');
    }

    /** @return HasMany<ProDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ProDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['approved_at' => 'immutable_datetime', 'weekly_job_cap' => 'integer'];
    }
}
