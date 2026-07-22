import { expect, test } from '@playwright/test';

import { attachReleaseGuards, expectNoDocumentOverflow } from './helpers/state';

test('empty category layout stays compact across desktop and mobile', async ({
    page,
}) => {
    const assertClean = attachReleaseGuards(page);
    const filters = page.getByRole('region', { name: 'Catalog filters' });
    const catalogHeading = page.getByRole('heading', {
        name: 'Travel catalog',
    });
    const emptyState = page.getByRole('status').filter({
        has: page.getByRole('heading', {
            name: 'No published LUTs in this category yet.',
        }),
    });

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/luts/travel');

    await expect(filters).toBeVisible();
    await expect(catalogHeading).toBeVisible();
    await expect(emptyState).toBeVisible();

    const desktopFiltersBox = await filters.boundingBox();
    const desktopHeadingBox = await catalogHeading.boundingBox();
    const desktopEmptyStateBox = await emptyState.boundingBox();

    expect(desktopFiltersBox).not.toBeNull();
    expect(desktopHeadingBox).not.toBeNull();
    expect(desktopEmptyStateBox).not.toBeNull();
    expect(
        Math.abs((desktopFiltersBox?.y ?? 0) - (desktopHeadingBox?.y ?? 0)),
    ).toBeLessThanOrEqual(8);
    expect(
        desktopEmptyStateBox?.height ?? Number.POSITIVE_INFINITY,
    ).toBeLessThanOrEqual(240);

    await page.setViewportSize({ width: 390, height: 844 });

    const mobileFiltersBox = await filters.boundingBox();
    const mobileHeadingBox = await catalogHeading.boundingBox();

    expect(mobileFiltersBox).not.toBeNull();
    expect(mobileHeadingBox).not.toBeNull();
    expect(mobileHeadingBox?.y ?? Number.POSITIVE_INFINITY).toBeLessThan(
        mobileFiltersBox?.y ?? 0,
    );
    await expectNoDocumentOverflow(page);

    await assertClean();
});
