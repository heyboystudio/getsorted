<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Reviews\Actions\ReplyToReview;
use App\Domain\Reviews\Actions\SetReviewHidden;
use App\Domain\Reviews\Actions\SubmitReview;
use App\Domain\Reviews\Exceptions\CannotReview;
use App\Domain\Reviews\Support\RatingSummary;
use App\Domain\ServiceJobs\Actions\MarkJobDone;
use App\Filament\Admin\Resources\Reviews\Pages\ListReviews;
use App\Filament\Admin\Resources\Reviews\ReviewResource;
use App\Livewire\Account\Jobs\Show as CustomerJob;
use App\Livewire\Account\ProProfile as CustomerProProfile;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/** Spec 025: reviews after a finished job. */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

/** @return array{ServiceJob, Pro, User} a finished job, its pro and its client */
function finishedJob(?Pro $pro = null): array
{
    $job = ServiceJob::factory()->open()->create();
    $quote = Quote::factory()->create(['service_job_id' => $job->id, 'pro_id' => ($pro ?? Pro::factory()->approved()->create())->id]);
    app(AcceptQuote::class)->handle($job->customer, $quote);
    app(MarkJobDone::class)->handle($job->customer, $job->refresh());

    return [$job->refresh(), $quote->pro, $job->customer];
}

// --- Leaving a review (AC1–AC4) -----------------------------------------------------------------

it('lets the client rate the pro after the job is done and tells the pro', function (): void {
    [$job, $pro, $customer] = finishedJob();

    $review = app(SubmitReview::class)->handle($customer, $job, 5, 'Fixed it fast. Call me on 082 123 4567');

    expect($review->rating)->toBe(5)->and($review->pro_id)->toBe($pro->id)->and($review->comment)->not->toContain('082 123 4567')->and($review->comment)->toContain('Fixed it fast')
        ->and(noticeCount($pro->user, 'review_received'))->toBe(1);
});

it('refuses a review before the job is done, from outsiders, twice, out of range or after the window', function (): void {
    $open = ServiceJob::factory()->open()->create();
    [$job, , $customer] = finishedJob();

    expect(fn () => app(SubmitReview::class)->handle($open->customer, $open, 5, null))->toThrow(CannotReview::class)
        ->and(fn () => app(SubmitReview::class)->handle(User::factory()->customer()->create(), $job, 5, null))->toThrow(CannotReview::class)
        ->and(fn () => app(SubmitReview::class)->handle($customer, $job, 0, null))->toThrow(CannotReview::class)
        ->and(fn () => app(SubmitReview::class)->handle($customer, $job, 6, null))->toThrow(CannotReview::class)
        ->and(fn () => app(SubmitReview::class)->handle($customer, $job, 4, str_repeat('a', 1001)))->toThrow(CannotReview::class);

    app(SubmitReview::class)->handle($customer, $job, 4, null);
    expect(fn () => app(SubmitReview::class)->handle($customer, $job, 5, null))->toThrow(CannotReview::class);

    [$old, , $oldCustomer] = finishedJob();
    $this->travel(61)->days();
    expect(fn () => app(SubmitReview::class)->handle($oldCustomer, $old, 5, null))->toThrow(CannotReview::class);
});

it('lets the client review from their job page, once', function (): void {
    [$job, , $customer] = finishedJob();
    $this->actingAs($customer);

    Livewire::test(CustomerJob::class, ['job' => $job])
        ->assertSee('How was the work?')->assertSeeHtml('disabled')
        ->call('setRating', 4)->set('reviewComment', 'Tidy and on time')->call('submitReview')->assertHasNoErrors()
        ->assertSee('Your review')->assertSee('Tidy and on time')->assertDontSee('How was the work?');

    expect(Review::query()->sole()->rating)->toBe(4);
});

// --- Replying (AC5–AC6) -------------------------------------------------------------------------

