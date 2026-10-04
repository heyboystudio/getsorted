<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Livewire\Account\Home;
use App\Livewire\Account\Properties\Form as PropertyForm;
use App\Livewire\Account\Properties\Index as PropertyIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Pros\BecomePro;
use App\Livewire\Pros\Welcome as ProWelcome;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

Route::get('/', Welcome::class)->name('home');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/pros/join', 'pages.pros.join')->name('pros.join');
Route::view('/pros/agreement', 'pages.pros.agreement')->name('pros.agreement');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::middleware(EnsurePhoneIsVerified::class)->group(function (): void {
        Route::middleware(EnsureCustomer::class)->group(function (): void {
            Route::get('/app', Home::class)->name('account.home');
            Route::get('/app/properties', PropertyIndex::class)->name('properties.index');
            Route::get('/app/properties/new', PropertyForm::class)->name('properties.create');
            Route::get('/app/properties/{property}/edit', PropertyForm::class)->name('properties.edit');
        });
        Route::get('/pros/welcome', ProWelcome::class)->name('pros.welcome');
        Route::get('/pros/become', BecomePro::class)->name('pros.become');
    });
});
