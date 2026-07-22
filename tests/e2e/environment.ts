import path from 'node:path';

export const e2eTestingRoot = path.resolve('storage', 'framework', 'testing');

function resolveTestingPath(requestedPath: string, label: string): string {
    const resolvedPath = path.resolve(requestedPath);

    if (!resolvedPath.startsWith(`${e2eTestingRoot}${path.sep}`)) {
        throw new Error(`${label} must be inside storage/framework/testing.`);
    }

    return resolvedPath;
}

export const e2eDatabasePath = resolveTestingPath(
    process.env.E2E_DATABASE_PATH ?? path.join(e2eTestingRoot, 'e2e.sqlite'),
    'E2E database',
);

export const e2eStatePath = resolveTestingPath(
    process.env.E2E_STATE_PATH ?? path.join(e2eTestingRoot, 'e2e-state.json'),
    'E2E state file',
);

export const e2eBaseURL =
    process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8787';

const inheritedEnvironment: Record<string, string> = {};

for (const [key, value] of Object.entries(process.env)) {
    if (value !== undefined) {
        inheritedEnvironment[key] = value;
    }
}

export const e2eEnvironment: Record<string, string> = {
    ...inheritedEnvironment,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    APP_URL: e2eBaseURL,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabasePath,
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    E2E_DATABASE_PATH: e2eDatabasePath,
    E2E_STATE_PATH: e2eStatePath,
};
