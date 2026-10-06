<?php

declare(strict_types=1);

use App\Livewire\Booking\Thread;
use App\Models\Property;
use App\Models\ScopingQuestion;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Helpers for driving the booking thread (spec 017) in tests.
 */

/** @return array{0: User, 1: Property} */
function bookingCustomer(string $suburb = 'musgrave'): array
{
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['label' => 'Home', 'street_address' => '7 Private Lane', 'suburb_id' => Suburb::query()->where('slug', $suburb)->value('id')]);

    return [$customer, $property];
}

/** Opens the thread with a service already chosen, as a service link does. */
function threadFor(Service $service): Testable
{
    return Livewire::test(Thread::class, ['trade' => $service->trade, 'service' => $service]);
}

/**
 * Answers every question still being asked, with $values by key or the first valid option.
 *
 * @param  array<string, mixed>  $values
 */
function answerQuestions(Testable $thread, Service $service, array $values = []): Testable
{
    foreach ($service->questions as $question) {
        if ($thread->get('stage') !== 'questions') {
            break;
        }

        /** @var ScopingQuestion $current */
        $current = $service->questions->firstWhere('key', array_values(array_diff($service->questions->pluck('key')->all(), array_keys($thread->get('answers'))))[0] ?? null);
        $thread->call('answer', $current->key, $values[$current->key] ?? match ($current->type->value) {
            'yes_no' => 'no',
            'multi_choice' => [$current->options[0]],
            'number' => 1,
            'text' => 'Kitchen',
            default => $current->options[0],
        });
    }

    return $thread;
}

/** Answers the questions and proceeds directly to secure booking details. */
function describeJob(Testable $thread, Service $service, array $values = []): Testable
{
    return answerQuestions($thread, $service, $values);
}

/** From Where & when to the summary: property, a day and window, then photos skipped. */
function bookUpToSummary(Testable $thread, Property $property, ?string $date = null, string $window = 'morning'): Testable
{
    return $thread->call('selectProperty', $property->public_id)
        ->set('preferredDate', $date ?? now()->addDays(3)->toDateString())->call('chooseWhen', $window)
        ->call('finishPhotos');
}
