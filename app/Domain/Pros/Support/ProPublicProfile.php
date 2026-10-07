<?php

declare(strict_types=1);

namespace App\Domain\Pros\Support;

use App\Domain\Pros\Enums\DocumentStatus;
use App\Models\Pro;
use Carbon\CarbonImmutable;

/**
 * What a customer may see of a pro (spec 021, AC17 and AC18). A short allow-list built field by
 * field, so contact details, ID and address documents, VAT and bank details can never reach the
 * screen by accident. Nothing here is a rating, a review or a count that is not backed by data.
 */
final readonly class ProPublicProfile
{
    /**
     * @param  list<array{name: string, verified: bool}>  $trades  each trade the pro offers, and whether they hold a valid registration for it
     * @param  list<array{label: string, valid: bool}>  $registrations
     */
    public function __construct(
        public string $businessName,
        public ?string $bio,
        public array $trades,
        public ?string $area,
        public int $radiusKm,
        public array $registrations,
        public ?CarbonImmutable $since,
        public ?string $photoUrl,
    ) {}

    public static function from(Pro $pro, ?string $photoUrl = null): self
    {
        $pro->loadMissing(['trades', 'documents']);

        $trades = $pro->trades->sortBy('name')->map(fn ($trade): array => ['name' => $trade->name, 'verified' => $pro->isVerifiedFor($trade)])->values()->all();

        $registrations = [];
        foreach ($pro->documents as $document) {
            if ($document->type->isRegistration() && $document->status === DocumentStatus::Verified) {
                $registrations[] = ['label' => $document->type->label(), 'valid' => ! $document->isExpired()];
            }
        }

        return new self(
            businessName: (string) $pro->business_name,
            bio: $pro->bio === null || trim($pro->bio) === '' ? null : $pro->bio,
            trades: $trades,
            area: $pro->base_area_label,
            radiusKm: $pro->service_radius_km,
            registrations: $registrations,
            since: $pro->approved_at,
            photoUrl: $photoUrl,
        );
    }
}
