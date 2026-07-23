<?php

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Tests\Support\TestEnvironmentGuard;

function isolatedTestApplication(string $environment, string $connection, string $database): Application
{
    $application = new Application(dirname(__DIR__, 2));
    $application->instance('env', $environment);
    $application->instance('config', new Repository([
        'database' => [
            'default' => $connection,
            'connections' => [
                $connection => ['database' => $database],
            ],
        ],
    ]));

    return $application;
}

test('phpunit bypasses the shared application configuration cache', function (): void {
    $xml = simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml');
    $environment = [];

    foreach ($xml->php->env as $variable) {
        $environment[(string) $variable['name']] = [
            'value' => (string) $variable['value'],
            'force' => (string) $variable['force'],
        ];
    }

    expect($environment)
        ->toHaveKey('APP_CONFIG_CACHE')
        ->and($environment['APP_CONFIG_CACHE']['value'] ?? null)
        ->toBe('storage/framework/testing/config.php')
        ->and($environment['APP_CONFIG_CACHE']['force'] ?? null)
        ->toBe('true');
});

test('the test environment guard permits only testing with in memory sqlite', function (): void {
    expect(fn () => TestEnvironmentGuard::ensureSafe(
        isolatedTestApplication('testing', 'sqlite', ':memory:'),
    ))->not->toThrow(LogicException::class);

    foreach ([
        ['production', 'sqlite', ':memory:'],
        ['testing', 'sqlite', '/srv/app/database.sqlite'],
        ['testing', 'mysql', 'application'],
    ] as [$environment, $connection, $database]) {
        expect(fn () => TestEnvironmentGuard::ensureSafe(
            isolatedTestApplication($environment, $connection, $database),
        ))->toThrow(LogicException::class);
    }
});
