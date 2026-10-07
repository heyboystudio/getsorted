<?php

declare(strict_types=1);

use App\Contracts\Data\MessageChannel;
use App\Contracts\MessagingChannel;
use App\Domain\Accounts\Actions\CompleteDataRequest;
use App\Domain\Accounts\Actions\RequestEmailChange;
use App\Domain\Accounts\Enums\DataRequestStatus;
use App\Domain\Accounts\Enums\DataRequestType;
use App\Domain\Accounts\Enums\Role;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Filament\Admin\Resources\DataRequests\Pages\ListDataRequests;
use App\Jobs\SendChatNotification;
use App\Jobs\SendJobExpiredMessage;
use App\Jobs\SendJobPostedMessage;
use App\Jobs\SendQuoteMessage;
use App\Livewire\Account\Settings\Notifications;
use App\Livewire\Account\Settings\Privacy;
use App\Livewire\Account\Settings\Profile;
use App\Mail\VerifyEmailAddress;
use App\Models\DataRequest;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use App\Models\WaitlistEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function accountChat(ServiceJob $job, int $proMessages = 1, ?Pro $pro = null): JobConversation
{
    $pro ??= Pro::factory()->approved()->create(['business_name' => 'Dlamini Plumbing']);
    ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id]);
    $conversation = JobConversation::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id]);
    JobMessage::factory()->count($proMessages)->create(['job_conversation_id' => $conversation->id, 'sender_type' => 'pro', 'body' => 'Secret chat text']);

    return $conversation;
}

beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create(['first_name' => 'Nomvula', 'last_name' => 'Zulu', 'email' => 'nomvula@example.com']);
    $this->actingAs($this->customer);
});

// --- Messages (AC12) ---------------------------------------------------------------------

it('lists chats across all jobs with unread counts in the inbox (spec 021, AC12)', function (): void {
    $first = ServiceJob::factory()->open()->create(['customer_id' => $this->customer->id]);
    $second = ServiceJob::factory()->open()->create(['customer_id' => $this->customer->id]);
    accountChat($first, 2);
    accountChat($second, 1);

    $this->get(route('messages'))->assertOk()
        ->assertSee(route('jobs.show', $first).'#chats', false)->assertSee(route('jobs.show', $second).'#chats', false)
        ->assertSee('2 unread messages');
});

it('shows the unread total as a badge on the Messages tab and links the tab to the inbox (spec 021, AC12)', function (): void {
    accountChat(ServiceJob::factory()->open()->create(['customer_id' => $this->customer->id]), 3);

    $this->get(route('account.home'))->assertSee('Unread:', false)->assertSee(route('messages'), false);
});

it('leaves out other customers\' chats and shows an empty state (spec 021, AC12, AC31)', function (): void {
    $other = User::factory()->customer()->create();
    $theirs = ServiceJob::factory()->open()->create(['customer_id' => $other->id]);
    accountChat($theirs, 2);

    $this->get(route('messages'))->assertOk()->assertDontSee(route('jobs.show', $theirs), false)->assertSee('No conversations yet');
});

it('keeps the inbox inside the customer shell with the Messages tab marked (spec 021, AC1, AC12)', function (): void {
    $html = $this->get(route('messages'))->getContent();

    expect($html)->toContain('aria-label="Main"')->toMatch('/aria-current="page"[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<span>Messages/s');
});

// --- Account list and profile (AC13, AC14) -----------------------------------------------

it('shows the Account sections and keeps pros-only accounts and guests out (spec 021, AC13)', function (): void {
    $this->get(route('account.settings'))->assertOk()
        ->assertSee('Profile')->assertSee('Properties')->assertSee('Notifications')->assertSee('Privacy and data')->assertSee('Help')
        ->assertSee(route('contact'), false);

    $proOnly = User::factory()->pro()->create();
    $this->actingAs($proOnly)->get(route('account.settings'))->assertRedirect(route('pros.welcome'));

    auth()->logout();
    $this->get(route('account.profile'))->assertRedirect(route('login'));
});

it('edits the name and rejects empty or oversized values (spec 021, AC14)', function (): void {
    Livewire::test(Profile::class)->set('firstName', 'Thandi')->set('lastName', 'Mokoena')->call('saveName')->assertHasNoErrors()->assertSee('Saved.');
    expect($this->customer->refresh()->fullName())->toBe('Thandi Mokoena');

    Livewire::test(Profile::class)->set('firstName', '')->call('saveName')->assertHasErrors('firstName');
    Livewire::test(Profile::class)->set('lastName', str_repeat('x', 101))->call('saveName')->assertHasErrors('lastName');
    expect($this->customer->refresh()->first_name)->toBe('Thandi');
});

