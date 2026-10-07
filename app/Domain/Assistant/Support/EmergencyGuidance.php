<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

/** Safety routing remains available when the model is disabled or unavailable. */
final class EmergencyGuidance
{
    public static function required(string $text): bool
    {
        $activeClauses = [];
        foreach (preg_split('/[.!?;,\n]+|\b(?:but|and|now|currently)\b/u', mb_strtolower($text)) ?: [] as $sentence) {
            // Scope history to a clause; other clauses may describe a current emergency.
            if (preg_match('/\b(?:last year|last month|years? ago|months? ago|used to|was extinguished|has been extinguished|was put out)\b/u', $sentence) === 1) {
                continue;
            }

            // Remove only a negated hazard, rather than discarding independent danger in the same clause.
            $sentence = (string) preg_replace([
                '/\b(?:smoke (?:alarm|detector)|spark plug)\b/u',
                '/\b(?:no|without)\s+(?:(?:any|signs? of)\s+)?(?:fire|smoke|sparks?|gas(?: leak)?|danger)(?:\s+or\s+(?:fire|smoke|sparks?|gas|danger))*\b/u',
                '/\b(?:do not|don[’\x27]t|cannot|can[’\x27]t)\s+(?:see|smell)\s+(?:any\s+)?(?:fire|smoke|sparks?|gas)\b/u',
                '/\b(?:fire|flames?)\s+(?:is|are)\s+out\b/u',
            ], '', $sentence);

            $activeClauses[] = $sentence;

            if (preg_match('/\b(?:fire\s*(?:brigade|fighters?|department)|ambulance|emergency services|emergency help|there (?:is|are) (?:a )?(?:fire|flames?)|house is on fire|home is on fire|on fire|electrocut\w*|gas leak|smell(?:ing)? gas|smell of gas|burning smell|sparks?|sparking|smoke)\b/u', $sentence) === 1
                || preg_match('/\b(?:fire|flames?)\b.{0,30}\b(?:house|home|kitchen|room|spreading|now)\b/u', $sentence) === 1
                || preg_match('/\b(?:flood\w*|water)\b.{0,45}\b(?:electric\w*|socket|plug|wire\w*)\b/u', $sentence) === 1) {
                return true;
            }
        }

        // Water and live electrics can be related across conjunctions: keep the active context together.
        $active = implode(' ', $activeClauses);

        return preg_match('/\b(?:flood\w*|water)\b.{0,100}\b(?:electric\w*|socket|plug|wire\w*)\b/u', $active) === 1
            || preg_match('/\b(?:electric\w*|socket|plug|wire\w*)\b.{0,100}\b(?:flood\w*|water)\b/u', $active) === 1;
    }

    public static function message(): string
    {
        return __('If there is immediate danger, call 112 from your cellphone or eThekwini Fire and Emergency on 031 361 0000 now. Get Sorted cannot dispatch emergency services. If a building is on fire, get out and do not go back inside. Follow the emergency operator’s instructions. We can help with a later repair after you have contacted emergency services.');
    }
}
