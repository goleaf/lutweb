import { readFileSync } from 'node:fs';
import path from 'node:path';

import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

type E2eUser = {
    email: string;
    password: string;
};

export type E2eState = {
    base_url: string;
    users: {
        admin: E2eUser;
        customer: E2eUser;
        shopper: E2eUser;
    };
    product: {
        id: string;
        slug: string;
        url: string;
        checkout_url: string;
        try_url: string;
    };
    order: {
        id: string;
        number: string;
        url: string;
    };
    entitlement: {
        id: string;
        download_url: string;
    };
    admin_url: string;
};

type ReleaseGuardOptions = {
    allowPayPal?: boolean;
    allowSameOriginStatuses?: number[];
};

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function stringAt(record: Record<string, unknown>, key: string): string {
    const value = record[key];

    if (typeof value !== 'string' || value === '') {
        throw new Error(`Invalid E2E state key: ${key}`);
    }

    return value;
}

function userAt(record: Record<string, unknown>, key: string): E2eUser {
    const value = record[key];

    if (!isRecord(value)) {
        throw new Error(`Invalid E2E user: ${key}`);
    }

    return {
        email: stringAt(value, 'email'),
        password: stringAt(value, 'password'),
    };
}

export function readE2eState(): E2eState {
    const statePath =
        process.env.E2E_STATE_PATH ??
        path.join('storage', 'framework', 'testing', 'e2e-state.json');
    const decoded: unknown = JSON.parse(readFileSync(statePath, 'utf8'));

    if (!isRecord(decoded)) {
        throw new Error('Invalid E2E state root.');
    }

    const users = decoded.users;
    const product = decoded.product;
    const order = decoded.order;
    const entitlement = decoded.entitlement;

    if (
        !isRecord(users) ||
        !isRecord(product) ||
        !isRecord(order) ||
        !isRecord(entitlement)
    ) {
        throw new Error('Invalid E2E state shape.');
    }

    return {
        base_url: stringAt(decoded, 'base_url'),
        users: {
            admin: userAt(users, 'admin'),
            customer: userAt(users, 'customer'),
            shopper: userAt(users, 'shopper'),
        },
        product: {
            id: stringAt(product, 'id'),
            slug: stringAt(product, 'slug'),
            url: stringAt(product, 'url'),
            checkout_url: stringAt(product, 'checkout_url'),
            try_url: stringAt(product, 'try_url'),
        },
        order: {
            id: stringAt(order, 'id'),
            number: stringAt(order, 'number'),
            url: stringAt(order, 'url'),
        },
        entitlement: {
            id: stringAt(entitlement, 'id'),
            download_url: stringAt(entitlement, 'download_url'),
        },
        admin_url: stringAt(decoded, 'admin_url'),
    };
}

export async function loginAs(page: Page, user: E2eUser): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(user.email);
    await page.getByLabel('Password').fill(user.password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(
        page.getByRole('heading', { name: 'Dashboard' }),
    ).toBeVisible();
}

export async function expectNoDocumentOverflow(page: Page): Promise<void> {
    const hasNoOverflow = await page.evaluate(() => {
        const root = document.documentElement;

        return root.scrollWidth <= root.clientWidth + 1;
    });

    expect(hasNoOverflow).toBe(true);
}

export function attachReleaseGuards(
    page: Page,
    options: ReleaseGuardOptions = {},
): () => Promise<void> {
    const problems: string[] = [];
    const base = new URL(
        process.env.PLAYWRIGHT_BASE_URL ?? 'https://lutweb.test',
    );

    page.on('console', (message) => {
        const text = message.text();

        if (
            message.type() === 'error' ||
            text.includes('[Vue warn]') ||
            text.includes('Content Security Policy')
        ) {
            problems.push(`console:${message.type()}:${text}`);
        }
    });

    page.on('pageerror', (error) => {
        problems.push(`pageerror:${error.message}`);
    });

    page.on('response', (response) => {
        const url = new URL(response.url());
        const status = response.status();
        const allowedStatuses = options.allowSameOriginStatuses ?? [];

        if (
            url.origin === base.origin &&
            !allowedStatuses.includes(status) &&
            (status >= 500 ||
                (status === 404 && url.pathname !== '/favicon.ico'))
        ) {
            problems.push(`response:${status}:${url.pathname}`);
        }

        if (
            !options.allowPayPal &&
            /(^|\.)paypal(?:objects)?\.com$/i.test(url.hostname)
        ) {
            problems.push(`third-party-paypal:${url.hostname}`);
        }
    });

    return async (): Promise<void> => {
        expect(problems).toEqual([]);
    };
}

export async function expectNoPrivatePaths(page: Page): Promise<void> {
    const html = await page.content();

    expect(html).not.toContain('storage/app');
    expect(html).not.toContain('catalog/product-files');
    expect(html).not.toContain('custom-lut-builds');
    expect(html).not.toContain('storefront-sources');
    expect(html).not.toContain('/private/');
}
