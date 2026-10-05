<?php

declare(strict_types=1);

namespace App\Livewire\Jobs;

use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Actions\ManageJobMessage;
use App\Domain\ServiceJobs\Actions\SendJobMessage;
use App\Domain\ServiceJobs\Enums\MessageReportReason;
use App\Domain\ServiceJobs\Enums\MessageSender;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Models\JobConversation;
use App\Models\JobMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The chat between a job's customer and one pro (spec 018), embedded on both the
 * customer's job page and the pro's invite page. New messages arrive by polling
 * every few seconds (decision 5); everything is checked again on the server.
 */
final class Chat extends Component
{
    use WithFileUploads;

    private const int SHOWN = 100;

    #[Locked]
    public string $jobPublicId;

    #[Locked]
    public string $proPublicId;

    /** What this side calls the other: "Pro A", a business name, or the customer's first name. */
    #[Locked]
    public string $title;

    public string $message = '';

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    #[Locked]
    public ?string $reporting = null;

    public function mount(string $jobPublicId, string $proPublicId, string $title): void
    {
        $this->jobPublicId = $jobPublicId;
        $this->proPublicId = $proPublicId;
        $this->title = $title;
        abort_unless($this->side() instanceof MessageSender, 404);
    }

    public function send(SendJobMessage $sendJobMessage): void
    {
        $this->validate([
            'message' => ['nullable', 'string', 'max:'.config('sortd.chat.max_length')],
            'photos' => ['array', 'max:'.config('sortd.chat.photos_per_message')],
            'photos.*' => ['file', 'max:'.config('sortd.job_photos.max_kilobytes')],
        ]);

        $sendJobMessage->handle($this->user(), $this->job(), $this->pro(), $this->message, $this->photos);

        foreach ($this->photos as $photo) {
            $photo->delete();
        }

        $this->reset(['message', 'photos']);
    }

    public function removePhoto(int $index): void
    {
        if (isset($this->photos[$index])) {
            $this->photos[$index]->delete();
            unset($this->photos[$index]);
            $this->photos = array_values($this->photos);
        }
    }

    public function deleteMessage(string $publicId, ManageJobMessage $manage): void
    {
        $manage->delete($this->user(), $this->messageOnChat($publicId));
    }

    public function startReport(string $publicId): void
    {
        // Not found unless the message is in this chat.
        $this->reporting = $this->messageOnChat($publicId)->public_id;
    }

    public function report(string $reason, ManageJobMessage $manage): void
    {
        $chosen = MessageReportReason::tryFrom($reason);
        abort_unless($chosen instanceof MessageReportReason && $this->reporting !== null, 404);
        $manage->report($this->user(), $this->messageOnChat($this->reporting), $chosen);
        $this->reporting = null;
    }

    public function cancelReport(): void
    {
        $this->reporting = null;
    }

    public function render(ManageJobMessage $manage): View
    {
        $job = $this->job();
        $pro = $this->pro();
        $side = $this->side();
        $conversation = $this->conversation();

        // Looking at the chat counts as reading it, and as "on the page" for notifications (AC4).
        if ($conversation instanceof JobConversation) {
            $manage->markRead($this->user(), $conversation);
        }

        $accepted = $job->accepted_quote_id !== null;
        $writable = JobChat::canWrite($job, $pro, $conversation);

        return view('livewire.jobs.chat', [
            'side' => $side,
            'items' => $this->timeline($conversation, $job, $pro),
            'writable' => $writable,
            'masked' => ! JobChat::contactShared($job, $pro),
            'closedNote' => $writable ? null : ($accepted && ! JobChat::isAcceptedPro($job, $pro)
                ? ($side === MessageSender::Customer ? __('You chose another pro, so this chat is closed.') : __('The customer chose another pro, so this chat is closed.'))
                : __('This chat is closed.')),
            'reasons' => MessageReportReason::cases(),
            'deleteWithin' => now()->subMinutes((int) config('sortd.chat.delete_within_minutes')),
        ]);
    }

    /**
     * Messages and this pro's estimate versions in time order (AC7).
     *
     * @return Collection<int, array{type: string, mine: bool, message?: JobMessage, photos?: array<string, string>, quote?: Quote, at: \DateTimeInterface}>
     */
    private function timeline(?JobConversation $conversation, ServiceJob $job, Pro $pro): Collection
    {
        $side = $this->side();
        $items = collect();

        if ($conversation instanceof JobConversation) {
            $messages = $conversation->messages()->with('media')->latest('id')->limit(self::SHOWN)->get()->reverse();

            foreach ($messages as $message) {
                $items->push([
                    'type' => 'message',
                    'mine' => $message->sender_type === $side,
                    'message' => $message,
                    'photos' => $message->deleted_at === null
                        ? $message->getMedia(JobMessage::PHOTO_COLLECTION)->mapWithKeys(fn ($photo): array => [$photo->uuid => $message->photoUrl($photo)])->all()
                        : [],
                    'at' => $message->created_at,
                ]);
            }
        }

        $quotes = Quote::query()->where('service_job_id', $job->id)->where('pro_id', $pro->id)
            ->whereNotNull('submitted_at')->orderBy('version')->get();

        foreach ($quotes as $quote) {
            $items->push(['type' => 'quote', 'mine' => $side === MessageSender::Pro, 'quote' => $quote, 'at' => $quote->submitted_at]);
        }

        return $items->sortBy(fn (array $item): int => $item['at']->getTimestamp())->values();
    }

    private function messageOnChat(string $publicId): JobMessage
    {
        $conversation = $this->conversation();
        abort_unless($conversation instanceof JobConversation, 404);

        return $conversation->messages()->where('public_id', $publicId)->firstOrFail();
    }

    private function side(): ?MessageSender
    {
        return JobChat::sideOf($this->user(), $this->job(), $this->pro());
    }

    private ?ServiceJob $jobCache = null;

    private function job(): ServiceJob
    {
        return $this->jobCache ??= ServiceJob::query()->where('public_id', $this->jobPublicId)->firstOrFail();
    }

    private ?Pro $proCache = null;

    private function pro(): Pro
    {
        return $this->proCache ??= Pro::query()->where('public_id', $this->proPublicId)->firstOrFail();
    }

    private function conversation(): ?JobConversation
    {
        return JobConversation::query()->where('service_job_id', $this->job()->id)->where('pro_id', $this->pro()->id)->first();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }

    /** For the view: an estimate version's status in words. */
    public static function quoteStatus(Quote $quote): string
    {
        return $quote->status === QuoteStatus::Submitted ? __('Estimate sent') : $quote->status->label();
    }
}
