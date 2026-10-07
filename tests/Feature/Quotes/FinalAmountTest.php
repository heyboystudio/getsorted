<?php

declare(strict_types=1);

use App\Contracts\MessagingChannel;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Quotes\Actions\ChangeFinalAmount;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Enums\LineKind;
use App\Domain\Quotes\Enums\ProposalStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\ServiceJobs\Actions\CancelOverPrice;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Livewire\Account\Jobs\FinalAmount as CustomerPrice;
use App\Livewire\Jobs\Chat;
use App\Livewire\Pros\Jobs\FinalAmount as ProPrice;
use App\Models\FinalAmountProposal;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\ServiceJobInvite;
use App\Models\Suburb;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Spec 018 part 2: the accepted pro changes the final amount; the customer approves increases.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->customer = User::factory()->customer()->create();
    $property = Property::factory()->for($this->customer)->create(['suburb_id' => Suburb::query()->where('slug', 'musgrave')->value('id')]);
    $this->job = ServiceJob::factory()->open()->forProperty($property)->create(['service_id' => Service::query()->where('key', 'leak_repair')->value('id')]);
    $this->pro = Pro::factory()->approved()->create(['business_name' => 'Dlamini Plumbing']);
    ServiceJobInvite::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->pro->id, 'status' => 'quoted']);
    $this->quote = Quote::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->pro->id, 'total_cents' => 57050, 'labour_cents' => 45000, 'materials_cents' => 12050]);
    app(AcceptQuote::class)->handle($this->customer, $this->quote);
    $this->job->refresh();
    $this->change = app(ChangeFinalAmount::class);
});

/** @return list<QuoteLineData> */
function lines(int ...$rand): array
{
    return array_map(fn (int $amount, int $index): QuoteLineData => new QuoteLineData($index === 0 ? LineKind::Labour : LineKind::Materials, "Line {$index}", '1', $amount * 100), $rand, array_keys($rand));
}

it('agrees on the accepted estimate total when a quote is accepted (AC14)', function (): void {
    expect($this->job->status)->toBe(ServiceJobStatus::Scheduled)
        ->and($this->job->agreed_final_cents)->toBe(57050)
        ->and($this->change->agreedCents($this->job))->toBe(57050);
});

it('waits for the customer on an increase, recalculating totals on the server (AC9)', function (): void {
    $proposal = $this->change->propose($this->pro->user, $this->job, lines(600, 150), 'The valve under the sink also needs replacing.');

    expect($proposal->status)->toBe(ProposalStatus::Pending)
        ->and($proposal->total_cents)->toBe(75000)
        ->and($proposal->previous_total_cents)->toBe(57050)
        ->and($proposal->differenceCents())->toBe(17950)
        ->and($this->job->fresh()->agreed_final_cents)->toBe(57050);
    expect(ServiceJobEvent::query()->where('event_type', 'final_amount_proposed')->exists())->toBeTrue();
    app(MessagingChannel::class)->assertSent('final_amount_proposed', fn ($message): bool => $message->phoneE164 === $this->customer->phone_e164 && $message->parameters['amount'] === 'R 750.00');
});

it('adds VAT for VAT-registered pros, as estimates do', function (): void {
    $this->pro->forceFill(['vat_number' => '4123456789'])->save();

    expect($this->change->propose($this->pro->user, $this->job, lines(1000), 'More work than expected here.')->total_cents)->toBe(115000);
});

it('makes an approved increase the agreed amount and tells the pro (AC11)', function (): void {
    $proposal = $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.');
    $this->change->decide($this->customer, $proposal, approve: true);

    expect($proposal->fresh()->status)->toBe(ProposalStatus::Approved)
        ->and($this->job->fresh()->agreed_final_cents)->toBe(70000);
    expect(ServiceJobEvent::query()->where('event_type', 'final_amount_approved')->sole()->payload)->toMatchArray(['from_cents' => 57050, 'to_cents' => 70000]);
    app(MessagingChannel::class)->assertSent('final_amount_approved', fn ($message): bool => $message->phoneE164 === $this->pro->user->phone_e164);
});

it('keeps the agreed amount on a decline, allows one more try, then blocks further increases (AC12, decision 3)', function (): void {
    $first = $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.');
    $this->change->decide($this->customer, $first, approve: false, note: 'Too much');
    expect($first->fresh()->customer_note)->toBe('Too much')->and($this->job->fresh()->agreed_final_cents)->toBe(57050);
    app(MessagingChannel::class)->assertSent('final_amount_declined');

    $second = $this->change->propose($this->pro->user, $this->job, lines(650), 'Trimmed it to just the pipe.');
    $this->change->decide($this->customer, $second, approve: false);

    expect(fn () => $this->change->propose($this->pro->user, $this->job, lines(620), 'One more try at the price.'))
        ->toThrow(ValidationException::class, 'The customer has declined two increases');
    // Lowering is still allowed.
    expect($this->change->propose($this->pro->user, $this->job, lines(500), 'I can do it for less after all.')->status)->toBe(ProposalStatus::Applied);
});

it('applies a lower amount straight away and tells the customer (AC13, decision 2)', function (): void {
    $proposal = $this->change->propose($this->pro->user, $this->job, lines(400), 'Only a washer was needed.');

    expect($proposal->status)->toBe(ProposalStatus::Applied)
        ->and($this->job->fresh()->agreed_final_cents)->toBe(40000);
    app(MessagingChannel::class)->assertSent('final_amount_lowered', fn ($message): bool => $message->phoneE164 === $this->customer->phone_e164);
});

