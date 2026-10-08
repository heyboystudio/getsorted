<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\ServiceJobs\Actions\SendJobMessage;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Filament\Admin\Resources\ServiceJobs\RelationManagers\ConversationsRelationManager;
use App\Jobs\SendChatNotification;
use App\Livewire\Account\Jobs\Show as CustomerJob;
use App\Livewire\Jobs\Chat;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Spec 018 part 1: chat between a job's customer and each invited pro.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    Storage::fake('media');
    $this->customer = User::factory()->customer()->create(['first_name' => 'Thandi']);
    $property = Property::factory()->for($this->customer)->create();
    $this->job = ServiceJob::factory()->open()->forProperty($property)->create(['trade_id' => Trade::query()->where('key', 'plumbing')->value('id')]);
    $this->proA = chatPro('Dlamini Plumbing');
    $this->proB = chatPro('Naidoo Plumbing');
});

function chatPro(string $name): Pro
{
    $pro = Pro::factory()->approved()->create(['business_name' => $name]);
    ServiceJobInvite::factory()->create(['service_job_id' => test()->job->id, 'pro_id' => $pro->id, 'status' => 'viewed', 'invited_at' => now()->subMinutes(10 - Pro::query()->count())]);

    return $pro;
}

function chatAs(User $user, Pro $pro, string $title = 'Pro A'): Testable
{
    test()->actingAs($user);

    return Livewire::test(Chat::class, ['jobPublicId' => test()->job->public_id, 'proPublicId' => $pro->public_id, 'title' => $title]);
}

it('lets the customer message an invited pro first, who stays anonymous until they reply (AC1; spec 009 AC13)', function (): void {
    $this->actingAs($this->customer);
    Livewire::test(CustomerJob::class, ['job' => $this->job])
        ->assertSee('Chat with your pros')->assertSee('Pro A')->assertSee('Pro B')->assertDontSee('Dlamini Plumbing');

    chatAs($this->customer, $this->proA)->set('message', 'Is the leak under the sink something you can do today?')->call('send')->assertHasNoErrors()
        ->assertSee('Is the leak under the sink something you can do today?');

    expect(JobConversation::query()->sole()->pro_id)->toBe($this->proA->id)
        ->and(JobMessage::query()->sole()->sender_type)->toBe(MessageSender::Customer);

    chatAs($this->proA->user, $this->proA, 'Thandi')->assertSee('Is the leak under the sink')
        ->set('message', 'Yes, I can come at 3pm.')->call('send')->assertHasNoErrors();

    $this->actingAs($this->customer);
    Livewire::test(CustomerJob::class, ['job' => $this->job])->assertSee('Dlamini Plumbing')->assertSee('Pro B');
});

it('lets a pro who opened the invite start the chat, shown on their job page (AC1)', function (): void {
    $this->actingAs($this->proB->user);
    Livewire::test(ProJob::class, ['invite' => ServiceJobInvite::query()->where('pro_id', $this->proB->id)->sole()])
        ->assertSeeLivewire(Chat::class)->assertSee('Chat with the customer')->assertDontSee('Thandi');

    chatAs($this->proB->user, $this->proB, 'Thandi')->set('message', 'Which tap is it?')->call('send')->assertHasNoErrors();
    expect(JobMessage::query()->sole()->sender_type)->toBe(MessageSender::Pro);
});

it('masks contact and bank details before a quote is accepted and flags pros who try (AC3)', function (): void {
    chatAs($this->proA->user, $this->proA, 'Thandi')
        ->set('message', 'WhatsApp me on 082 123 4567 or pay FNB acc 62812345678')->call('send')
        ->assertDontSee('082 123 4567')->assertSee('Keep chats on GetSorted');

    $stored = JobMessage::query()->sole()->body;
    expect($stored)->not->toContain('082 123 4567')->not->toContain('62812345678')
        ->and($this->proA->fresh()->contact_masking_count)->toBe(1);
});

