<?php

declare(strict_types=1);

namespace App\Domain\Matching\Actions;

use App\Domain\Accounts\Support\PhoneNumbers;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class JoinWaitlist
{
    public function handle(Service $service, ?Suburb $suburb, string $suburbText, string $firstName, string $phone, bool $consent, ?string $ip): void
    {
        $phoneE164 = PhoneNumbers::normaliseSaMobile($phone);
        $suburbText = trim($suburb instanceof Suburb ? $suburb->name : $suburbText);
        $suburbKey = Str::slug($suburb instanceof Suburb ? $suburb->slug : $suburbText);

        Validator::make([
            'firstName' => trim($firstName), 'phone' => $phoneE164, 'suburb' => $suburbText,
            'consent' => $consent, 'service' => $service->is_active && $service->trade->is_active,
        ], [
            'firstName' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^\+27[0-9]{9}$/'],
            'suburb' => ['required', 'string', 'min:2', 'max:120'],
            'consent' => ['accepted'],
            'service' => ['accepted'],
        ])->validate();

        if (preg_match("/^[\\pL\\pM][\\pL\\pM\\s'’\\-]{1,119}$/u", $suburbText) !== 1 || $suburbKey === '') {
            throw ValidationException::withMessages(['suburb' => __('Enter a suburb name without an address or contact details.')]);
        }

        $phoneKey = 'waitlist:phone:'.hash_hmac('sha256', (string) $phoneE164, (string) config('app.key'));
        $ipKey = 'waitlist:ip:'.hash_hmac('sha256', (string) $ip, (string) config('app.key'));

        // Throttle every submission, duplicates included, so the response never reveals who is already waitlisted.
        if (RateLimiter::tooManyAttempts($phoneKey, (int) config('sortd.waitlist.submissions_per_hour'))
            || RateLimiter::tooManyAttempts($ipKey, (int) config('sortd.waitlist.submissions_per_ip_hour'))) {
            throw ValidationException::withMessages(['waitlist' => __('Please try again later.')]);
        }

        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        // The unique (phone, suburb, service) key makes retries and double-taps a no-op.
        WaitlistEntry::query()->insertOrIgnore([
            'first_name' => trim($firstName),
            'phone_e164' => $phoneE164,
            'suburb_text' => $suburbText,
            'suburb_key' => $suburbKey,
            'suburb_id' => $suburb?->id,
            'service_id' => $service->id,
            'privacy_version' => (string) config('sortd.legal.privacy_version'),
            'consented_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
