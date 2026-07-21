import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { transformV1 } from '../../resources/js/lib/lut-transform/transform-v1.js';
import type { CanonicalLutParameters } from '../../resources/js/types/lut-wizard.js';

interface FixtureRgb {
    red: number;
    green: number;
    blue: number;
}

interface FixtureCase {
    name: string;
    parameters: CanonicalLutParameters;
    input: FixtureRgb;
    expected: FixtureRgb;
    identity?: boolean;
}

interface FixtureFile {
    tolerance: number;
    identity_tolerance: number;
    cases: FixtureCase[];
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function parseFixture(value: unknown): FixtureFile {
    if (!isRecord(value) || !Array.isArray(value.cases)) {
        throw new Error('Invalid conformance fixture.');
    }

    return value as unknown as FixtureFile;
}

function assertClose(
    caseName: string,
    channel: keyof FixtureRgb,
    actual: number,
    expected: number,
    tolerance: number,
): void {
    if (!Number.isFinite(actual) || Math.abs(actual - expected) > tolerance) {
        throw new Error(
            `${caseName} ${channel} expected ${expected}, received ${actual}`,
        );
    }
}

const fixturePath = join(
    process.cwd(),
    'tests/Fixtures/lut-transform-v1-conformance.json',
);
const fixture = parseFixture(
    JSON.parse(readFileSync(fixturePath, 'utf8')) as unknown,
);

for (const item of fixture.cases) {
    const actual = transformV1(item.input, item.parameters);
    const tolerance = item.identity
        ? fixture.identity_tolerance
        : fixture.tolerance;

    assertClose(item.name, 'red', actual.red, item.expected.red, tolerance);
    assertClose(
        item.name,
        'green',
        actual.green,
        item.expected.green,
        tolerance,
    );
    assertClose(item.name, 'blue', actual.blue, item.expected.blue, tolerance);
}

console.log(
    `LUT Transform V1 conformance passed (${fixture.cases.length} cases).`,
);
