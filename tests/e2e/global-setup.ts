import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';

import { e2eDatabasePath, e2eEnvironment, e2eStatePath } from './environment';

function run(command: string, args: string[]): void {
    execFileSync(command, args, {
        stdio: 'inherit',
        env: e2eEnvironment,
    });
}

export default async function globalSetup(): Promise<void> {
    const databasePath = e2eDatabasePath;
    const statePath = e2eStatePath;

    mkdirSync(path.dirname(databasePath), { recursive: true });
    mkdirSync(path.dirname(statePath), { recursive: true });

    if (!existsSync(databasePath)) {
        execFileSync(process.execPath, [
            '-e',
            `require('node:fs').closeSync(require('node:fs').openSync(${JSON.stringify(databasePath)}, 'w'))`,
        ]);
    }

    run('php', ['artisan', 'migrate:fresh', '--seed', '--no-interaction']);
    run('php', [
        'artisan',
        'db:seed',
        '--class=Database\\Seeders\\StorefrontPreviewSeeder',
        '--no-interaction',
    ]);

    if (!existsSync(path.resolve('public', 'storage'))) {
        run('php', ['artisan', 'storage:link', '--force']);
    }

    run('php', ['artisan', 'e2e:prepare', `--output=${statePath}`]);
}