it('keeps the old email until the new address\'s link is opened (spec 021, AC14)', function (): void {
    Mail::fake();

    Livewire::test(Profile::class)->set('newEmail', 'New@Example.com')->call('changeEmail')->assertHasNoErrors()->assertSee('Check your new inbox');

    $this->customer->refresh();
    expect($this->customer->email)->toBe('nomvula@example.com')->and($this->customer->pending_email)->toBe('new@example.com');
    Mail::assertSent(VerifyEmailAddress::class, fn (VerifyEmailAddress $mail): bool => $mail->hasTo('new@example.com') && ! $mail->hasTo('nomvula@example.com'));

    $link = RequestEmailChange::link($this->customer, 'new@example.com');
    $this->get($link)->assertRedirect(route('account.profile'));

    $this->customer->refresh();
    expect($this->customer->email)->toBe('new@example.com')->and($this->customer->pending_email)->toBeNull()->and($this->customer->email_verified_at)->not->toBeNull();
});

it('refuses an email change link that is unsigned, for another address or for another account (spec 021, AC14)', function (): void {
    Mail::fake();
    Livewire::test(Profile::class)->set('newEmail', 'new@example.com')->call('changeEmail');

    $this->get(route('account.email.confirm', ['user' => $this->customer->public_id, 'hash' => 'x']))->assertForbidden();

    $wrongAddress = RequestEmailChange::link($this->customer, 'someone-else@example.com');
    $this->get($wrongAddress)->assertForbidden();

    $valid = RequestEmailChange::link($this->customer, 'new@example.com');
    $this->actingAs(User::factory()->customer()->create())->get($valid)->assertForbidden();

    expect($this->customer->refresh()->email)->toBe('nomvula@example.com');
});

it('will not take an address another account uses or is waiting for (spec 021, AC14)', function (): void {
    Mail::fake();
    User::factory()->customer()->create(['email' => 'taken@example.com']);
    User::factory()->customer()->create(['email' => 'other@example.com', 'pending_email' => 'waiting@example.com']);

    Livewire::test(Profile::class)->set('newEmail', 'Taken@example.com')->call('changeEmail')->assertHasErrors('newEmail');
    Livewire::test(Profile::class)->set('newEmail', 'waiting@example.com')->call('changeEmail')->assertHasErrors('newEmail');
    Livewire::test(Profile::class)->set('newEmail', 'not an email')->call('changeEmail')->assertHasErrors('newEmail');

    Mail::assertNothingSent();
    expect($this->customer->refresh()->pending_email)->toBeNull();
});

it('lets the customer cancel a waiting email change and rate limits repeated tries (spec 021, AC14)', function (): void {
    Mail::fake();
    $profile = Livewire::test(Profile::class)->set('newEmail', 'new@example.com')->call('changeEmail');
    $profile->call('cancelEmailChange');
    expect($this->customer->refresh()->pending_email)->toBeNull();

    foreach (range(1, 5) as $i) {
        Livewire::test(Profile::class)->set('newEmail', "try{$i}@example.com")->call('changeEmail');
    }
    Livewire::test(Profile::class)->set('newEmail', 'one-too-many@example.com')->call('changeEmail')->assertHasErrors('newEmail');
});

// --- Notifications (AC15) ----------------------------------------------------------------

it('defaults every message on and saves choices (spec 021, AC15)', function (): void {
    Livewire::test(Notifications::class)->assertSet('groups.quotes', true)->assertSet('groups.messages', true)->assertSet('channel', 'whatsapp')
        ->set('groups.quotes', false)->set('channel', 'sms')->call('save')->assertSee('Saved.');

    $this->customer->refresh();
    expect($this->customer->notification_preferences)->toEqual(['groups' => ['quotes' => false, 'job_updates' => true, 'messages' => true], 'channel' => 'sms']);
    Livewire::test(Notifications::class)->assertSet('groups.quotes', false)->assertSet('channel', 'sms');
});

it('rejects an unknown channel (spec 021, AC15)', function (): void {
    Livewire::test(Notifications::class)->set('channel', 'carrier-pigeon')->call('save')->assertHasErrors('channel');
});

