import { expect, test } from '@playwright/test';

import {
    attachReleaseGuards,
    expectNoDocumentOverflow,
    expectNoPrivatePaths,
    readE2eState,
} from './helpers/state';

let state: ReturnType<typeof readE2eState>;

test.beforeAll(() => {
    state = readE2eState();
});

test('home and shop render across the release smoke matrix', async ({
    page,
}) => {
    const assertClean = attachReleaseGuards(page);

    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/');

    await expect(page.getByRole('heading', { level: 1 })).toContainText(
        'Professional LUTs',
    );
    await expect(
        page.getByRole('link', { name: 'Explore LUTs' }),
    ).toBeVisible();
    await expectNoDocumentOverflow(page);
    await expectNoPrivatePaths(page);

    await page.goto('/shop');
    await expect(
        page.getByRole('heading', { name: 'Browse professional LUTs' }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: /E2E Release Candidate LUT/ }),
    ).toBeVisible();
    await expectNoDocumentOverflow(page);
    await expectNoPrivatePaths(page);

    await assertClean();
});

test('shop search updates the URL and filtered shop is noindex', async ({
    page,
}) => {
    const assertClean = attachReleaseGuards(page);

    await page.goto('/shop');
    await page.getByLabel('Search').fill('release candidate');
    await expect(page).toHaveURL(/q=release\+candidate|q=release%20candidate/);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
        'content',
        /noindex,follow/,
    );

    await assertClean();
});

test('product page exposes responsive public images and safe product metadata', async ({
    page,
}) => {
    const assertClean = attachReleaseGuards(page);

    await page.goto(state.product.url);

    await expect(
        page
            .getByRole('heading', { name: 'E2E Release Candidate LUT' })
            .first(),
    ).toBeVisible();
    await expect(page.locator('picture img').first()).toHaveAttribute(
        'src',
        /\/storage\/storefront\/e2e\//,
    );
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
        'href',
        /\/shop\/e2e-release-candidate-lut-/,
    );
    await expectNoPrivatePaths(page);

    const jsonLdText = await page
        .locator('script[type="application/ld+json"]')
        .first()
        .textContent();

    expect(jsonLdText).not.toBeNull();
    const jsonLd: unknown = JSON.parse(jsonLdText ?? '{}');

    expect(JSON.stringify(jsonLd)).toContain('E2E Release Candidate LUT');
    expect(JSON.stringify(jsonLd)).not.toContain('catalog/product-files');
    expect(JSON.stringify(jsonLd)).not.toContain('review');

    await assertClean();
});

test('before and after comparison supports keyboard and side-by-side mode', async ({
    page,
}) => {
    const assertClean = attachReleaseGuards(page);

    await page.goto(state.product.url);

    const slider = page.getByLabel(/Comparison position/).first();
    await slider.focus();
    await page.keyboard.press('ArrowRight');
    await expect(slider).toHaveValue('51');

    await page
        .getByRole('button', { name: /Side by side/ })
        .first()
        .click();
    await expect(page.getByText('Before').first()).toBeVisible();
    await expect(page.getByText('After').first()).toBeVisible();

    await assertClean();
});
