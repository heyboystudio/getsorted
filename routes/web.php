<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Middleware\EnsurePhoneIsVerified;
use App\Livewire\Account\Home;
use App\Livewire\Auth\Login;
use App\Livewire\Welcome;
use Illuminate\Support\Facades\Route;

Route::get('/', Welcome::class)->name('home');

Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::middleware(EnsurePhoneIsVerified::class)->group(function (): void {
        Route::get('/app', Home::class)->name('account.home');
    });
});
