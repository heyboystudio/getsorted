<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Contracts\Data\ScopingSuggestionReply;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\Support\AssistantCalls;
use App\Domain\Assistant\Support\Redactor;
use App\Models\Service;
use App\Settings\AiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Turns a customer's own words into a suggested active service, or null so the
 * customer chooses manually (spec 007, AC1–AC3). Only ever a suggestion.
 */
final readonly class SuggestService
{
    public const int MIN_LENGTH = 10;

    public const int MAX_LENGTH = 500;

    public function __construct(private AssistantCalls $calls, private AiSettings $settings) {}

    /** @throws ValidationException */
    public function handle(string $description, string $visitorKey): ?Service
    {
        $description = trim($description);

        Validator::make(['description' => $description], [
            'description' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH],
        ], [
            'description.*' => __('Describe the problem in :min to :max characters.', ['min' => self::MIN_LENGTH, 'max' => self::MAX_LENGTH]),
        ])->validate();

        if (! $this->calls->available()) {
            return null;
        }

        // One request per visitor at a time; a double tap falls back instead of paying twice.
        $lock = Cache::lock('assistant:suggest:'.$visitorKey, 30);

        if (! $lock->get()) {
            return null;
        }

        try {
            $services = Service::query()->with('trade')
                ->where('is_active', true)->whereHas('trade', fn ($trade) => $trade->where('is_active', true))
                ->get();

            $catalogue = [];
            foreach ($services as $service) {
                $catalogue[$service->trade->key][] = $service->key;
            }

            $chosen = null;

            $this->calls->call(
                AiPurpose::SuggestService,
                'assistant:suggest-rate:'.$visitorKey,
                (int) config('sortd.ai.suggestions_per_hour'),
                fn (ScopingAssistant $assistant): ScopingSuggestionReply => $assistant->suggestService(Redactor::strip($description), $catalogue),
                function (ScopingSuggestionReply $reply) use ($services, &$chosen): bool {
                    $suggestion = $reply->suggestion;

                    if ($suggestion === null || $suggestion->confidence < $this->settings->suggestion_min_confidence || $suggestion->confidence > 1) {
                        return false;
                    }

                    $chosen = $services->first(fn (Service $service): bool => $service->key === $suggestion->serviceKey && $service->trade->key === $suggestion->tradeKey);

                    return $chosen instanceof Service;
                },
            );

            return $chosen;
        } finally {
            $lock->release();
        }
    }
}
