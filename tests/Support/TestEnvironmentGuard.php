<?php

namespace Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use LogicException;

final class TestEnvironmentGuard
{
    public static function ensureSafe(Application $application): void
    {
        $configuration = $application->make('config');
        $connection = $configuration->get('database.default');
        $database = is_string($connection)
            ? $configuration->get("database.connections.{$connection}.database")
            : null;

        if ($application->environment('testing') && $connection === 'sqlite' && $database === ':memory:') {
            return;
        }

        throw new LogicException(
            'Refusing to run tests unless APP_ENV is testing and the default database is in-memory SQLite.',
        );
    }
}
