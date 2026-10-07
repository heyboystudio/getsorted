<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ConfirmEmailChangeController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\MessagePhotoController;
use App\Http\Controllers\ProChangeFileController;
use App\Http\Controllers\ProDocumentController;
use App\Http\Controllers\ProJobPhotoController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QuoteProPhotoController;
use App\Http\Controllers\ShowTradeController;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Livewire\Account\Home;
use App\Livewire\Account\Inbox;
use App\Livewire\Account\Jobs\Index as JobIndex;
use App\Livewire\Account\Jobs\Show as JobShow;
use App\Livewire\Account\Messages;
use App\Livewire\Account\Properties\Form as PropertyForm;
use App\Livewire\Account\Properties\Index as PropertyIndex;
use App\Livewire\Account\ProProfile as AccountProProfile;
use App\Livewire\Account\Settings\Index as AccountSettings;
use App\Livewire\Account\Settings\Notifications as AccountNotifications;
use App\Livewire\Account\Settings\Privacy as AccountPrivacy;
use App\Livewire\Account\Settings\Profile as AccountProfile;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Auth\VerifyPhone;
use App\Livewire\Booking\Thread as BookingThread;
use App\Livewire\Pros\Application as ProApplication;
use App\Livewire\Pros\Jobs\Index as ProJobs;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Livewire\Pros\Profile as ProProfilePage;
use App\Livewire\Pros\ProfilePreview as ProProfilePreview;
use App\Livewire\Pros\Status as ProStatusPage;
use App\Livewire\Pros\Welcome as ProWelcome;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

if (config('sortd.admin_domain') !== null) {
    // The admin panel moved to its own host; old /admin links land there.
    Route::get('/admin/{any?}', fn () => redirect()->away('https://'.config('sortd.admin_domain')))->where('any', '.*');
}

Route::get('/', Welcome::class)->name('home');
Route::view('/customers', 'pages.customers')->name('customers');
Route::view('/trades', 'pages.trades.index')->name('trades.index');
Route::view('/about', 'pages.about')->name('about');
Route::view('/contact', 'pages.contact')->name('contact');
Route::get('/trades/{trade}', ShowTradeController::class)->name('trades.show');
// Booking is one Siya thread (spec 017). Guests describe the problem, then sign in before Where & when.
Route::get('/book', BookingThread::class)->name('book');
Route::get('/book/{trade}', BookingThread::class)->name('book.trade');
Route::redirect('/help', '/book')->name('assistant');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/pros/join', 'pages.pros.join')->name('pros.join');
Route::view('/pros/agreement', 'pages.pros.agreement')->name('pros.agreement');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/pros/register', Register::class)->defaults('as', 'pro')->name('pros.register');
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->name('password.reset');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/app/jobs/{job}/photos/{photo}', JobPhotoController::class)->name('job-photos.show');
    Route::get('/app/messages/{message}/photos/{photo}', MessagePhotoController::class)->name('message-photos.show');
    Route::get('/pros/documents/{document}', ProDocumentController::class)->name('pro-documents.show');
    Route::get('/pros/changes/{change}/file', ProChangeFileController::class)->name('pro-changes.file');
    Route::get('/pros/jobs/{invite}/photos/{photo}', ProJobPhotoController::class)->name('pros.jobs.photo');
    Route::get('/app/quotes/{quote}/pro-photo', QuoteProPhotoController::class)->name('quotes.pro-photo');
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('push.subscribe');
    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])->middleware('throttle:20,1')->name('push.unsubscribe');
    Route::get('/verify-email', VerifyEmail::class)->name('verification.email');
    Route::get('/verify-email/{user}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.email.verify');
    Route::get('/verify-mobile', VerifyPhone::class)->name('verification.phone');
    Route::get('/app/account/email/{user}/{hash}', ConfirmEmailChangeController::class)->middleware(['signed', 'throttle:6,1'])->name('account.email.confirm');

    Route::middleware(EnsurePhoneIsVerified::class)->group(function (): void {
        Route::middleware(EnsureCustomer::class)->group(function (): void {
            Route::get('/app', Home::class)->name('account.home');
            Route::redirect('/app/book', '/book')->name('account.book');
            Route::get('/app/properties', PropertyIndex::class)->name('properties.index');
            Route::get('/app/properties/new', PropertyForm::class)->name('properties.create');
            Route::get('/app/properties/{property}/edit', PropertyForm::class)->name('properties.edit');
            Route::get('/app/account', AccountSettings::class)->name('account.settings');
            Route::get('/app/account/profile', AccountProfile::class)->name('account.profile');
            Route::get('/app/account/notifications', AccountNotifications::class)->name('account.notifications');
            Route::get('/app/account/privacy', AccountPrivacy::class)->name('account.privacy');
            Route::get('/app/quotes/{quote}/pro', AccountProProfile::class)->name('account.pro-profile');
            Route::get('/app/jobs', JobIndex::class)->name('jobs.index');
            Route::get('/app/jobs/{job}', JobShow::class)->name('jobs.show');
            Route::get('/app/jobs/{job}/continue', BookingThread::class)->name('booking.continue');
        });
        Route::get('/notifications', Inbox::class)->name('notifications');
        Route::get('/messages', Messages::class)->name('messages');
        Route::get('/pros/welcome', ProWelcome::class)->name('pros.welcome');
        Route::get('/pros/apply', ProApplication::class)->name('pros.apply');
        Route::get('/pros/status', ProStatusPage::class)->name('pros.status');
        Route::get('/pros/profile', ProProfilePage::class)->name('pros.profile');
        Route::get('/pros/profile/preview', ProProfilePreview::class)->name('pros.profile.preview');
        Route::get('/pros/jobs', ProJobs::class)->name('pros.jobs');
        Route::get('/pros/jobs/{invite}', ProJob::class)->name('pros.jobs.show');
    });
});
