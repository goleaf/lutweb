<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

function validRegistrationData(array $overrides = []): array
{
    return [
        'name' => 'Avery Stone',
        'email' => 'AVERY@example.com',
        'country_code' => 'us',
        'password' => 'marketplace-password',
        'password_confirmation' => 'marketplace-password',
        'accept_terms' => '1',
        'accept_privacy' => '1',
        ...$overrides,
    ];
}

test('login page can be rendered', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login'));
});

test('registration page can be rendered', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('countries.US', 'United States')
            ->where('countries.DE', 'Germany'));
});

test('a user can register with valid data', function () {
    Notification::fake();

    $this->post(route('register.store'), validRegistrationData())
        ->assertRedirect('/dashboard');

    $this->assertAuthenticated();

    $user = User::query()->firstOrFail();

    expect($user->name)->toBe('Avery Stone')
        ->and($user->email)->toBe('avery@example.com')
        ->and($user->country_code)->toBe('US')
        ->and(Hash::check('marketplace-password', $user->password))->toBeTrue();
});

test('registration rejects an invalid country code', function () {
    $this->from('/register')
        ->post(route('register.store'), validRegistrationData([
            'country_code' => 'ZZ',
        ]))
        ->assertRedirect('/register')
        ->assertSessionHasErrors('country_code');

    expect(User::query()->count())->toBe(0);
});

test('registration requires Terms acceptance', function () {
    $this->from('/register')
        ->post(route('register.store'), validRegistrationData([
            'accept_terms' => '0',
        ]))
        ->assertRedirect('/register')
        ->assertSessionHasErrors('accept_terms');

    expect(User::query()->count())->toBe(0);
});

test('registration requires Privacy acceptance', function () {
    $this->from('/register')
        ->post(route('register.store'), validRegistrationData([
            'accept_privacy' => '0',
        ]))
        ->assertRedirect('/register')
        ->assertSessionHasErrors('accept_privacy');

    expect(User::query()->count())->toBe(0);
});

test('registered acceptance timestamps and versions are saved', function () {
    $this->post(route('register.store'), validRegistrationData());

    $user = User::query()->firstOrFail();

    expect($user->terms_accepted_at)->not->toBeNull()
        ->and($user->privacy_accepted_at)->not->toBeNull()
        ->and($user->terms_version)->toBe(config('legal.terms_version'))
        ->and($user->privacy_version)->toBe(config('legal.privacy_version'));
});

test('registered users receive an email verification notification', function () {
    Notification::fake();

    $this->post(route('register.store'), validRegistrationData());

    Notification::assertSentTo(User::query()->firstOrFail(), VerifyEmail::class);
});

test('a user can log in', function () {
    $user = User::factory()->create([
        'email' => 'buyer@example.com',
        'password' => Hash::make('marketplace-password'),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'marketplace-password',
        'remember' => '1',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials are rejected', function () {
    User::factory()->create([
        'email' => 'buyer@example.com',
        'password' => Hash::make('marketplace-password'),
    ]);

    $this->from('/login')
        ->post(route('login.store'), [
            'email' => 'buyer@example.com',
            'password' => 'wrong-password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a user can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('forgot-password request works', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'buyer@example.com',
    ]);

    $this->post(route('password.email'), [
        'email' => $user->email,
    ])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('password can be reset with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'buyer@example.com',
    ]);

    $this->post(route('password.email'), [
        'email' => $user->email,
    ]);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-marketplace-password',
        'password_confirmation' => 'new-marketplace-password',
    ])->assertRedirect(route('login', absolute: false));

    expect(Hash::check('new-marketplace-password', $user->refresh()->password))->toBeTrue();
});

test('a guest cannot access dashboard', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login', absolute: false));
});

test('an unverified authenticated user cannot access dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('a verified authenticated user can access dashboard', function () {
    $user = User::factory()->create([
        'country_code' => 'US',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.country_code', 'US')
            ->has('auth.user.email_verified_at'));
});

test('a signed verification link verifies the user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/dashboard');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect('/dashboard');

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

test('a verification email can be resent', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('verification.notice', absolute: false))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('fortify exposes only the milestone authentication features', function () {
    expect(config('fortify.features'))->toBe([
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
    ]);

    $routeNames = collect(Route::getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    expect($routeNames)
        ->not->toContain('two-factor.enable')
        ->not->toContain('two-factor.login')
        ->not->toContain('passkey.login')
        ->not->toContain('user-profile-information.update')
        ->not->toContain('user-password.update');
});
