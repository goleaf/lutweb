<?php

use App\Models\User;
use Database\Seeders\LocalDemoUserSeeder;
use Database\Seeders\UserSeeder;

test('DatabaseSeeder creates no users or administrators', function () {
    $this->seed();

    expect(User::query()->count())->toBe(0)
        ->and(User::query()->where('is_admin', true)->count())->toBe(0);
});

test('DatabaseSeeder creates no known default accounts', function () {
    $this->seed();

    expect(User::query()->where('email', 'admin@example.com')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'user@example.com')->exists())->toBeFalse();
});

test('UserSeeder is an intentional no-op', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(0);
});

test('optional local demo seeder refuses production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    (new LocalDemoUserSeeder)->run();
})->throws(RuntimeException::class, 'Local demo users may only be seeded in local or testing environments.');

test('users set admin remains intentional for an existing account', function () {
    $user = User::factory()->verified()->create([
        'email' => 'real-admin@example.test',
        'is_admin' => false,
    ]);

    $this->artisan('users:set-admin real-admin@example.test')
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});
