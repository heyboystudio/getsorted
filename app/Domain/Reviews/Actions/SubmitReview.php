<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Actions;

use App\Domain\Notifications\Notify;
use App\Domain\Quotes\Support\ContactMasker;
use App\Domain\Reviews\Exceptions\CannotReview;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Support\BookedJob;
use App\Models\Pro;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** The client rates the pro once their job is done (spec 025, AC1–AC4). One review per job, and the comment is masked like quotes. */
final class SubmitReview
{
    public const int WINDOW_DAYS = 60;

    public function handle(User $customer, ServiceJob $job, int $rating, ?string $comment): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new CannotReview(__('Choose from 1 to 5 stars.'));
        }

        $comment = $comment === null || trim($comment) === '' ? null : trim($comment);

        if ($comment !== null && mb_strlen($comment) > 1000) {
            throw new CannotReview(__('Keep your comment under 1 000 characters.'));
        }

        $review = null;
        $pro = null;

        try {
            $review = DB::transaction(function () use ($customer, $job, $rating, $comment, &$pro): Review {
                $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

                if ($job->customer_id !== $customer->id) {
                    throw new CannotReview(__('This job is not yours to review.'));
                }

                if ($job->status !== ServiceJobStatus::Completed || $job->completed_at === null) {
                    throw new CannotReview(__('You can review a job once it is done.'));
                }

                if ($job->completed_at->lt(now()->subDays(self::WINDOW_DAYS))) {
                    throw new CannotReview(__('Reviews close :days days after a job is done.', ['days' => self::WINDOW_DAYS]));
                }

                if (Review::query()->where('service_job_id', $job->id)->exists()) {
                    throw new CannotReview(__('You already reviewed this job.'));
                }

                $pro = BookedJob::pro($job);

                if (! $pro instanceof Pro) {
                    throw new CannotReview(__('This job has no pro to review.'));
                }

                [$masked] = $comment === null ? [null] : ContactMasker::mask($comment);

                $review = new Review;
                $review->forceFill([
                    'service_job_id' => $job->id,
                    'pro_id' => $pro->id,
                    'customer_id' => $customer->id,
                    'rating' => $rating,
                    'comment' => $masked,
                ])->save();

                activity()->causedBy($customer)->performedOn($job)->withProperties(['rating' => $rating])->log('review_submitted');

                return $review;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CannotReview(__('You already reviewed this job.'));
        }

        if ($pro instanceof Pro) {
            Notify::user($pro->user, 'review_received', __('You got a :stars-star review', ['stars' => $rating]), __('A client rated your work. Open the job to read it and reply.'), route('pros.jobs'), email: true);
        }

        return $review;
    }
}
