import { expect, test } from '@playwright/test';

import { attachReleaseGuards, expectNoDocumentOverflow } from './helpers/state';

test('filled category layout stays aligned across desktop and mobile', async ({
    page,
}) => {
    const assertClean = attachReleaseGuards(page);
    const filters = page.getByRole('region', { name: 'Catalog filters' });
    const catalogHeading = page.getByRole('heading', {
        name: 'Travel catalog',
    });
    const productCards = page.locator('article');

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/luts/travel');

    await expect(filters).toBeVisible();
    await expect(catalogHeading).toBeVisible();
    await expect(page.getByText('30 results shown')).toBeVisible();
    await expect(productCards).toHaveCount(12);
    await expect(productCards.first()).toBeVisible();

    const desktopFiltersBox = await filters.boundingBox();
    const desktopHeadingBox = await catalogHeading.boundingBox();

    expect(desktopFiltersBox).not.toBeNull();
    expect(desktopHeadingBox).not.toBeNull();
    expect(
        Math.abs((desktopFiltersBox?.y ?? 0) - (desktopHeadingBox?.y ?? 0)),
    ).toBeLessThanOrEqual(8);

    await page.setViewportSize({ width: 390, height: 844 });

    const mobileFiltersBox = await filters.boundingBox();
    const mobileHeadingBox = await catalogHeading.boundingBox();

    expect(mobileFiltersBox).not.toBeNull();
    expect(mobileHeadingBox).not.toBeNull();
    expect(mobileFiltersBox?.y ?? Number.POSITIVE_INFINITY).toBeLessThan(
        mobileHeadingBox?.y ?? 0,
    );
    await expect(productCards.first()).toBeVisible();
    await expectNoDocumentOverflow(page);

    await assertClean();
});