it('lets the reviewed pro reply once, masks the reply and tells the client', function (): void {
    [$job, $pro, $customer] = finishedJob();
    $review = app(SubmitReview::class)->handle($customer, $job, 3, 'Late');

    app(ReplyToReview::class)->handle($pro->user, $review, 'Sorry, traffic. Email me at pro@example.com');

    $review->refresh();
    expect($review->reply)->toContain('Sorry, traffic')->and($review->reply)->not->toContain('pro@example.com')->and($review->replied_at)->not->toBeNull()
        ->and(noticeCount($customer, 'review_reply'))->toBe(1)
        ->and(fn () => app(ReplyToReview::class)->handle($pro->user, $review, 'A second reply'))->toThrow(CannotReview::class)
        ->and(fn () => app(ReplyToReview::class)->handle(Pro::factory()->approved()->create()->user, Review::query()->sole(), 'Not mine'))->toThrow(CannotReview::class)
        ->and(fn () => app(ReplyToReview::class)->handle($pro->user, $review, 'x'))->toThrow(CannotReview::class);
});

it('lets the pro read the review and reply from their job page', function (): void {
    [$job, $pro, $customer] = finishedJob();
    app(SubmitReview::class)->handle($customer, $job, 5, 'Brilliant');
    $invite = ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, 'status' => 'quoted']);
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => $invite])
        ->assertSee('Brilliant')->set('replyText', 'Thank you!')->call('reply')->assertHasNoErrors()->assertSee('Thank you!')->assertDontSee('Send reply');
});

// --- Ratings shown (AC7–AC8) --------------------------------------------------------------------

it('shows no rating until a pro has a review, then the average and count of visible reviews', function (): void {
    [$job, $pro, $customer] = finishedJob();
    expect(RatingSummary::for($pro))->toBeNull()->and(RatingSummary::label($pro))->toBe('New on GetSorted');

    app(SubmitReview::class)->handle($customer, $job, 5, null);
    [$second, , $other] = finishedJob($pro);
    app(SubmitReview::class)->handle($other, $second, 4, null);

    expect(RatingSummary::for($pro))->toBe(['average' => 4.5, 'count' => 2])->and(RatingSummary::label($pro))->toBe('★ 4.5 (2 reviews)');
});

it('shows the rating and reviews on the pro profile the client sees', function (): void {
    [$job, $pro, $customer] = finishedJob();
    app(SubmitReview::class)->handle($customer, $job, 5, 'Excellent work');
    $other = ServiceJob::factory()->open()->create(['customer_id' => $customer->id]);
    $quote = Quote::factory()->create(['service_job_id' => $other->id, 'pro_id' => $pro->id]);
    $this->actingAs($customer);

    Livewire::test(CustomerProProfile::class, ['quote' => $quote])->assertSee('★ 5.0 (1 review)')->assertSee('Excellent work')->assertSee($customer->first_name);
});

// --- Admin (AC9) --------------------------------------------------------------------------------

it('lets an admin hide a review with a reason, and show it again', function (): void {
    [$job, $pro, $customer] = finishedJob();
    $review = app(SubmitReview::class)->handle($customer, $job, 1, 'Awful');
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);

    app(SetReviewHidden::class)->handle($admin, $review, true, 'Abusive language');
    expect($review->refresh()->hidden_at)->not->toBeNull()->and($review->hidden_by)->toBe($admin->id)->and(RatingSummary::for($pro))->toBeNull()
        ->and(fn () => app(ReplyToReview::class)->handle($pro->user, $review, 'Reply to hidden'))->toThrow(CannotReview::class)
        ->and(fn () => app(SetReviewHidden::class)->handle($admin, $review, true, ''))->toThrow(CannotReview::class)
        ->and(fn () => app(SetReviewHidden::class)->handle($customer, $review, false))->toThrow(CannotReview::class);

    app(SetReviewHidden::class)->handle($admin, $review, false);
    expect(RatingSummary::for($pro)['count'])->toBe(1);
});

it('lists reviews for admins only', function (): void {
    [$job, , $customer] = finishedJob();
    app(SubmitReview::class)->handle($customer, $job, 5, 'Great');
    Filament::setCurrentPanel('admin');

    $this->actingAs($customer);
    expect(ReviewResource::canViewAny())->toBeFalse();

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Livewire::test(ListReviews::class)->assertSee('Great')->assertSee('Hide');
});