it('skips the messages a customer switched off and uses their channel for the rest (spec 021, AC15)', function (): void {
    $job = ServiceJob::factory()->open()->create(['customer_id' => $this->customer->id]);
    $quote = Quote::factory()->create(['service_job_id' => $job->id]);
    $this->customer->forceFill(['notification_preferences' => ['groups' => ['quotes' => false, 'job_updates' => true, 'messages' => false], 'channel' => 'sms']])->save();
    $messaging = app(MessagingChannel::class);

    (new SendQuoteMessage($quote->id, 'quote_received'))->handle($messaging);
    $messaging->assertSent('quote_received', times: 0);

    (new SendJobPostedMessage($job->id))->handle($messaging);
    $messaging->assertSent('job_posted', fn ($message): bool => $message->channel === MessageChannel::Sms);

    (new SendJobExpiredMessage($job->id))->handle($messaging);
    $messaging->assertSent('job_expired', fn ($message): bool => $message->channel === MessageChannel::Sms);

    $conversation = accountChat($job);
    (new SendChatNotification($conversation->id, MessageSender::Customer))->handle($messaging);
    $messaging->assertSent('chat_message', times: 0);

    $this->customer->forceFill(['notification_preferences' => null])->save();
    (new SendQuoteMessage($quote->id, 'quote_received'))->handle($messaging);
    $messaging->assertSent('quote_received', fn ($message): bool => $message->channel === MessageChannel::WhatsApp);
});

it('never lets a customer\'s choices stop a pro hearing they were chosen (spec 021, AC15)', function (): void {
    $job = ServiceJob::factory()->open()->create(['customer_id' => $this->customer->id]);
    $quote = Quote::factory()->create(['service_job_id' => $job->id]);
    $this->customer->forceFill(['notification_preferences' => ['groups' => ['quotes' => false, 'job_updates' => false, 'messages' => false], 'channel' => 'sms']])->save();
    $messaging = app(MessagingChannel::class);

    (new SendQuoteMessage($quote->id, 'quote_accepted'))->handle($messaging);

    $messaging->assertSent('quote_accepted', fn ($message): bool => $message->phoneE164 === $quote->pro->user->phone_e164 && $message->channel === MessageChannel::WhatsApp);
});

// --- Privacy and data (AC16) -------------------------------------------------------------

it('records a download or deletion request once and shows it as requested (spec 021, AC16)', function (): void {
    $page = Livewire::test(Privacy::class)->call('request', 'download')->call('request', 'download')->call('request', 'deletion');

    expect(DataRequest::query()->where('user_id', $this->customer->id)->count())->toBe(2);
    $page->assertSee('Requested. We will be in touch on your email.')->assertSee('Requested. We will be in touch before anything is deleted.');
    expect(Activity::query()->where('description', 'data request opened')->count())->toBe(2);
});

it('does not delete or export anything by itself (spec 021, AC16)', function (): void {
    Livewire::test(Privacy::class)->call('request', 'deletion');

    expect($this->customer->refresh()->trashed())->toBeFalse()->and($this->customer->email)->toBe('nomvula@example.com');
});

it('rejects an unknown request type and rate limits repeats (spec 021, AC16)', function (): void {
    Livewire::test(Privacy::class)->call('request', 'export-everything')->assertNotFound();

    foreach (range(1, 5) as $i) {
        Livewire::test(Privacy::class)->call('request', 'download');
    }
    Livewire::test(Privacy::class)->call('request', 'download')->assertStatus(429);
});

it('lets support and super admins mark a request done, and nobody else (spec 021, AC16)', function (): void {
    $request = new DataRequest;
    $request->forceFill(['user_id' => $this->customer->id, 'type' => DataRequestType::Download, 'status' => DataRequestStatus::Open])->save();
    Filament::setCurrentPanel('admin');

    foreach ([Role::AdminVetting, Role::AdminFinance] as $role) {
        $other = User::factory()->create();
        $other->assignRole($role->value);
        $this->actingAs($other);
        Livewire::test(ListDataRequests::class)->assertForbidden();
        expect(fn () => app(CompleteDataRequest::class)->handle($other, $request))->toThrow(HttpException::class);
    }
    $this->actingAs($this->customer)->get('/admin/data-requests')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);

    Livewire::test(ListDataRequests::class)->assertCanSeeTableRecords([$request])->assertSee($this->customer->email)
        ->callAction(TestAction::make('complete')->table($request));

    $request->refresh();
    expect($request->status)->toBe(DataRequestStatus::Done)->and($request->handled_by)->toBe($admin->id)->and($request->handled_at)->not->toBeNull();
    expect(Activity::query()->where('description', 'data request completed')->count())->toBe(1);
});

it('offers waitlist removal on the privacy page only when there are requests, and removes them (spec 021, AC16)', function (): void {
    Livewire::test(Privacy::class)->assertDontSee('Remove my waitlist requests');

    WaitlistEntry::factory()->create(['phone_e164' => $this->customer->phone_e164]);
    WaitlistEntry::factory()->create();

    Livewire::test(Privacy::class)->assertSee('Remove my waitlist requests')->call('removeWaitlistRequests')->assertSee('Your waitlist requests were removed.');

    expect(WaitlistEntry::query()->where('phone_e164', $this->customer->phone_e164)->count())->toBe(0)->and(WaitlistEntry::query()->count())->toBe(1);
});
