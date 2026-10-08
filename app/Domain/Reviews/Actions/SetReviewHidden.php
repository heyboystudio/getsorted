<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Actions;

use App\Domain\Accounts\Enums\Role;
use App\Domain\Reviews\Exceptions\CannotReview;
use App\Models\Review;
use App\Models\User;

/** An admin takes a review off the site, or puts it back (spec 025, AC9). The review is kept, with who did it and why. */
final class SetReviewHidden
{
    public function handle(User $admin, Review $review, bool $hidden, ?string $reason = null): Review
    {
        if (! $admin->hasAnyRole([Role::AdminSupport->value, Role::AdminSuper->value])) {
            throw new CannotReview(__('Only admins can hide reviews.'));
        }

        if ($hidden && ($reason === null || mb_strlen(trim($reason)) < 3)) {
            throw new CannotReview(__('Give a reason for hiding the review.'));
        }

        $review->forceFill($hidden
            ? ['hidden_at' => now(), 'hidden_by' => $admin->id, 'hide_reason' => mb_substr(trim((string) $reason), 0, 300)]
            : ['hidden_at' => null, 'hidden_by' => null, 'hide_reason' => null])->save();

        activity()->causedBy($admin)->performedOn($review)->withProperties(['hidden' => $hidden, 'reason' => $reason])->log($hidden ? 'review_hidden' : 'review_unhidden');

        return $review;
    }
}
