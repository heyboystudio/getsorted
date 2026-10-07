<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pros\Enums\BusinessType;
use App\Domain\Pros\Enums\DocumentStatus;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Pros\Enums\ProStatus;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\ProFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tradesperson business linked to one user (spec 008). `status` changes only
 * through ProStatusMachine.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property ProStatus $status
 * @property string|null $business_name
 * @property BusinessType|null $business_type
 * @property string|null $vat_number
 * @property string|null $bio
 * @property int|null $weekly_job_cap
 * @property Point|null $base_location where the pro works from (Google Places); jobs are matched by distance from here
 * @property string|null $base_address
 * @property string|null $base_place_id
 * @property string|null $base_area_label approximate area name, safe to show customers
 * @property int $service_radius_km
 * @property CarbonImmutable|null $vetting_consent_at
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $decided_by
 * @property string|null $decision_reason
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $reapply_after
 * @property CarbonImmutable|null $last_activity_at
 * @property int $contact_masking_count
 * @property CarbonImmutable $created_at
 */
final class Pro extends Model
{
    /** @use HasFactory<ProFactory> */
    use HasFactory, HasUlids;

    /** Status and decision fields are deliberately absent: only actions set them. */
    /** @var list<string> */
    protected $fillable = ['business_name', 'business_type', 'vat_number', 'bio', 'weekly_job_cap'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'service_radius_km' => 15];

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

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsToMany<Trade, $this> */
    public function trades(): BelongsToMany
    {
        return $this->belongsToMany(Trade::class, 'pro_trades');
    }

    /** @return HasMany<ProDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ProDocument::class);
    }

    /** @return HasMany<ProReference, $this> */
    public function references(): HasMany
    {
        return $this->hasMany(ProReference::class)->orderBy('id');
    }

    /** @return HasMany<ServiceJobInvite, $this> */
    public function invites(): HasMany
    {
        return $this->hasMany(ServiceJobInvite::class);
    }

    /** Repeated attempts to share contact details in quotes (spec 010, AC6). */
    public function isMaskingFlagged(): bool
    {
        return $this->contact_masking_count >= 3;
    }

    /** Pros with a VAT number add VAT to quotes (spec 010, decision 2). */
    public function isVatRegistered(): bool
    {
        return filled($this->vat_number);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** @return HasMany<ProEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProEvent::class)->orderBy('id');
    }

    public function document(DocumentType $type): ?ProDocument
    {
        return $this->documents->firstWhere('type', $type);
    }

    /**
     * Registrations a pro can verify for the chosen trades. They are optional: an unverified pro
     * can still be matched, and a verified one shows a badge (spec 020, decision 2).
     *
     * @return list<DocumentType>
     */
    public function offeredRegistrations(): array
    {
        return $this->trades->pluck('registration')->filter()->unique()
            ->map(fn ($registration): DocumentType => DocumentType::forRegistration($registration))
            ->sortBy(fn (DocumentType $type): string => $type->value)->values()->all();
    }

    /** Holds a verified, unexpired registration for the trade; false when the trade has none to verify. */
    public function isVerifiedFor(Trade $trade): bool
    {
        if ($trade->registration === null) {
            return false;
        }

        $document = $this->documents->firstWhere('type', DocumentType::forRegistration($trade->registration));

        return $document instanceof ProDocument
            && $document->status === DocumentStatus::Verified
            && $document->verified_at !== null
            && ($document->expires_at === null || $document->expires_at->isFuture());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProStatus::class,
            'business_type' => BusinessType::class,
            'weekly_job_cap' => 'integer',
            'base_location' => Point::class,
            'base_address' => 'encrypted',
            'service_radius_km' => 'integer',
            'contact_masking_count' => 'integer',
            'vetting_consent_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'reapply_after' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }
}
