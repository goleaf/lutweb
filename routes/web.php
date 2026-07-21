<?php

use App\Http\Controllers\Account\CaptureOrderPayPalController;
use App\Http\Controllers\Account\CustomLutController as AccountCustomLutController;
use App\Http\Controllers\Account\CustomLutPurchaseController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DownloadHistoryController;
use App\Http\Controllers\Account\EntitlementDownloadController;
use App\Http\Controllers\Account\LutLibraryController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Checkout\ClaimFreeProductController;
use App\Http\Controllers\Checkout\ShowCheckoutController;
use App\Http\Controllers\Checkout\StorePayPalOrderController;
use App\Http\Controllers\CustomLut\ProjectController as CustomLutProjectController;
use App\Http\Controllers\CustomLut\ProjectMutationController;
use App\Http\Controllers\CustomLut\ProjectPhotoController;
use App\Http\Controllers\CustomLut\ProjectPhotoPreviewController;
use App\Http\Controllers\CustomLut\ProjectStyleController;
use App\Http\Controllers\CustomLut\ProjectVariationController;
use App\Http\Controllers\CustomLutCheckoutController;
use App\Http\Controllers\CustomLutPayPalOrderController;
use App\Http\Controllers\Operations\HealthController;
use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\LutTesterController;
use App\Http\Controllers\Storefront\LutTestImageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ShopController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use Illuminate\Support\Facades\Route;
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

Route::get('/', HomeController::class)->name('home');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap.index');

Route::prefix('health')
    ->name('health.')
    ->middleware('throttle:health')
    ->group(function (): void {
        Route::get('/live', [HealthController::class, 'live'])->name('live');
        Route::get('/ready', [HealthController::class, 'ready'])->name('ready');
    });

Route::prefix('shop')->name('shop.')->group(function () use ($authMiddleware): void {
    Route::get('/', [ShopController::class, 'index'])->name('index');
    Route::middleware([$authMiddleware, 'verified'])->group(function (): void {
        Route::get('/{slug}/try', [LutTesterController::class, 'create'])->name('tester.create');
        Route::post('/{slug}/try', [LutTesterController::class, 'store'])
            ->middleware('throttle:lut-tester-upload')
            ->name('tester.store');
        Route::get('/{slug}/try/{lutTestUpload}', [LutTesterController::class, 'show'])->name('tester.show');
        Route::delete('/{slug}/try/{lutTestUpload}', [LutTesterController::class, 'destroy'])->name('tester.destroy');
    });
    Route::get('/{slug}', [ProductController::class, 'show'])->name('show');
});

Route::get('/lut-tests/{lutTestUpload}/images/{variant}', LutTestImageController::class)
    ->middleware([$authMiddleware, 'verified', 'signed'])
    ->whereIn('variant', ['before', 'after'])
    ->name('lut-tests.images.show');

Route::prefix('luts')->name('categories.')->group(function (): void {
    Route::get('/{categorySlug}', [CategoryController::class, 'show'])->name('show');
});

