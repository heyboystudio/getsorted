<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\ServiceJobs\Actions\CancelServiceJob;
use App\Domain\ServiceJobs\Actions\RemoveJobPhoto;
use App\Domain\ServiceJobs\Actions\StoreJobPhoto;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    proNear(['plumbing'], 2);
    Storage::fake('media');
});

/** The booking thread at its photos card for the signed-in customer (spec 017). */
function photoThread(): Testable
{
    /** @var User $customer */
    $customer = auth()->user();
    $property = Property::factory()->for($customer)->create();
    $trade = tradeOf('plumbing');

    return describeJob(threadFor($trade), $trade)->call('selectProperty', $property->public_id)
        ->set('preferredDate', now()->addDays(2)->toDateString())->call('chooseWhen', 'morning')
        ->assertSet('stage', 'photos');
}

it('stores a processed photo privately and lets its owner remove it', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);

    $thread = photoThread()
        ->set('photoUpload', UploadedFile::fake()->image('leak.jpg', 80, 60))->assertHasNoErrors();

    $job = ServiceJob::query()->sole();
    $photo = $job->getMedia('job_photos')->sole();
    expect($photo->disk)->toBe('media')
        ->and($photo->mime_type)->toBe('image/webp')
        ->and($photo->uuid)->not->toBeNull();
    Storage::disk('media')->assertExists($photo->getPathRelativeToRoot());

    $thread->call('removePhoto', $photo->uuid)->assertHasNoErrors();
    expect($job->fresh()->getMedia('job_photos'))->toHaveCount(0);
});

it('rejects oversized, non-image and sixth uploads without saving them', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    $thread = photoThread();

    $thread->set('photoUpload', UploadedFile::fake()->create('notes.txt', 1, 'text/plain'))->assertHasErrors(['photoUpload']);
    $thread->set('photoUpload', UploadedFile::fake()->createWithContent('looks-like-a-photo.jpg', 'not an image'))->assertHasErrors(['photoUpload']);
    $thread->set('photoUpload', UploadedFile::fake()->image('large.jpg')->size(10_241))->assertHasErrors(['photoUpload']);

    for ($i = 0; $i < 5; $i++) {
        $thread->set('photoUpload', UploadedFile::fake()->image("image{$i}.jpg", 20 + $i, 20))->assertHasNoErrors();
    }

    $thread->set('photoUpload', UploadedFile::fake()->image('sixth.jpg', 30, 20))->assertHasErrors(['photoUpload']);
    expect(ServiceJob::query()->sole()->getMedia('job_photos'))->toHaveCount(5);
});

it('allows only an authorized owner to open a signed photo URL', function (): void {
    $owner = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();
    $this->actingAs($owner);
    photoThread()
        ->set('photoUpload', UploadedFile::fake()->image('leak.jpg', 20, 20));
    $job = ServiceJob::query()->sole();
    $photo = $job->getMedia('job_photos')->sole();
    $url = route('job-photos.show', [$job, $photo->uuid]);

    $this->get($url)->assertForbidden();
    $signed = URL::temporarySignedRoute('job-photos.show', now()->addMinutes(5), ['job' => $job, 'photo' => $photo->uuid]);
    $this->get($signed)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->actingAs($other)->get($signed)->assertNotFound();
    $this->actingAs($owner)->get(URL::temporarySignedRoute('job-photos.show', now()->subMinute(), ['job' => $job, 'photo' => $photo->uuid]))->assertForbidden();
});

it('removes photos with a cancelled draft', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    photoThread()
        ->set('photoUpload', UploadedFile::fake()->image('leak.jpg'));
    $job = ServiceJob::query()->sole();
    $path = $job->getMedia('job_photos')->sole()->getPathRelativeToRoot();

    app(CancelServiceJob::class)->handle($job, ActorType::Customer, $customer->id, 'Removed by customer');

    expect($job->fresh()->getMedia('job_photos'))->toHaveCount(0);
    Storage::disk('media')->assertMissing($path);
});

it('strips source metadata when processing a JPEG', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    $source = UploadedFile::fake()->image('location.jpg', 20, 20);
    $jpeg = (string) file_get_contents($source->getRealPath());
    $metadata = "Exif\0\0GPSLatitude=test-location";
    $withMetadata = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($metadata) + 2).$metadata.substr($jpeg, 2);

    photoThread()
        ->set('photoUpload', UploadedFile::fake()->createWithContent('location.jpg', $withMetadata))->assertHasNoErrors();

    $photo = ServiceJob::query()->sole()->getMedia('job_photos')->sole();
    $stored = Storage::disk('media')->get($photo->getPathRelativeToRoot());
    expect($stored)->not->toContain('GPSLatitude', 'test-location', 'Exif');
});

it('does not attach the same upload twice when a request is repeated', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    photoThread();
    $job = ServiceJob::query()->sole();
    $upload = UploadedFile::fake()->image('same.jpg', 20, 20);

    $first = app(StoreJobPhoto::class)->handle($customer, $job, $upload);
    $second = app(StoreJobPhoto::class)->handle($customer, $job, $upload);

    expect($second->id)->toBe($first->id)
        ->and($job->fresh()->getMedia('job_photos'))->toHaveCount(1);
});

it('refuses to remove a photo after its draft was posted', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    photoThread()
        ->set('photoUpload', UploadedFile::fake()->image('leak.jpg'));
    $staleDraft = ServiceJob::query()->sole();
    $photo = $staleDraft->getMedia('job_photos')->sole();
    $current = $staleDraft->fresh();
    app(ServiceJobStateMachine::class)->transition($current, ServiceJobStatus::Open, 'job_posted', ActorType::Customer, $customer->id);

    try {
        app(RemoveJobPhoto::class)->handle($customer, $staleDraft, $photo->uuid);
        $this->fail('A posted job photo was removed.');
    } catch (NotFoundHttpException) {
        expect($staleDraft->fresh()->getMedia('job_photos'))->toHaveCount(1);
    }
});

it('adds a chosen photo straight away and shows it on the summary (spec 017 AC13)', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);

    $thread = photoThread()->assertSee('Skip for now')
        ->set('photoUpload', UploadedFile::fake()->image('leak.jpg'))->assertHasNoErrors()
        ->assertSet('photoUpload', null)->assertSee('Continue')
        ->call('finishPhotos')->assertSet('stage', 'summary')->assertSee('1 photo added');

    $photo = ServiceJob::query()->sole()->getMedia('job_photos')->sole();
    $thread->assertSee($photo->uuid);
});

it('shows photos to the customer and admin, but requires an admin panel session for the image', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    photoThread()
        ->set('photoUpload', UploadedFile::fake()->image('leak.jpg'));
    $job = ServiceJob::query()->sole();
    $photo = $job->getMedia('job_photos')->sole();
    $this->get(route('jobs.show', $job))->assertSee('Photos')->assertSee($photo->uuid);

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])->assertSee('Photos')->assertSee($photo->uuid);

    $signed = $job->photoUrl($photo);
    $this->get($signed)->assertForbidden();
    $this->withSession([AdminLogin::SESSION_KEY => $admin->id])->get($signed)->assertOk();
});
