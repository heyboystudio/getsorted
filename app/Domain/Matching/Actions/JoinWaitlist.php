<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\Trade;
use App\Models\WaitlistEntry;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** "Tell me when you have pros near me": stored by trade, approximate area and point, never a street address (spec 020, D-c). */
final class JoinWaitlist
{
    public function handle(Trade $trade, string $areaLabel, Point $location, string $firstName, string $phone, bool $consent, ?string $ip): void
    {
        $phoneE164 = PhoneNumbers::normaliseSaMobile($phone);
        $areaLabel = trim($areaLabel);

        Validator::make([
            'firstName' => trim($firstName), 'phone' => $phoneE164, 'area' => $areaLabel,
            'consent' => $consent, 'trade' => $trade->is_active,
        ], [
            'firstName' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^\+27[0-9]{9}$/'],
            'area' => ['required', 'string', 'min:2', 'max:160'],
            'consent' => ['accepted'],
            'trade' => ['accepted'],
        ])->validate();

        $phoneKey = 'waitlist:phone:'.hash_hmac('sha256', (string) $phoneE164, (string) config('app.key'));
        $ipKey = 'waitlist:ip:'.hash_hmac('sha256', (string) $ip, (string) config('app.key'));

        // Throttle every submission, duplicates included, so the response never reveals who is already waitlisted.
        if (RateLimiter::tooManyAttempts($phoneKey, (int) config('sortd.waitlist.submissions_per_hour'))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('sortd.waitlist.submissions_per_ip_hour'))) {
            throw ValidationException::withMessages(['waitlist' => __('Please try again later.')]);
        }

        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        // The unique (phone, trade, area) key makes retries and double-taps a no-op.
        $inserted = WaitlistEntry::query()->insertOrIgnore([
            'first_name' => trim($firstName),
            'phone_e164' => $phoneE164,
            'trade_id' => $trade->id,
            'area_label' => $areaLabel,
            'privacy_version' => (string) config('sortd.legal.privacy_version'),
            'consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted > 0) {
            DB::statement('update waitlist_entries set location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography where phone_e164 = ? and trade_id = ? and area_label = ?', [$location->getLongitude(), $location->getLatitude(), $phoneE164, $trade->id, $areaLabel]);
        }
    }
}
