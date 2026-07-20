<?php

use App\Models\User;

test('a normal user cannot access admin', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('an unverified administrator cannot access admin', function () {
    $user = User::factory()->admin()->unverified()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('a verified administrator can access the admin panel', function () {
    $user = User::factory()->admin()->verified()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

test('users set admin promotes an existing user', function () {
    $user = User::factory()->create([
        'email' => 'curator@example.com',
        'is_admin' => false,
    ]);

    $this->artisan('users:set-admin', ['email' => 'CURATOR@example.com'])
        ->assertExitCode(0);

    expect($user->refresh()->is_admin)->toBeTrue();
});

test('users set admin revoke removes admin access', function () {
    $user = User::factory()->admin()->create([
        'email' => 'curator@example.com',
    ]);

    $this->artisan('users:set-admin', [
        'email' => 'curator@example.com',
        '--revoke' => true,
    ])->assertExitCode(0);

    expect($user->refresh()->is_admin)->toBeFalse();
});

test('users set admin fails for an unknown email', function () {
    $this->artisan('users:set-admin', ['email' => 'missing@example.com'])
        ->assertExitCode(1);
});
