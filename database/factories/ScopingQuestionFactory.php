<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalogue\Enums\QuestionType;
use App\Models\ScopingQuestion;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScopingQuestion> */
final class ScopingQuestionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'key' => fake()->unique()->lexify('question_??????'),
            'prompt' => fake()->sentence().'?',
            'type' => QuestionType::SingleChoice,
            'options' => ['Yes, a lot', 'A little', 'Not sure'],
            'required' => true,
            'flags' => [],
            'sort' => fake()->numberBetween(1, 100),
        ];
    }
}
