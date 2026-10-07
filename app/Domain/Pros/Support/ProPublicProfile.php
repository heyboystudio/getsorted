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
     * @param  array<string, list<string>>  $trades  trade name => service names
     * @param  list<string>  $suburbs
     * @param  list<array{label: string, valid: bool}>  $registrations
     */
    public function __construct(
        public string $businessName,
        public ?string $bio,
        public array $trades,
        public array $suburbs,
        public array $registrations,
        public ?CarbonImmutable $since,
        public ?string $photoUrl,
    ) {}

    public static function from(Pro $pro, ?string $photoUrl = null): self
    {
        $pro->loadMissing(['services.trade', 'serviceAreas', 'documents']);

        $trades = [];
        foreach ($pro->services->sortBy('name') as $service) {
            $trades[$service->trade->name][] = $service->name;
        }
        ksort($trades);

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
            suburbs: $pro->serviceAreas->pluck('name')->sort()->values()->all(),
            registrations: $registrations,
            since: $pro->approved_at,
            photoUrl: $photoUrl,
        );
    }
}
