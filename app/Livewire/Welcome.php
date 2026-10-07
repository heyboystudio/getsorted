<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\BookingStart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Home: describe the problem (spec 007) or pick a trade to start a booking (spec 005, AC1). Page design: home v3 (decision 055). */
#[Layout('components.layouts.home', ['brand' => 'GetSorted', 'description' => 'Describe the job, compare quotes from vetted Durban pros, and keep everything in one place.'])]
final class Welcome extends Component
{
    /** Session key the booking wizard reads to prefill its notes step (spec 007, AC4). */
    public const string DESCRIPTION_KEY = 'booking.description';

    private const int MIN_LENGTH = 10;

    private const int MAX_LENGTH = 500;

    public string $description = '';

    /** An unused description expires after 30 minutes; drop it rather than keep it in the session (spec 007). */
    public function mount(): void
    {
        // Browsing the home page cancels any earlier "start a job": a later sign-in goes to the account, not Siya.
        BookingStart::clear();

        $stored = session(self::DESCRIPTION_KEY);

        if (is_array($stored) && ($stored['expires_at'] ?? 0) <= now()->getTimestamp()) {
            session()->forget(self::DESCRIPTION_KEY);
        }
    }

    /** Home page box: keep the description for the Siya booking thread (it reads DESCRIPTION_KEY once); guests sign up first. */
    public function start(): mixed
    {
        $description = trim($this->description);

        if ($description !== '') {
            $this->validate(
                ['description' => ['string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH]],
                ['description.*' => __('Describe the problem in :min to :max characters.', ['min' => self::MIN_LENGTH, 'max' => self::MAX_LENGTH])],
            );

            session()->put(self::DESCRIPTION_KEY, [
                'text' => $description,
                'expires_at' => now()->addMinutes((int) config('getsorted.ai.description_ttl_minutes'))->getTimestamp(),
            ]);
        }

        if (auth()->guest()) {
            // Siya is for signed-in users only (decision 056): sign in (or sign up) first, then land in the booking thread.
            BookingStart::begin();

            return $this->redirect(route('login'), navigate: true);
        }

        return $this->redirect(route('book'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.welcome');
    }
}
