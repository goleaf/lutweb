<?php

use App\Models\AuditEvent;
use App\Models\BundleItem;
use App\Models\CustomLutBuild;
use App\Models\CustomLutBuildFile;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\LutTestUpload;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PayPalWebhookEvent;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\StorefrontImageVariant;
use App\Models\User;
use App\Models\WizardProject;
use App\Models\WizardProjectPhoto;
use App\Models\WizardProjectVariant;
use Database\Seeders\LocalDemoApplicationSeeder;
use Illuminate\Support\Facades\Hash;

test('local demo application seeder creates the runtime model graph', function () {
    $this->seed(LocalDemoApplicationSeeder::class);

    foreach ([
        AuditEvent::class,
        BundleItem::class,
        CustomLutBuild::class,
        CustomLutBuildFile::class,
        DownloadEvent::class,
        Entitlement::class,
        LutTestUpload::class,
        NotificationDispatch::class,
        Order::class,
        OrderItem::class,
        PayPalWebhookEvent::class,
        Payment::class,
        Product::class,
        ProductExample::class,
        ProductFile::class,
        ProductMedia::class,
        ProductVersion::class,
        StorefrontImageVariant::class,
        User::class,
        WizardProject::class,
        WizardProjectPhoto::class,
        WizardProjectVariant::class,
    ] as $model) {
        expect($model::query()->count())->toBeGreaterThan(0, $model.' should be seeded');
    }
});

test('local demo application seeder keeps default-account security guarantees', function () {
    $this->seed(LocalDemoApplicationSeeder::class);

    expect(User::query()->whereIn('email', ['admin@example.com', 'user@example.com'])->exists())->toBeFalse();

    User::query()
        ->get()
        ->each(fn (User $user) => expect(Hash::check('password', $user->password))->toBeFalse());
});

test('local demo application seeder refuses production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    (new LocalDemoApplicationSeeder)->run();
})->throws(RuntimeException::class, 'Local demo application data may only be seeded in local or testing environments.');
