<?php

use App\Models\User;
use Database\Seeders\LocalDemoUserSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

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

test('DatabaseSeeder does not call user seeders', function () {
    expect(File::get(database_path('seeders/DatabaseSeeder.php')))
        ->not->toContain('UserSeeder::class')
        ->not->toContain('LocalDemoUserSeeder::class');
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

test('optional local demo seeder prints temporary random credentials that can log in', function () {
    Artisan::call('db:seed', ['--class' => LocalDemoUserSeeder::class]);
    $output = Artisan::output();

    preg_match('/Temporary password: (?P<password>.+)/', $output, $matches);

    expect($matches['password'] ?? null)
        ->toBeString()
        ->not->toBe('')
        ->not->toBe('password');

    $customer = User::query()
        ->where('email', 'demo-customer@example.test')
        ->firstOrFail();

    expect($customer->is_admin)->toBeFalse()
        ->and($customer->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check($matches['password'], $customer->password))->toBeTrue()
        ->and(Hash::check('password', $customer->password))->toBeFalse();

    $this->post(route('login.store'), [
        'email' => 'demo-customer@example.test',
        'password' => $matches['password'],
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($customer);
});

test('optional local demo seeder source has no fixed credential', function () {
    expect(File::get(database_path('seeders/LocalDemoUserSeeder.php')))
        ->not->toContain('public const Password')
        ->not->toContain('Hash::make(self::Password)')
        ->not->toContain("Hash::make('password')")
        ->not->toContain('Hash::make("password")');
});

test('optional local demo seeder keeps stable accounts idempotent', function () {
    $this->seed(LocalDemoUserSeeder::class);

    $firstIds = User::query()
        ->whereIn('email', ['demo-admin@example.test', 'demo-customer@example.test'])
        ->pluck('id', 'email');

    $this->seed(LocalDemoUserSeeder::class);

    expect(User::query()->count())->toBe(2)
        ->and($firstIds)->toHaveCount(2)
        ->and(User::query()
            ->whereIn('email', ['demo-admin@example.test', 'demo-customer@example.test'])
            ->pluck('id', 'email'))
        ->toEqual($firstIds);
});

test('users set admin remains intentional for an existing account', function () {
    $user = User::factory()->verified()->create([
        'email' => 'real-admin@example.test',
        'is_admin' => false,
    ]);

    $this->artisan('users:set-admin real-admin@example.test')
        ->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});
