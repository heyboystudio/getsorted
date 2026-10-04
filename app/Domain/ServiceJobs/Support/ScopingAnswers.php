<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Domain\Catalogue\Enums\QuestionType;
use App\Models\ScopingQuestion;
use App\Models\Service;

/**
 * Server-side checking of scoping answers (spec 005: never trust the browser).
 * Answers are stored with the prompt and type as asked, so later catalogue
 * edits don't change what the customer answered.
 */
final class ScopingAnswers
{
    private const int MAX_TEXT = 500;

    private const int MAX_NUMBER = 100000;

    /**
     * The stored form of one answer, or an error message.
     *
     * @return array{ok: true, value: array{prompt: string, type: string, answer: string|int|list<string>}}|array{ok: false, error: string}
     */
    public static function check(ScopingQuestion $question, mixed $raw): array
    {
        $answer = match ($question->type) {
            QuestionType::SingleChoice => is_string($raw) && in_array($raw, $question->options, true) ? $raw : null,
            QuestionType::MultiChoice => self::multi($question, $raw),
            QuestionType::YesNo => in_array($raw, ['yes', 'no'], true) ? $raw : null,
            QuestionType::Number => is_numeric($raw) && (int) $raw == $raw && (int) $raw >= 0 && (int) $raw <= self::MAX_NUMBER ? (int) $raw : null,
            QuestionType::Text => is_string($raw) && trim($raw) !== '' && mb_strlen($raw) <= self::MAX_TEXT ? trim($raw) : null,
        };

        if ($answer === null) {
            return ['ok' => false, 'error' => __('Please choose a valid answer.')];
        }

        return ['ok' => true, 'value' => ['prompt' => $question->prompt, 'type' => $question->type->value, 'answer' => $answer]];
    }

    public static function isBlank(mixed $raw): bool
    {
        return in_array($raw, [null, '', []], true);
    }

    /**
     * Required questions without an answer.
     *
     * @param  array<string, mixed>  $stored
     * @return list<string> question keys
     */
    public static function missingRequired(Service $service, array $stored): array
    {
        return $service->questions
            ->filter(fn (ScopingQuestion $question): bool => $question->required && ! isset($stored[$question->key]))
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Whether any stored answer is listed in its question's `urgent_if`.
     *
     * @param  array<string, array{answer?: mixed}>  $stored
     */
    public static function isUrgent(Service $service, array $stored): bool
    {
        foreach ($service->questions as $question) {
            if (self::triggersUrgent($question, $stored[$question->key]['answer'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public static function triggersUrgent(ScopingQuestion $question, mixed $answer): bool
    {
        $urgentIf = $question->flags['urgent_if'] ?? [];

        if ($answer === null || $urgentIf === []) {
            return false;
        }

        $answers = is_array($answer) ? $answer : [(string) $answer];

        return array_intersect($answers, $urgentIf) !== [];
    }

    /**
     * @return list<string>|null
     */
    private static function multi(ScopingQuestion $question, mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        foreach ($raw as $value) {
            if (! is_string($value) && ! is_int($value)) {
                return null;
            }
        }

        $values = array_values(array_unique(array_map(strval(...), $raw)));

        return array_diff($values, $question->options) === [] ? $values : null;
    }
}
