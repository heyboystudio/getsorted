<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Actions;

use App\Domain\Notifications\Notify;
use App\Domain\Quotes\Support\ContactMasker;
use App\Domain\Reviews\Exceptions\CannotReview;
use App\Models\Pro;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The reviewed pro replies once (spec 025, AC5–AC6). The reply is masked like the review. */
final class ReplyToReview
{
    public function handle(User $user, Review $review, string $reply): Review
    {
        $reply = trim($reply);

        if (mb_strlen($reply) < 3 || mb_strlen($reply) > 500) {
            throw new CannotReview(__('Write a reply of 3 to 500 characters.'));
        }

        $review = DB::transaction(function () use ($user, $review, $reply): Review {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);

            if (Pro::query()->whereKey($review->pro_id)->value('user_id') !== $user->id) {
                throw new CannotReview(__('You can only reply to your own reviews.'));
            }

            if ($review->reply !== null) {
                throw new CannotReview(__('You already replied to this review.'));
            }

            if ($review->hidden_at !== null) {
                throw new CannotReview(__('This review is no longer shown.'));
            }

            [$masked] = ContactMasker::mask($reply);
            $review->forceFill(['reply' => $masked, 'replied_at' => now()])->save();
            activity()->causedBy($user)->performedOn($review)->log('review_replied');

            return $review;
        });

        $job = $review->serviceJob;
        Notify::user($review->customer, 'review_reply', __('Your pro replied to your review'), __('Open your :trade job to read the reply.', ['trade' => mb_strtolower($job->trade->name)]), route('jobs.show', $job));

        return $review;
    }
}
