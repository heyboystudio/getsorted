<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\ProDocumentController;
use App\Http\Controllers\ProJobPhotoController;
use App\Http\Controllers\ShowTradeController;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Livewire\Account\Home;
use App\Livewire\Account\Jobs\Show as JobShow;
use App\Livewire\Account\Properties\Form as PropertyForm;
use App\Livewire\Account\Properties\Index as PropertyIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Booking\Wizard as BookingWizard;
use App\Livewire\Pros\Application as ProApplication;
use App\Livewire\Pros\BecomePro;
use App\Livewire\Pros\Jobs\Index as ProJobs;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Livewire\Pros\Status as ProStatusPage;
use App\Livewire\Pros\Welcome as ProWelcome;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

Route::get('/', Welcome::class)->name('home');
Route::get('/trades/{trade}', ShowTradeController::class)->name('trades.show');
// Guests may answer the questions; they log in at the property step (spec 005, AC2).
Route::get('/book/{trade}/{service:key}', BookingWizard::class)->scopeBindings()->name('booking.start');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/pros/join', 'pages.pros.join')->name('pros.join');
Route::view('/pros/agreement', 'pages.pros.agreement')->name('pros.agreement');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/app/jobs/{job}/photos/{photo}', JobPhotoController::class)->name('job-photos.show');
    Route::get('/pros/documents/{document}', ProDocumentController::class)->name('pro-documents.show');
    Route::get('/pros/jobs/{invite}/photos/{photo}', ProJobPhotoController::class)->name('pros.jobs.photo');
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::middleware(EnsurePhoneIsVerified::class)->group(function (): void {
        Route::middleware(EnsureCustomer::class)->group(function (): void {
            Route::get('/app', Home::class)->name('account.home');
            Route::get('/app/properties', PropertyIndex::class)->name('properties.index');
            Route::get('/app/properties/new', PropertyForm::class)->name('properties.create');
            Route::get('/app/properties/{property}/edit', PropertyForm::class)->name('properties.edit');
            Route::get('/app/jobs/{job}', JobShow::class)->name('jobs.show');
            Route::get('/app/jobs/{job}/continue', BookingWizard::class)->name('booking.continue');
        });
        Route::get('/pros/welcome', ProWelcome::class)->name('pros.welcome');
        Route::get('/pros/become', BecomePro::class)->name('pros.become');
        Route::get('/pros/apply', ProApplication::class)->name('pros.apply');
        Route::get('/pros/status', ProStatusPage::class)->name('pros.status');
        Route::get('/pros/jobs', ProJobs::class)->name('pros.jobs');
        Route::get('/pros/jobs/{invite}', ProJob::class)->name('pros.jobs.show');
    });
});
