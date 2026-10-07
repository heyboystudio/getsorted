<?php

declare(strict_types=1);

use App\Contracts\Data\ChatRequest;
use App\Domain\Assistant\State\BookingState;
use App\Domain\Assistant\Support\BookingToolbox;
use App\Integrations\Anthropic\AnthropicScopingAssistant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * The whole agent loop through the real Laravel AI SDK and its Gemini gateway, with only the HTTP layer faked
 * (spec 020): Gemini asks for tool calls, our toolbox validates and applies them, Gemini sees the results and
 * answers in plain text. No network and no key are used. This proves the wiring, not the model's judgement.
 */
function geminiChatRequest(string $customer): ChatRequest
{
    $transcript = [['role' => 'customer', 'text' => $customer]];

    return new ChatRequest(new BookingToolbox(new BookingState, ['plumbing' => 'Plumbing', 'electrical' => 'Electrical'], $transcript), $transcript, ['GetSorted is free for customers.']);
}

beforeEach(function (): void {
    config([
        'getsorted.ai.provider' => 'gemini', 'getsorted.ai.model' => 'gemini-2.5-flash',
        'ai.providers.gemini.key' => 'test-key', 'ai.providers.gemini.driver' => 'gemini',
    ]);
});

it('runs tool calls through the toolbox and returns the model’s plain-text answer', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push([
            'status' => 'requires_action',
            'steps' => [
                ['type' => 'function_call', 'id' => 'c1', 'name' => 'set_trade', 'arguments' => ['trade_key' => 'plumbing']],
                ['type' => 'function_call', 'id' => 'c2', 'name' => 'add_job_fact', 'arguments' => ['text' => 'tap drips', 'evidence' => 'tap is dripping']],
                ['type' => 'function_call', 'id' => 'c3', 'name' => 'add_job_fact', 'arguments' => ['text' => 'made up', 'evidence' => 'never said']],
            ],
            'usage' => ['total_input_tokens' => 800, 'total_output_tokens' => 40],
        ])
        ->push([
            'status' => 'completed',
            'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Sounds like a dripping tap. Does it drip when fully closed?']]]],
            'usage' => ['total_input_tokens' => 900, 'total_output_tokens' => 20],
        ])]);
    $request = geminiChatRequest('My tap is dripping');

    $reply = (new AnthropicScopingAssistant)->chat($request);

    expect($reply->reply)->toBe('Sounds like a dripping tap. Does it drip when fully closed?')
        ->and($reply->steps)->toBe(2)->and($reply->toolCalls)->toBe(3)
        ->and($reply->usage->provider)->toBe('gemini')->and($reply->usage->inputTokens)->toBe(1700)
        ->and($request->toolbox->state->tradeKey)->toBe('plumbing')
        ->and($request->toolbox->state->factTexts())->toBe(['tap drips'])
        ->and($request->toolbox->rejected)->toBe(1);
});

it('sends Gemini the eight tools, the short instructions, a low temperature and the booking state, and returns tool results', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['status' => 'requires_action', 'steps' => [['type' => 'function_call', 'id' => 'c1', 'name' => 'set_trade', 'arguments' => ['trade_key' => 'roofing']]]])
        ->push(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'We do not offer roofing.']]]]])]);

    (new AnthropicScopingAssistant)->chat(geminiChatRequest('My roof leaks'));

    $requests = Http::recorded()->map(fn (array $pair): Request => $pair[0])->values();
    expect($requests)->toHaveCount(2);

    $first = $requests[0];
    $body = $first->data();
    $toolNames = collect($body['tools'])->flatMap(fn (array $tool): array => collect($tool['function_declarations'] ?? [$tool])->pluck('name')->all())->all();

    expect($first->url())->toContain('/interactions')->and($first->hasHeader('x-goog-api-key', 'test-key'))->toBeTrue()
        ->and($toolNames)->toBe(['get_booking_state', 'set_trade', 'add_job_fact', 'remove_job_fact', 'set_urgency', 'park_job', 'offer_next_step'])
        ->and($body['system_instruction'])->toContain('You are Siya')->and(strlen((string) $body['system_instruction']))->toBeLessThan(3000)
        ->and($body['generation_config']['temperature'])->toBe(0.3)
        ->and(json_encode($body['input']))->toContain('booking_state')->toContain('My roof leaks');

    // The second request replays the tool call with our validated result, telling the model "Unknown trade key.".
    expect(json_encode($requests[1]->data()['input']))->toContain('function_result')->toContain('Unknown trade key.');
});
