import { rmSync } from 'node:fs';

import { e2eStatePath } from './environment';

export default async function globalTeardown(): Promise<void> {
    rmSync(e2eStatePath, { force: true });
}
