<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\Quotes\Support\ContactMasker;
use App\Domain\ServiceJobs\Enums\MessageKind;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Jobs\SendChatNotification;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\Pro;
use App\Models\ServiceJob;
use App\Models\User;
use App\Support\Images\HeicUnsupported;
use App\Support\Images\ImageReencoder;
use App\Support\Images\UnreadableImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Sends one chat message between a job's customer and a pro (spec 018, AC1–AC4).
 * The first message from either side opens the conversation. Until this pro's
 * quote is accepted, contact and bank details are masked before anything is stored.
 */
final readonly class SendJobMessage
{
    public function __construct(private ImageReencoder $images) {}

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function handle(User $sender, ServiceJob $job, Pro $pro, string $body, array $photos = []): JobMessage
    {
        $side = JobChat::sideOf($sender, $job, $pro);
        abort_unless($side instanceof MessageSender, 404);

        $body = trim($body);
        $this->validate($body, $photos);
        $this->throttle($sender, $job, $pro, count($photos));

        // Re-encode outside the transaction: it drops location and other metadata (spec 012).
        $processed = array_map(fn (UploadedFile $photo): string => $this->reencode($photo), $photos);

        $message = DB::transaction(function () use ($sender, $job, $pro, $side, $body, $processed): JobMessage {
            $lockedJob = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            $conversation = JobConversation::query()->where('service_job_id', $lockedJob->id)->where('pro_id', $pro->id)->lockForUpdate()->first();

            if (! JobChat::canWrite($lockedJob, $pro, $conversation)) {
                throw ValidationException::withMessages(['message' => __('This chat is closed.')]);
            }

            if (! $conversation instanceof JobConversation) {
                $conversation = new JobConversation;
                $conversation->forceFill(['service_job_id' => $lockedJob->id, 'pro_id' => $pro->id])->save();
            }

            [$text, $masked] = $body === '' ? [null, false] : ($this->contactShared($lockedJob, $pro) ? [$body, false] : ContactMasker::mask($body));

            if ($masked && $side === MessageSender::Pro) {
                Pro::query()->whereKey($pro->id)->increment('contact_masking_count');
            }

            $message = new JobMessage;
            $message->forceFill([
                'job_conversation_id' => $conversation->id,
                'sender_type' => $side,
                'sender_id' => $sender->id,
                'body' => $text,
                'kind' => $processed === [] ? MessageKind::Text : MessageKind::Photos,
            ])->save();

            foreach ($processed as $image) {
                $message->addMediaFromString($image)
                    ->usingFileName('photo-'.(string) str()->uuid().'.webp')
                    ->usingName(__('Chat photo'))
                    ->toMediaCollection(JobMessage::PHOTO_COLLECTION, 'media');
            }

            $conversation->forceFill([
                'last_message_at' => now(),
                $side === MessageSender::Customer ? 'customer_read_at' : 'pro_read_at' => now(),
            ])->save();

            return $message;
        });

        SendChatNotification::dispatch($message->job_conversation_id, $side === MessageSender::Customer ? MessageSender::Pro : MessageSender::Customer);

        return $message;
    }

    private function contactShared(ServiceJob $job, Pro $pro): bool
    {
        return JobChat::contactShared($job, $pro);
    }

    /** @param list<UploadedFile> $photos */
    private function validate(string $body, array $photos): void
    {
        if ($body === '' && $photos === []) {
            throw ValidationException::withMessages(['message' => __('Type a message or add a photo.')]);
        }

        if (mb_strlen($body) > (int) config('sortd.chat.max_length')) {
            throw ValidationException::withMessages(['message' => __('Messages can be up to :max characters.', ['max' => config('sortd.chat.max_length')])]);
        }

        if (count($photos) > (int) config('sortd.chat.photos_per_message')) {
            throw ValidationException::withMessages(['photos' => __('Send up to :count photos at a time.', ['count' => config('sortd.chat.photos_per_message')])]);
        }

        foreach ($photos as $photo) {
            if ($photo->getSize() === false || $photo->getSize() > (int) config('sortd.job_photos.max_kilobytes') * 1024) {
                throw ValidationException::withMessages(['photos' => __('Each photo must be 10 MB or smaller.')]);
            }
        }
    }

    private function throttle(User $sender, ServiceJob $job, Pro $pro, int $photoCount): void
    {
        $messages = 'chat:messages:'.$sender->id.':'.$job->id.':'.$pro->id;
        $dailyPhotos = 'chat:photos:'.$sender->id;

        if (RateLimiter::tooManyAttempts($messages, (int) config('sortd.chat.messages_per_hour'))) {
            throw ValidationException::withMessages(['message' => __('You’re sending messages too quickly. Try again in a little while.')]);
        }

        if ($photoCount > 0 && RateLimiter::attempts($dailyPhotos) + $photoCount > (int) config('sortd.chat.photos_per_day')) {
            throw ValidationException::withMessages(['photos' => __('You’ve sent the most photos allowed today.')]);
        }

        RateLimiter::hit($messages, 3600);

        for ($i = 0; $i < $photoCount; $i++) {
            RateLimiter::hit($dailyPhotos, 86_400);
        }
    }

    private function reencode(UploadedFile $photo): string
    {
        $path = $photo->getRealPath();

        try {
            return $path === false ? throw new UnreadableImage : $this->images->toWebp($path);
        } catch (HeicUnsupported) {
            throw ValidationException::withMessages(['photos' => __('HEIC photos cannot be processed on this server yet. Please choose a JPEG, PNG or WebP photo.')]);
        } catch (UnreadableImage) {
            throw ValidationException::withMessages(['photos' => __('Choose a valid JPEG, PNG, WebP or HEIC photo.')]);
        }
    }
}
