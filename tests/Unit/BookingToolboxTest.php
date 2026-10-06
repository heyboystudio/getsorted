<?php

declare(strict_types=1);

use App\Domain\Assistant\State\BookingState;
use App\Domain\Assistant\Support\BookingToolbox;

/** Spec 020: the toolbox is the only way the model changes the booking state, so every rule is tested here. */
function toolbox(string $customerSaid = 'my kitchen tap is dripping non stop', ?BookingState $state = null): BookingToolbox
{
    return new BookingToolbox(
        $state ?? new BookingState,
        ['plumbing' => 'Plumbing', 'electrical' => 'Electrical'],
        [['role' => 'assistant', 'text' => 'Hi'], ['role' => 'customer', 'text' => $customerSaid]],
    );
}

it('records a fact only when the evidence is a quote from the customer', function (): void {
    $toolbox = toolbox();

    expect($toolbox->addFact('kitchen tap drips constantly', 'kitchen tap is dripping')['ok'])->toBeTrue()
        ->and($toolbox->state->factTexts())->toBe(['kitchen tap drips constantly']);

    $result = $toolbox->addFact('geyser is old', 'the geyser is ten years old');

    expect($result['ok'])->toBeFalse()->and($toolbox->state->facts)->toHaveCount(1)->and($toolbox->rejected)->toBe(1);
});

it('matches evidence despite case and punctuation', function (): void {
    expect(toolbox('The DB keeps tripping, since the rain!')->addFact('DB trips since the rain', 'db keeps tripping since the rain')['ok'])->toBeTrue();
});

it('does not store the same fact twice', function (): void {
    $toolbox = toolbox();
    $first = $toolbox->addFact('tap drips', 'tap is dripping');
    $second = $toolbox->addFact('Tap drips', 'tap is dripping');

    expect($second['ok'])->toBeTrue()->and($second['duplicate'])->toBeTrue()->and($second['id'])->toBe($first['id'])
        ->and($toolbox->state->facts)->toHaveCount(1);
});

it('rejects facts with prices, contact details or too much text', function (string $text): void {
    $toolbox = toolbox('call me on 0821234567 about R500 for the tap');

    expect($toolbox->addFact($text, 'about R500 for the tap')['ok'])->toBeFalse()->and($toolbox->state->facts)->toBe([]);
})->with(['price' => 'will pay R500', 'phone' => 'call 082 123 4567', 'long' => str_repeat('very long fact ', 20)]);

it('only sets active trades and keeps the facts when the trade is corrected', function (): void {
    $toolbox = toolbox();
    $toolbox->addFact('tap drips', 'tap is dripping');

    expect($toolbox->setTrade('roofing')['ok'])->toBeFalse()
        ->and($toolbox->setTrade('plumbing')['ok'])->toBeTrue()
        ->and($toolbox->setTrade('electrical')['changed'])->toBeTrue()
        ->and($toolbox->state->tradeKey)->toBe('electrical')
        ->and($toolbox->state->facts)->toHaveCount(1);
});

it('removes a fact by id and reports an unknown id', function (): void {
    $toolbox = toolbox();
    $id = $toolbox->addFact('tap drips', 'tap is dripping')['id'];

    expect($toolbox->removeFact('nope')['ok'])->toBeFalse()
        ->and($toolbox->removeFact($id)['ok'])->toBeTrue()
        ->and($toolbox->state->facts)->toBe([]);
});

it('offers the next step only once a trade and a fact are known', function (): void {
    $toolbox = toolbox();

    $early = $toolbox->offerNextStep('sign_in');
    expect($early['ok'])->toBeFalse()->and($early['still_needed'])->toBe(['trade', 'problem'])->and($toolbox->state->nextStepOffered)->toBeFalse();

    $toolbox->setTrade('plumbing');
    $toolbox->addFact('tap drips', 'tap is dripping');

    expect($toolbox->offerNextStep('bogus')['ok'])->toBeFalse()
        ->and($toolbox->offerNextStep('sign_in')['ok'])->toBeTrue()
        ->and($toolbox->state->nextStepOffered)->toBeTrue();
});

it('parks at most three other jobs', function (): void {
    $toolbox = toolbox();

    foreach (['bathroom light trips', 'gate motor stuck', 'paint the lounge', 'fix a fence'] as $job) {
        $toolbox->parkJob($job);
    }

    expect($toolbox->state->parked)->toHaveCount(3);
});

it('flags an emergency without changing the booking', function (): void {
    $toolbox = toolbox();

    expect($toolbox->flagEmergency('smoke from the socket')['ok'])->toBeTrue()
        ->and($toolbox->emergencyFlagged)->toBeTrue()
        ->and($toolbox->state->facts)->toBe([]);
});

it('round-trips the state through an array', function (): void {
    $state = new BookingState('plumbing', [['id' => 'f1', 'text' => 'tap drips', 'turn' => 1]], true, ['gate motor stuck'], 2, true);

    expect(BookingState::fromArray($state->toArray())->toArray())->toBe($state->toArray());
});