Route::middleware([$authMiddleware, 'verified', 'not_suspended'])->group(function (): void {
    Route::prefix('custom-lut')->name('custom-lut.')->group(function (): void {
        Route::get('/', [CustomLutProjectController::class, 'create'])->name('create');
        Route::post('/', [CustomLutProjectController::class, 'store'])
            ->middleware('throttle:lut-wizard-create')
            ->name('store');
        Route::get('/{wizardProject}', [CustomLutProjectController::class, 'show'])->name('show');
        Route::get('/{wizardProject}/builds/{customLutBuild}/checkout', [CustomLutCheckoutController::class, 'show'])
            ->middleware('throttle:custom-lut-checkout-page')
            ->name('checkout.show');
        Route::post('/{wizardProject}/builds/{customLutBuild}/checkout/paypal/orders', [CustomLutPayPalOrderController::class, 'store'])
            ->middleware('throttle:custom-lut-checkout')
            ->name('checkout.paypal.orders.store');
        Route::patch('/{wizardProject}', [ProjectMutationController::class, 'update'])
            ->middleware('throttle:lut-wizard-mutation')
            ->name('update');
        Route::delete('/{wizardProject}', [CustomLutProjectController::class, 'destroy'])->name('destroy');
        Route::post('/{wizardProject}/duplicate', [CustomLutProjectController::class, 'duplicate'])
            ->middleware('throttle:lut-wizard-duplicate')
            ->name('duplicate');
        Route::post('/{wizardProject}/style', [ProjectStyleController::class, 'store'])
            ->middleware('throttle:lut-wizard-mutation')
            ->name('style.store');
        Route::post('/{wizardProject}/photos', [ProjectPhotoController::class, 'store'])
            ->middleware('throttle:lut-wizard-photo-upload')
            ->name('photos.store');
        Route::delete('/{wizardProject}/photos/{wizardProjectPhoto}', [ProjectPhotoController::class, 'destroy'])
            ->name('photos.destroy');
        Route::get('/{wizardProject}/photos/{wizardProjectPhoto}/preview', ProjectPhotoPreviewController::class)
            ->middleware(['signed', 'throttle:lut-wizard-preview'])
            ->name('photos.preview');
        Route::post('/{wizardProject}/variations', [ProjectVariationController::class, 'store'])
            ->middleware('throttle:lut-wizard-variation')
            ->name('variations.store');
        Route::post('/{wizardProject}/variations/{wizardProjectVariant}/select', [ProjectVariationController::class, 'select'])
            ->middleware('throttle:lut-wizard-mutation')
            ->name('variations.select');
    });

    Route::prefix('account')->name('account.')->group(function (): void {
        Route::get('/custom-luts', [AccountCustomLutController::class, 'index'])->name('custom-luts.index');
    });
});

Route::inertia('/terms', 'Legal/Terms')->name('terms');
Route::inertia('/privacy', 'Legal/Privacy')->name('privacy');
Route::inertia('/terms-of-sale', 'Legal/TermsOfSale')->name('terms-of-sale');
Route::inertia('/license', 'Legal/License')->name('license');
Route::inertia('/refund-policy', 'Legal/RefundPolicy')->name('refund-policy');

Route::post('/webhooks/paypal', PayPalWebhookController::class)->name('webhooks.paypal');

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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware([$authMiddleware, 'verified', 'account.active'])->group(function (): void {
    Route::get('/shop/{slug}/checkout', ShowCheckoutController::class)->name('checkout.show');
    Route::post('/shop/{slug}/checkout/paypal/orders', StorePayPalOrderController::class)
        ->middleware('throttle:checkout-create')
        ->name('checkout.paypal.orders.store');
    Route::post('/shop/{slug}/claim', ClaimFreeProductController::class)
        ->middleware('throttle:checkout-free-claim')
        ->name('checkout.free.claim');
});

Route::middleware([$authMiddleware, 'verified', 'account.active'])
    ->prefix('account')
    ->name('account.')
    ->group(function (): void {
        Route::get('/luts', [LutLibraryController::class, 'index'])->name('luts.index');
        Route::get('/luts/{entitlement}/download', [LutLibraryController::class, 'download'])
            ->middleware('throttle:account-downloads')
            ->name('luts.download');
        Route::get('/custom-luts/purchased', [CustomLutPurchaseController::class, 'index'])->name('custom-luts.purchased.index');
        Route::get('/custom-luts/purchased/{entitlement}', [CustomLutPurchaseController::class, 'show'])->name('custom-luts.purchased.show');
        Route::get('/custom-lut-packages/{entitlement}/download', [EntitlementDownloadController::class, 'download'])
            ->middleware('throttle:entitlement-downloads')
            ->name('custom-luts.download');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/paypal/capture', CaptureOrderPayPalController::class)
            ->middleware('throttle:checkout-capture')
            ->name('orders.paypal.capture');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('/downloads', DownloadHistoryController::class)->name('downloads.index');
    });
