<?php

use Illuminate\Support\Facades\File;

test('production doctor fails closed when sqlite is not explicitly approved', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config()->set('database.default', 'sqlite');
    config()->set('database.production_sqlite_approved', false);

    $this->artisan('production:doctor')
        ->expectsOutputToContain('FAIL Production SQLite use is explicitly approved');
});

test('production doctor accepts explicitly approved sqlite and templates remain fail closed', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    config()->set('database.default', 'sqlite');
    config()->set('database.production_sqlite_approved', true);

    $this->artisan('production:doctor')
        ->expectsOutputToContain('PASS Production SQLite use is explicitly approved');

    expect(File::get(base_path('.env.example')))
        ->toContain('DB_PRODUCTION_SQLITE_APPROVED=false')
        ->and(File::get(base_path('deploy/.env.production.example')))
        ->toContain('DB_PRODUCTION_SQLITE_APPROVED=false');
});
