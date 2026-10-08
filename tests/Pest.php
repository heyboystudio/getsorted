<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

require_once __DIR__.'/Support/booking.php';

/** How many in-app notices of one kind a person has been sent. */
function noticeCount(User $user, string $kind): int
{
    return $user->notifications()->get()->filter(fn ($notice): bool => ($notice->data['kind'] ?? null) === $kind)->count();
}

/** How many in-app notices of one kind have been sent to anyone. */
function allNoticeCount(string $kind): int
{
    return DatabaseNotification::query()->get()->filter(fn ($notice): bool => ($notice->data['kind'] ?? null) === $kind)->count();
}
