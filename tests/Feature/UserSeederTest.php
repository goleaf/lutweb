<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('DatabaseSeeder creates default admin and client users', function () {
    $this->seed();

    $admin = User::query()
        ->where('email', 'admin@example.com')
        ->firstOrFail();

    $client = User::query()
        ->where('email', 'user@example.com')
        ->firstOrFail();

    expect($admin->name)->toBe('Admin')
        ->and($admin->is_admin)->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and($client->name)->toBe('Client')
        ->and($client->is_admin)->toBeFalse()
        ->and($client->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password', $client->password))->toBeTrue();
});

test('UserSeeder is idempotent', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()
        ->whereIn('email', [
            'admin@example.com',
            'user@example.com',
        ])
        ->count())->toBe(2);
});
