<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

$guard = (string) config('fortify.guard');
$authMiddleware = (string) config('fortify.auth_middleware', 'auth').':'.$guard;
$verificationLimiter = (string) config('fortify.limiters.verification', '6,1');

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/terms', 'Legal/Terms')->name('terms');
Route::inertia('/privacy', 'Legal/Privacy')->name('privacy');

Route::middleware('guest:'.$guard)->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware($authMiddleware)
    ->name('logout');

Route::middleware($authMiddleware)->group(function () use ($verificationLimiter): void {
    Route::get('/email/verify', [EmailVerificationPromptController::class, '__invoke'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:'.$verificationLimiter])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:'.$verificationLimiter)
        ->name('verification.send');
});

Route::middleware([$authMiddleware, 'verified'])->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
});