it('keeps the chosen pro’s chat open and unmasked after acceptance, and closes the others read-only (AC5)', function (): void {
    chatAs($this->customer, $this->proA)->set('message', 'Hi')->call('send');
    chatAs($this->customer, $this->proB)->set('message', 'Hi')->call('send');
    $quote = Quote::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->proA->id]);
    app(AcceptQuote::class)->handle($this->customer, $quote);

    chatAs($this->proA->user, $this->proA, 'Thandi')->assertDontSee('Contact details are shared once')
        ->set('message', 'Call me on 082 123 4567')->call('send')->assertHasNoErrors()->assertSee('082 123 4567');

    chatAs($this->customer, $this->proB)->assertSee('You chose another pro, so this chat is closed.')->assertDontSee('Type a message');
    expect(fn () => app(SendJobMessage::class)->handle($this->customer, $this->job->fresh(), $this->proB, 'Still there?'))
        ->toThrow(ValidationException::class);
});

it('keeps strangers, declined pros and other customers out (security)', function (): void {
    $stranger = User::factory()->customer()->create();
    $this->actingAs($stranger);
    Livewire::test(Chat::class, ['jobPublicId' => $this->job->public_id, 'proPublicId' => $this->proA->public_id, 'title' => 'x'])->assertNotFound();

    $otherPro = Pro::factory()->approved()->create();
    $this->actingAs($otherPro->user);
    Livewire::test(Chat::class, ['jobPublicId' => $this->job->public_id, 'proPublicId' => $this->proA->public_id, 'title' => 'x'])->assertNotFound();

    ServiceJobInvite::query()->where('pro_id', $this->proB->id)->update(['status' => 'declined']);
    expect(fn () => app(SendJobMessage::class)->handle($this->customer, $this->job, $this->proB, 'Hello'))
        ->toThrow(ValidationException::class);
    $this->actingAs($this->customer);
    Livewire::test(CustomerJob::class, ['job' => $this->job])->assertDontSee('Pro B');
});

it('sends photos, re-encoded, that only the two people in the chat (or a signed-in admin) can open (AC2)', function (): void {
    chatAs($this->customer, $this->proA)
        ->set('photos', [UploadedFile::fake()->image('leak.jpg', 40, 30), UploadedFile::fake()->image('pipe.png', 20, 20)])
        ->call('send')->assertHasNoErrors();

    $message = JobMessage::query()->sole();
    $photo = $message->getMedia(JobMessage::PHOTO_COLLECTION)->first();
    expect($message->getMedia(JobMessage::PHOTO_COLLECTION))->toHaveCount(2)->and($photo->mime_type)->toBe('image/webp');

    $url = $message->photoUrl($photo);
    $this->actingAs($this->proA->user)->get($url)->assertOk();
    $this->actingAs($this->customer)->get($url)->assertOk();
    $this->actingAs($this->proB->user)->get($url)->assertNotFound();
    $this->actingAs($this->customer)->get(route('message-photos.show', [$message, $photo->uuid]))->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin)->get($url)->assertForbidden();
    $this->actingAs($admin)->withSession([AdminLogin::SESSION_KEY => $admin->id])->get($url)->assertOk();
});

it('refuses empty, too long and too many-photo messages', function (): void {
    $chat = chatAs($this->customer, $this->proA);
    $chat->set('message', '   ')->call('send')->assertHasErrors('message');
    $chat->set('message', str_repeat('a', 1001))->call('send')->assertHasErrors('message');
    $chat->set('message', 'ok')->set('photos', array_map(fn (int $i) => UploadedFile::fake()->image("p{$i}.jpg", 10, 10), range(1, 6)))
        ->call('send')->assertHasErrors('photos');

    expect(JobMessage::query()->count())->toBe(0);
});

it('notifies the other side without the message text, at most once per window, and not while they are on the page (AC4)', function (): void {
    chatAs($this->customer, $this->proA)->set('message', 'My private message text')->call('send');

    expect(noticeCount($this->proA->user, 'chat_message'))->toBe(1)
        ->and(json_encode($this->proA->user->notifications()->get()->pluck('data')))->not->toContain('private message text');

    chatAs($this->customer, $this->proA)->set('message', 'Another one')->call('send');
    expect(noticeCount($this->proA->user, 'chat_message'))->toBe(1);

    // The customer has the chat open (just polled), so a reply sends them nothing.
    chatAs($this->proA->user, $this->proA, 'Thandi')->set('message', 'Reply')->call('send');
    expect(noticeCount($this->customer, 'chat_message'))->toBe(0);

    $this->travel(16)->minutes();
    chatAs($this->proA->user, $this->proA, 'Thandi')->set('message', 'Later reply')->call('send');
    expect(noticeCount($this->customer, 'chat_message'))->toBe(1);
});