it('keeps one pending proposal: a new one replaces it, and the pro can withdraw (AC10)', function (): void {
    $first = $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.');
    $second = $this->change->propose($this->pro->user, $this->job, lines(680), 'Found a cheaper part for it.');

    expect($first->fresh()->status)->toBe(ProposalStatus::Withdrawn)->and($second->version)->toBe(2);

    $this->change->withdraw($this->pro->user, $second);
    expect($second->fresh()->status)->toBe(ProposalStatus::Withdrawn);
    expect(fn () => $this->change->decide($this->customer, $second->fresh(), approve: true))->toThrow(CannotQuote::class);
});

it('refuses the same amount, a short reason and bad lines', function (): void {
    // 450 + 120.50 = the agreed R 570.50.
    $same = [new QuoteLineData(LineKind::Labour, 'Labour', '1', 45000), new QuoteLineData(LineKind::Materials, 'Parts', '1', 12050)];
    expect(fn () => $this->change->propose($this->pro->user, $this->job, $same, 'Exactly the same as before.'))->toThrow(ValidationException::class, 'same as the agreed amount');
    expect(fn () => $this->change->propose($this->pro->user, $this->job, lines(700), 'Short'))->toThrow(ValidationException::class);
    expect(fn () => $this->change->propose($this->pro->user, $this->job, [], 'No lines at all here.'))->toThrow(ValidationException::class);
    expect(FinalAmountProposal::query()->count())->toBe(0);
});

it('lets only the accepted pro propose and only the job’s customer decide (security)', function (): void {
    $other = Pro::factory()->approved()->create();
    expect(fn () => $this->change->propose($other->user, $this->job, lines(700), 'Not my job but trying anyway.'))->toThrow(NotFoundHttpException::class);
    expect(fn () => $this->change->propose($this->customer, $this->job, lines(700), 'Customers cannot propose a price.'))->toThrow(NotFoundHttpException::class);

    $proposal = $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.');
    expect(fn () => $this->change->decide($this->pro->user, $proposal, approve: true))->toThrow(NotFoundHttpException::class);
    expect(fn () => $this->change->decide(User::factory()->customer()->create(), $proposal, approve: true))->toThrow(NotFoundHttpException::class);
    expect($proposal->fresh()->status)->toBe(ProposalStatus::Pending);
});

it('only changes the price while the job is booked or in progress', function (ServiceJobStatus $status): void {
    $this->job->forceFill(['status' => $status])->save();

    expect(fn () => $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.'))->toThrow(CannotQuote::class);
})->with([ServiceJobStatus::AwaitingFinalPayment, ServiceJobStatus::Completed, ServiceJobStatus::Cancelled, ServiceJobStatus::Disputed]);

it('lets the pro cancel over price after a decline, before work starts, with the deposit owed back in full (AC12, decision 4)', function (): void {
    expect(fn () => app(CancelOverPrice::class)->handle($this->pro->user, $this->job))->toThrow(CannotQuote::class);

    $proposal = $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.');
    $this->change->decide($this->customer, $proposal, approve: false);
    app(CancelOverPrice::class)->handle($this->pro->user, $this->job);

    $job = $this->job->fresh();
    expect($job->status)->toBe(ServiceJobStatus::Cancelled)->and($job->cancel_reason)->toBe('price_not_agreed');
    expect(ServiceJobEvent::query()->where('event_type', 'cancelled_price_not_agreed')->sole()->payload)->toHaveKey('refund_deposit_cents');
    app(MessagingChannel::class)->assertSent('cancelled_price_not_agreed', fn ($message): bool => $message->phoneE164 === $this->customer->phone_e164);
});

it('sends the pro to support instead once work has started', function (): void {
    $proposal = $this->change->propose($this->pro->user, $this->job, lines(700), 'Needs a new pipe section too.');
    $this->change->decide($this->customer, $proposal, approve: false);
    $this->job->forceFill(['status' => ServiceJobStatus::InProgress])->save();

    expect(fn () => app(CancelOverPrice::class)->handle($this->pro->user, $this->job))->toThrow(CannotQuote::class, 'contact Sortd support');
});

it('works end to end on the pro panel and the customer card', function (): void {
    $this->actingAs($this->pro->user);
    Livewire::test(ProPrice::class, ['jobPublicId' => $this->job->public_id])
        ->assertSee('Agreed: R 570.50')->call('start')
        ->assertCount('lines', 1)
        ->set('lines', [['kind' => 'labour', 'description' => 'Labour and new valve', 'quantity' => '1', 'unitPrice' => '800']])
        ->assertSee('New total: R 800.00')->assertSee('The customer must approve an increase.')
        ->set('reason', 'The valve needs replacing as well.')->call('propose')->assertHasNoErrors()
        ->assertSee('You proposed R 800.00. Waiting for the customer to approve.');

    $this->actingAs($this->customer);
    Livewire::test(CustomerPrice::class, ['jobPublicId' => $this->job->public_id])
        ->assertSee('Your pro proposed a new final amount')->assertSee('+R 229.50')->assertSee('The valve needs replacing as well.')
        ->call('approve', FinalAmountProposal::query()->sole()->public_id)
        ->assertSee('Agreed final amount: R 800.00');

    Livewire::test(Chat::class, ['jobPublicId' => $this->job->public_id, 'proPublicId' => $this->pro->public_id, 'title' => 'Dlamini Plumbing'])
        ->assertSee('Final amount: R 570.50 → R 800.00')->assertSee('Approved');
});

it('keeps other customers and pros away from the price panels (security)', function (): void {
    $this->actingAs(User::factory()->customer()->create());
    Livewire::test(CustomerPrice::class, ['jobPublicId' => $this->job->public_id])->assertNotFound();

    $this->actingAs(Pro::factory()->approved()->create()->user);
    Livewire::test(ProPrice::class, ['jobPublicId' => $this->job->public_id])->assertNotFound();
});
