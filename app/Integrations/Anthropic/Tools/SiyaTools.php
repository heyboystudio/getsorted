<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic\Tools;

use App\Domain\Assistant\Support\BookingToolbox;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/** The tools Siya's model can call. Each is a thin wrapper: every rule lives in BookingToolbox. */
final class SiyaTools
{
    /** @return list<ToolboxTool> */
    public static function for(BookingToolbox $toolbox): array
    {
        return [
            new ToolboxTool(
                'get_booking_state',
                'Read what has been established so far: trade, facts, urgency, parked jobs and what is still needed. Call it before relying on memory.',
                fn (JsonSchema $schema): array => [],
                fn (Request $request): array => $toolbox->digest(),
            ),
            new ToolboxTool(
                'set_trade',
                'Set or correct the trade for this job, using an exact key from available_trades. Call it as soon as the problem clearly belongs to one trade.',
                fn (JsonSchema $schema): array => ['trade_key' => $schema->string()->required()],
                fn (Request $request): array => $toolbox->setTrade((string) $request['trade_key']),
            ),
            new ToolboxTool(
                'add_job_fact',
                'Record one short fact a tradesperson would want, in plain words (for example "tap drips when fully closed"). Evidence must be an exact quote from the customer. Add every supported fact, even from the first message.',
                fn (JsonSchema $schema): array => ['text' => $schema->string()->required(), 'evidence' => $schema->string()->required()],
                fn (Request $request): array => $toolbox->addFact((string) $request['text'], (string) $request['evidence']),
            ),
            new ToolboxTool(
                'remove_job_fact',
                'Remove a fact that the customer corrected or withdrew. Use the id from get_booking_state.',
                fn (JsonSchema $schema): array => ['id' => $schema->string()->required()],
                fn (Request $request): array => $toolbox->removeFact((string) $request['id']),
            ),
            new ToolboxTool(
                'set_urgency',
                'Mark the job urgent when the customer needs help today or the problem is getting worse, or clear it.',
                fn (JsonSchema $schema): array => ['urgent' => $schema->boolean()->required()],
                fn (Request $request): array => $toolbox->setUrgency((bool) $request['urgent']),
            ),
            new ToolboxTool(
                'park_job',
                'Note a second, unrelated job the customer mentioned, so it can be booked separately afterwards. One job is booked at a time.',
                fn (JsonSchema $schema): array => ['summary' => $schema->string()->required()],
                fn (Request $request): array => $toolbox->parkJob((string) $request['summary']),
            ),
            new ToolboxTool(
                'offer_next_step',
                'When a trade and at least one problem fact are known and the customer wants to go ahead, call this so the app shows the secure control for that step.',
                fn (JsonSchema $schema): array => ['step' => $schema->string()->enum(BookingToolbox::STEPS)->required()],
                fn (Request $request): array => $toolbox->offerNextStep((string) $request['step']),
            ),
            new ToolboxTool(
                'flag_emergency',
                'Call this if the customer describes immediate danger to life or property. The app then shows reviewed emergency guidance.',
                fn (JsonSchema $schema): array => ['reason' => $schema->string()->required()],
                fn (Request $request): array => $toolbox->flagEmergency((string) $request['reason']),
            ),
        ];
    }
}