it('queues notifications on the notifications queue after the message is saved (AC4)', function (): void {
    Queue::fake();
    chatAs($this->customer, $this->proA)->set('message', 'Hi')->call('send');

    Queue::assertPushedOn('notifications', SendChatNotification::class, fn (SendChatNotification $job): bool => $job->recipient === MessageSender::Pro);
});

it('never lets anyone delete a message, but the other side can report it (AC6)', function (): void {
    chatAs($this->customer, $this->proA)->set('message', 'Oops wrong job')->call('send');
    $message = JobMessage::query()->sole();

    chatAs($this->customer, $this->proA)->assertDontSee('Delete');
    expect(fn () => chatAs($this->customer, $this->proA)->call('deleteMessage', $message->public_id))->toThrow(Exception::class);
    expect($message->fresh()->body)->toBe('Oops wrong job')->and($message->fresh()->deleted_at)->toBeNull();

    chatAs($this->proA->user, $this->proA, 'Thandi')->set('message', 'Pay me cash, cheaper')->call('send');
    $proMessage = JobMessage::query()->where('sender_type', 'pro')->sole();
    chatAs($this->proA->user, $this->proA, 'Thandi')->call('startReport', $proMessage->public_id)->call('report', 'off_platform')->assertNotFound();
    chatAs($this->customer, $this->proA)->call('startReport', $proMessage->public_id)->call('report', 'off_platform')->assertSee('Reported');
    expect($proMessage->fresh()->reported_at)->not->toBeNull()->and($proMessage->fresh()->report_reason->value)->toBe('off_platform');
});

it('limits messages per hour per sender', function (): void {
    config()->set('getsorted.chat.messages_per_hour', 2);
    $chat = chatAs($this->customer, $this->proA);
    $chat->set('message', 'one')->call('send')->set('message', 'two')->call('send')->set('message', 'three')->call('send')->assertHasErrors('message');

    expect(JobMessage::query()->count())->toBe(2);
});

it('shows estimate cards in the chat and calls quotes estimates (AC7)', function (): void {
    Quote::factory()->create(['service_job_id' => $this->job->id, 'pro_id' => $this->proA->id, 'total_cents' => 57050]);
    chatAs($this->customer, $this->proA)->set('message', 'Thanks')->call('send')->assertSee('Estimate · R 570.50');

    $this->actingAs($this->customer);
    Livewire::test(CustomerJob::class, ['job' => $this->job])->assertSee('Estimates (1 of 5)')
        ->assertDontSee('adjust the final amount');
});

it('shows admins the chats read-only, logs each view, and lets support close one (AC15)', function (): void {
    chatAs($this->customer, $this->proA)->set('message', 'Private question')->call('send');
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ConversationsRelationManager::class, ['ownerRecord' => $this->job, 'pageClass' => ViewServiceJob::class])
        ->assertSee('Dlamini Plumbing')
        ->mountTableAction('read', JobConversation::query()->sole())->assertMountedActionModalSee('Private question');

    Livewire::test(ConversationsRelationManager::class, ['ownerRecord' => $this->job, 'pageClass' => ViewServiceJob::class])
        ->callTableAction('close', JobConversation::query()->sole(), ['reason' => 'Abuse'])->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('activity_log', ['description' => 'chat_viewed_by_admin', 'causer_id' => $admin->id]);
    expect(JobConversation::query()->sole()->status)->toBe('closed');
    chatAs($this->customer, $this->proA)->assertSee('This chat is closed.');
});

it('prunes chats two years after the job ends, photos included (decision 6)', function (): void {
    chatAs($this->customer, $this->proA)->set('message', 'Old')->set('photos', [UploadedFile::fake()->image('a.jpg', 10, 10)])->call('send');
    $path = JobMessage::query()->sole()->getFirstMedia(JobMessage::PHOTO_COLLECTION)->getPathRelativeToRoot();
    $this->job->forceFill(['status' => 'cancelled'])->save();
    ServiceJob::query()->whereKey($this->job->id)->update(['updated_at' => now()->subMonths(25)]);

    $this->artisan('model:prune', ['--model' => [JobMessage::class]])->assertSuccessful();

    expect(JobMessage::query()->count())->toBe(0);
    Storage::disk('media')->assertMissing($path);
});
