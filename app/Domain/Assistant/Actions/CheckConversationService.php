<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Matching\EligibleProsQuery;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Models\Property;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** A corrected classification must be active, covered and compatible with an existing urgent window. */
final readonly class CheckConversationService
{
    public function __construct(private EligibleProsQuery $eligiblePros) {}

    /** @return array{covered: bool, windowCompatible: bool} */
    public function handle(Service $service, ?Property $property, ?User $customer, ?TimeWindow $window): array
    {
        $service->loadMissing('trade');
        abort_unless($service->is_active && $service->trade->is_active, 404);
        $covered = true;

        if ($property instanceof Property) {
            abort_unless($customer instanceof User, 404);
            Gate::forUser($customer)->authorize('view', $property);
            $covered = $this->eligiblePros->covers($service, $property->suburb, $customer);
        }

        return ['covered' => $covered, 'windowCompatible' => $window !== TimeWindow::Today || $service->emergency_capable];
    }
}
