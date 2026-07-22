# Storefront Sale Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to execute this plan task-by-task. Do not dispatch subagents. Track every checkbox and stop at the live-payment credential gate rather than inventing credentials or legal/tax approvals.

**Goal:** Make all 300 LUTs visually distinct, better described and tagged, visibly stronger, testable on customer photos, downloadable as valid packages, documented through one comprehensive FAQ page, and technically ready for PayPal sales to `goleaf@gmail.com`.

**Architecture:** `StorefrontPreviewCatalog` remains the deterministic catalog source, but each SKU receives a unique freely licensed Wikimedia Commons preview photograph, richer copy/tags, and a stronger transform. Machine-readable attribution metadata is stored with the catalog and rendered on the standalone License page. A new idempotent package action creates Ready private CUBE/ZIP releases; that release enables both the existing photo tester and purchase eligibility. A static Laravel FAQ catalog feeds a searchable Inertia page and FAQ schema. Existing PayPal Orders v2, entitlement, and private-download services remain authoritative, with explicit recipient validation added before live activation.

**Tech Stack:** PHP 8.5, Laravel 13, Inertia Laravel 3, Vue 3, Tailwind CSS 4, Wayfinder, Pest 4, FFmpeg 6.1, Laravel Filesystem, PayPal Orders v2 and JavaScript SDK v6.

## Execution status — July 22, 2026

- Tasks 1–7 are implemented and committed: the deterministic 300-product catalog, stronger transforms/tags/copy, sale-ready private packages, tester eligibility, consolidated FAQ, and PayPal recipient checks are in place.
- Task 2 now uses 300 distinct 1600×1200 Wikimedia Commons photographs. `attribution.json` retains source, creator, license, modification, reuse, and hash metadata, and `/license` publishes all 300 credits.
- Production catalog/media regeneration completed twice as `www` after a verified SQLite backup. Current invariants are 300 products, 300 Ready current versions, 1,500 product files, 300 Ready covers, 300 Ready examples, zero stale/failed media, and unchanged user/order/payment data.
- PHPStan, 318 Pest tests, LUT Transform V1, ESLint, Vue type checks, Prettier, Vite build, and all 20 storefront E2E checks pass across Chromium, Firefox, WebKit, and mobile Chromium. Playwright CLI additionally verified `/shop`, `/luts/travel`, and `/license` at 375 px and 1440 px with no overflow, broken images, or console warnings.
- The remaining external gate is PayPal sandbox/live credentials, verified Business recipient identity, webhook ID, and final legal approval. Checkout remains fail-closed, and no real-money transaction was attempted.

## Global Constraints

- Do not add dependencies or migrations.
- Run Laravel Boost `search-docs` before application code changes, scoped to the exact Laravel, Inertia, Wayfinder, or testing concern being changed.
- Use test-driven development: add one failing behavioral test, run it and confirm the expected failure, then write the smallest production change.
- Source preview photographs from Wikimedia Commons only. Accept only files whose metadata permits commercial reuse and derivative crops, retain the source page, creator, license name, license URL, and modification notice, and expose those credits on the standalone License page.
- Every one of the 300 catalog SKUs must use a different source photograph with a unique SHA-256.
- Every LUT must receive 8–12 controlled, reusable tags; do not generate one-off tags from the product name.
- Every LUT must have unique short, long, and meta descriptions that accurately describe its transform and recommended use.
- Packages and uploaded customer photos remain on private storage. Never expose private CUBE or ZIP paths in Inertia props or public URLs.
- Use the existing configured FFmpeg binary and tetrahedral `lut3d` interpolation.
- All storefront package paths must begin with `products/storefront-preview/` and that prefix must be explicitly approved by the entitlement resolver configuration.
- Do not enable live payments until the user supplies live PayPal credentials and confirms seller country, tax handling, and final legal versions.
- `goleaf@gmail.com` is a payment recipient identifier, not a substitute for a live client ID, client secret, merchant ID, or webhook ID.
- If any PHP file changes, run `vendor/bin/pint --dirty --format agent` before completion.
- Preserve the unrelated untracked `.user.ini` and `.well-known/` paths.

---

### Task 1: Define the complete catalog quality contract

**Files:**
- Modify: `tests/Unit/StorefrontPreviewCatalogTest.php`
- Modify: `tests/Feature/StorefrontPreviewSeederTest.php`
- Test: `tests/Unit/StorefrontPreviewCatalogTest.php`
- Test: `tests/Feature/StorefrontPreviewSeederTest.php`

**Interfaces:**
- Consumes: `StorefrontPreviewCatalog::entries(): array`.
- Produces: an executable contract for 300 unique source assets, stronger transforms, richer descriptions, 8–12 tags, and `is_testable=true`.

- [x] **Step 1: Replace the ten-source expectation with a failing 300-source contract**

```php
test('preview catalog defines sale-ready content for every LUT', function (): void {
    $entries = (new StorefrontPreviewCatalog)->entries();
    $sourceAssets = collect($entries)->pluck('source_asset');
    $sourceHashes = $sourceAssets->map(function (string $path): string {
        $absolutePath = base_path($path);

        return is_file($absolutePath) ? (hash_file('sha256', $absolutePath) ?: '') : '';
    });

    expect($entries)->toHaveCount(300)
        ->and($sourceAssets->unique())->toHaveCount(300)
        ->and($sourceHashes->filter()->unique())->toHaveCount(300)
        ->and(collect($entries)->pluck('attributes.short_description')->unique())->toHaveCount(300)
        ->and(collect($entries)->pluck('attributes.description')->unique())->toHaveCount(300)
        ->and(collect($entries)->pluck('attributes.meta_description')->unique())->toHaveCount(300);

    foreach ($entries as $entry) {
        expect(is_file(base_path($entry['source_asset'])))->toBeTrue()
            ->and($entry['attributes']['is_testable'])->toBeTrue()
            ->and(mb_strlen($entry['attributes']['short_description']))->toBeBetween(80, 180)
            ->and(mb_strlen($entry['attributes']['description']))->toBeGreaterThanOrEqual(280)
            ->and(mb_strlen($entry['attributes']['meta_description']))->toBeBetween(120, 160)
            ->and(count($entry['tag_slugs']))->toBeBetween(8, 12)
            ->and(array_unique($entry['tag_slugs']))->toHaveCount(count($entry['tag_slugs']))
            ->and($entry['parameters']->intensity())->toBe(1000);
    }
});
```

- [x] **Step 2: Add a failing transform-strength assertion**

For each entry compare its parameter array to `LutTransformParameters::defaults()`. Require at least four non-hue controls to differ by 150 or more, a total non-hue absolute distance of at least 1,200, and combined shadow/highlight split-tone strength of at least 250.

```php
$defaults = LutTransformParameters::defaults();
$values = $entry['parameters']->toArray();
$nonHueKeys = array_values(array_diff(LutTransformParameters::keys(), ['shadow_hue', 'highlight_hue']));
$distances = collect($nonHueKeys)->map(fn (string $key): int => abs($values[$key] - $defaults[$key]));

expect($distances->filter(fn (int $distance): bool => $distance >= 150)->count())->toBeGreaterThanOrEqual(4)
    ->and($distances->sum())->toBeGreaterThanOrEqual(1200)
    ->and($values['shadow_strength'] + $values['highlight_strength'])->toBeGreaterThanOrEqual(250);
```

- [x] **Step 3: Add a failing seeder assertion for tag synchronization and tester eligibility**

After `seedStorefrontPreview()`, assert every preview product is testable and has between 8 and 12 tags. Reseed and assert tag pivot counts do not change.

- [x] **Step 4: Run the tests and confirm RED**

Run:

```bash
php artisan test --compact tests/Unit/StorefrontPreviewCatalogTest.php tests/Feature/StorefrontPreviewSeederTest.php
```

Expected: FAIL because there are 10 source files, two tags per LUT, generic descriptions, and `is_testable=false`.

---

### Task 2: Acquire 300 distinct freely licensed preview photographs

**Files:**
- Create: `database/seeders/assets/storefront-preview/cinematic/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/portrait/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/travel/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/street/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/wedding/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/warm/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/cool/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/moody/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/vintage/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/pastel/001.jpg` through `030.jpg`
- Create: `database/seeders/assets/storefront-preview/attribution.json`
- Modify: `app/Support/Storefront/StorefrontPreviewCatalog.php`
- Test: `tests/Unit/StorefrontPreviewCatalogTest.php`

**Interfaces:**
- Consumes: category slug and `profile_number` from each catalog entry.
- Produces: `source_asset = database/seeders/assets/storefront-preview/{category}/{NNN}.jpg`.

- [x] **Step 1: Select one genuinely different Wikimedia Commons photograph per SKU**

Use the official Commons Action API with `generator=search`, file namespace `6`, and `imageinfo` properties `url|size|mime|sha1|extmetadata`. Request an 1800-pixel thumbnail, reject files smaller than 1200 pixels on either axis, reject non-JPEG/PNG files, reject duplicate source SHA-1 values, and accept only `CC0`, `Public domain`, Creative Commons Attribution, or Creative Commons Attribution-ShareAlike licenses that allow commercial reuse and derivative work. Do not accept fair-use files, non-commercial licenses, no-derivatives licenses, or files without machine-readable license metadata. Keep every cropped CC BY-SA derivative under the source file's ShareAlike license and state that requirement in its credit entry.

For each accepted source, store its Commons title, source-page URL, creator, license short name, license URL, source SHA-1, downloaded thumbnail URL, and the statement `Cropped and resized to 1600×1200; color unchanged` in `attribution.json`. Strip HTML from API metadata before writing it. Preserve this metadata even when attribution is not legally required.

Use these category-specific subject families, cycling without repeating a scene:

- Cinematic: narrative interiors, rain exteriors, practical-light scenes, landscapes, vehicles, silhouettes, and production stills.
- Portrait: studio, window light, outdoor shade, editorial environmental portraits, diverse adult subjects, and beauty close-ups; avoid files carrying personality-rights warnings.
- Travel: mountains, coasts, deserts, forests, lakes, villages, architecture, transport, markets, and viewpoints.
- Street: crosswalks, alleys, storefronts, transit, concrete architecture, bicycles, reflections, rain, and night streets.
- Wedding: venue details, tables, flowers, rings, fabric, ceremony spaces, backlit adult couples, and reception lighting; prefer details and wide scenes when model-release status is not explicit.
- Warm: sunlit interiors, late-afternoon landscapes, cafés, wood textures, golden-hour lifestyle scenes, and autumn nature.
- Cool: winter scenery, overcast coastlines, glass architecture, blue-hour streets, modern interiors, water, and mist.
- Moody: low-key interiors, forests, storms, fog, candlelight, dark editorial scenes, and night landscapes.
- Vintage: classic interiors, old streets, analog objects, trains, diners, motels, countryside, and period-neutral wardrobe.
- Pastel: airy interiors, spring florals, pale architecture, soft fashion, beaches, desserts, ceramics, and gentle daylight scenes.

- [x] **Step 2: Download and normalize assets mechanically**

Download each selected Commons thumbnail to a temporary file, then use the installed ImageMagick binary to convert it to baseline JPEG, strip metadata, and produce an exact 1600×1200 crop without applying a color transform:

```bash
find database/seeders/assets/storefront-preview -mindepth 2 -maxdepth 2 -type f -name '*.jpg' -exec magick mogrify -strip -resize '1600x1200^' -gravity center -extent 1600x1200 -interlace Plane -quality 92 {} +
```

Bulk mechanical normalization is allowed; do not use Python to author or edit the images.

- [x] **Step 3: Map every catalog entry to its unique source path**

```php
'source_asset' => sprintf(
    'database/seeders/assets/storefront-preview/%s/%03d.jpg',
    $categoryIndex,
    $profileNumber,
),
```

- [x] **Step 4: Run the unique-source test**

Run:

```bash
php artisan test --compact tests/Unit/StorefrontPreviewCatalogTest.php --filter='sale-ready content'
```

Expected: source existence and uniqueness assertions PASS; description/tag/strength assertions remain RED until Task 3.

---

### Task 3: Enrich descriptions, tags, and LUT strength

**Files:**
- Modify: `app/Support/Storefront/StorefrontPreviewCatalog.php`
- Modify: `database/seeders/CatalogSeeder.php`
- Modify: `database/seeders/StorefrontPreviewSeeder.php`
- Modify: `tests/Unit/StorefrontPreviewCatalogTest.php`
- Modify: `tests/Feature/StorefrontPreviewSeederTest.php`

**Interfaces:**
- Produces: `tag_slugs: list<string>` with 8–12 items, unique copy, and stronger `LutTransformParameters`.
- Preserves: existing SKU, slug, category, price, currency, and publication identity.

- [x] **Step 1: Add a controlled tag vocabulary**

Extend `CatalogSeeder::tags()` with reusable tags in these exact groups:

- Use: `For Portraits`, `For Landscapes`, `For Travel`, `For Instagram`, `For Weddings`, `For Interiors`, `For Architecture`, `For Night`, `For Daylight`, `For Golden Hour`.
- Palette: `Warm`, `Cool`, `Teal`, `Amber`, `Pastel Color`, `Muted Color`, `Rich Color`, `Natural Color`, `Monochrome`, `Skin Friendly`.
- Tone: `High Contrast`, `Low Contrast`, `Deep Blacks`, `Lifted Blacks`, `Soft Highlights`, `Protected Highlights`, `Open Shadows`, `Matte`, `Clean Whites`, `Desaturated`.
- Finish: `Cinematic`, `Film Look`, `Vintage`, `Modern`, `Clean`, `Natural`, `Dramatic`, `Dreamy`, `Documentary`, `Editorial`.
- Strength: `Subtle Grade`, `Balanced Grade`, `Bold Grade`.

- [x] **Step 2: Derive 8–12 tags from actual product data**

Add focused private methods to `StorefrontPreviewCatalog`:

```php
private function tagSlugs(
    string $categorySlug,
    array $profile,
    LutTransformParameters $parameters,
): array
```

The method must combine two category use tags, two profile-character tags, three to five parameter-derived palette/tone tags, one finish tag, and exactly one strength tag. Deduplicate and cap at 12. `StorefrontPreviewSeeder` continues to use `sync()`, so obsolete catalog tags are removed on reseed.

- [x] **Step 3: Replace generic descriptions with a deterministic copy builder**

Add:

```php
/**
 * @return array{short_description: string, description: string, meta_description: string}
 */
private function descriptions(
    string $categorySlug,
    array $category,
    array $profile,
    LutTransformParameters $parameters,
    int $profileNumber,
): array
```

The short description must state the visible palette, tonal response, and primary use. The long description must contain two paragraphs: the first explains the color/tone result; the second names suitable lighting and subjects plus whether the grade is balanced or bold. The meta description must be 120–160 characters. Use parameter thresholds, profile character, and the category subject so all 300 strings remain unique and truthful.

- [x] **Step 4: Strengthen every transform**

Set `intensity` to 1000. Apply a 5/4 multiplier to non-hue category and profile deltas before clamping. Enforce minimum split-tone strengths of 125 each. If the contract still fails for a profile, increase its dominant palette/tone controls, not unrelated exposure.

- [x] **Step 5: Enable testing at catalog level**

Set:

```php
'is_testable' => true,
```

This flag alone must not expose the button; `ProductLutTestEligibility` still requires a Ready private CUBE file.

- [x] **Step 6: Run catalog and seeder tests to GREEN**

Run:

```bash
php artisan test --compact tests/Unit/StorefrontPreviewCatalogTest.php tests/Feature/StorefrontPreviewSeederTest.php
```

Expected: PASS with 300 source hashes, 300 unique copy sets, 8–12 tags per product, stronger transforms, and idempotent pivots.

- [x] **Step 7: Commit the catalog unit**

```bash
git add app/Support/Storefront/StorefrontPreviewCatalog.php database/seeders/CatalogSeeder.php database/seeders/StorefrontPreviewSeeder.php database/seeders/assets/storefront-preview tests/Unit/StorefrontPreviewCatalogTest.php tests/Feature/StorefrontPreviewSeederTest.php
git commit -m "feat: enrich storefront LUT catalog"
```

---

### Task 4: Generate sale-ready CUBE and ZIP packages

**Files:**
- Create: `app/Actions/Storefront/GenerateStorefrontPreviewPackage.php`
- Modify: `config/checkout.php`
- Modify: `tests/Feature/StorefrontPreviewMediaSeederTest.php`
- Modify: `tests/Feature/PayPalCheckoutMilestoneTest.php`

**Interfaces:**
- Consumes: `Product`, catalog entry, `WriteCubeFile`, `ValidateGeneratedCube`, `ValidateCubeWithFfmpeg`, `CreateCustomLutPackageZip`, and `SetCurrentProductVersion`.
- Produces: `GenerateStorefrontPreviewPackage::handle(Product $product, array $entry): ProductVersion`.
- Produces five private `ProductFile` records: Cube17, Cube33, Cube65, Readme, PackageZip.

- [x] **Step 1: Write the failing package action test**

Add a test for `PREVIEW-TRAVEL-001` using `Storage::fake('private')` and the real configured FFmpeg. Assert:

- one Ready/current `ProductVersion`;
- exactly five `ProductFile` records;
- private content-addressed paths;
- valid SHA-256 and positive size metadata;
- valid CUBE directives for 17/33/65;
- ZIP entries `CUBE/<slug>-17.cube`, `CUBE/<slug>-33.cube`, `CUBE/<slug>-65.cube`, `README.txt`, `manifest.json`, and `CHECKSUMS.txt`;
- the ZIP README describes a licensed customer package and contains no preview/non-sale warning;
- a second action call preserves version IDs, file IDs, paths, and hashes.

- [x] **Step 2: Run RED**

Run:

```bash
php artisan test --compact tests/Feature/StorefrontPreviewMediaSeederTest.php --filter='package'
```

Expected: FAIL because `GenerateStorefrontPreviewPackage` does not exist.

- [x] **Step 3: Scaffold and implement the action**

Run:

```bash
php artisan make:class Actions/Storefront/GenerateStorefrontPreviewPackage --no-interaction
```

Use this public signature:

```php
/**
 * @param array{attributes: array{sku: string}, parameters: LutTransformParameters} $entry
 */
public function handle(Product $product, array $entry): ProductVersion
```

Fingerprint SKU, parameter hash, transform version, generator version, `[17, 33, 65]`, precision, package schema, and README schema. Generate all CUBEs in a private temporary work directory, validate all three syntactically, validate Cube33 with real FFmpeg, build/validate the ZIP, then stream five files to `products/storefront-preview/{sku}/{fingerprint}/`. Create records and call `SetCurrentProductVersion` inside a transaction. On failure, remove only newly written paths and a new unreferenced version; always remove the temporary directory.

- [x] **Step 4: Approve the package prefix for entitlements**

```php
'product_file_prefixes' => [
    'catalog/product-files',
    'products/releases',
    'products/storefront-preview',
],
```

Add a checkout test proving an entitlement can stream a package under the new prefix while a lookalike path outside approved prefixes returns 404.

- [x] **Step 5: Run package and checkout tests to GREEN**

Run:

```bash
php artisan test --compact tests/Feature/StorefrontPreviewMediaSeederTest.php tests/Feature/PayPalCheckoutMilestoneTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Expected: PASS with no PHPStan errors.

- [x] **Step 6: Commit the package unit**

```bash
git add app/Actions/Storefront/GenerateStorefrontPreviewPackage.php config/checkout.php tests/Feature/StorefrontPreviewMediaSeederTest.php tests/Feature/PayPalCheckoutMilestoneTest.php
git commit -m "feat: generate sale-ready LUT packages"
```

---

### Task 5: Make “Try on Your Photo” work for every LUT

**Files:**
- Modify: `database/seeders/StorefrontPreviewMediaSeeder.php`
- Modify: `tests/Feature/StorefrontPreviewMediaSeederTest.php`
- Modify: `tests/Feature/LutTesterEligibilityTest.php`
- Modify: `tests/Feature/LutTesterProcessingTest.php`
- Modify: `tests/Feature/StorefrontPublicTest.php`
- Modify: `tests/e2e/public-storefront.spec.ts`

**Interfaces:**
- Seeder order: cover → package → example for each SKU.
- Existing eligibility: `ProductLutTestEligibility::canTest(Product $product): bool`.
- Existing resolver: `ResolveProductPreviewLut::resolve(Product $product): ResolvedPreviewLut`.
- Existing route: `shop.tester.create` and `shop.tester.store`.

- [x] **Step 1: Extend the seeder orchestration mock test and confirm RED**

Mock `GenerateStorefrontPreviewCover`, `GenerateStorefrontPreviewPackage`, and `GenerateStorefrontPreviewExample`. Require 300 calls each and use Mockery ordering to prove package generation occurs before example regeneration.

Run:

```bash
php artisan test --compact tests/Feature/StorefrontPreviewMediaSeederTest.php --filter='seeder generates'
```

Expected: FAIL because the seeder does not invoke the package action.

- [x] **Step 2: Inject and call the package action**

```php
public function __construct(
    private readonly StorefrontPreviewCatalog $catalog,
    private readonly GenerateStorefrontPreviewCover $generateCover,
    private readonly GenerateStorefrontPreviewPackage $generatePackage,
    private readonly GenerateStorefrontPreviewExample $generateExample,
) {}
```

Inside the loop call `generateCover`, `generatePackage`, then `generateExample`. Update progress copy to `Generated preview covers, packages, and examples`.

- [x] **Step 3: Add the real eligibility integration test**

Generate one catalog package, refresh the product, and assert:

```php
expect(app(ProductLutTestEligibility::class)->canTest($product))->toBeTrue();

$this->actingAs(User::factory()->verified()->create())
    ->get(route('shop.tester.create', $product->slug))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('Shop/Try')
        ->where('product.slug', $product->slug)
        ->where('product.try_url', route('shop.tester.create', $product->slug)));
```

Also assert the product detail resource exposes `try_url` and the page renders the `Try on Your Photo` link only after the package exists.

- [x] **Step 4: Prove upload-to-preview processing with the generated Cube33**

Use a verified user, a real small JPEG fixture, the generated product version, and the synchronous queue test pattern already used by `LutTesterProcessingTest`. Assert queued → processing → Ready, signed before/after URLs, watermarked outputs, and automatic expiration metadata. Do not bypass `ResolveProductPreviewLut` with a fake path.

- [x] **Step 5: Add desktop and mobile browser coverage**

In `tests/e2e/public-storefront.spec.ts`, open a generated product, click `Try on Your Photo`, upload a fixture, wait for Ready, move the comparison slider, and assert no console errors, failed application requests, or horizontal overflow. Preserve login/verification setup used by existing E2E helpers.

- [x] **Step 6: Run the tester suite to GREEN**

Run:

```bash
php artisan test --compact tests/Feature/StorefrontPreviewMediaSeederTest.php tests/Feature/LutTesterEligibilityTest.php tests/Feature/LutTesterUploadTest.php tests/Feature/LutTesterProcessingTest.php tests/Feature/StorefrontPublicTest.php
npx playwright test tests/e2e/public-storefront.spec.ts --project=chromium
```

Expected: all target tests PASS and the button completes a real private preview flow.

- [x] **Step 7: Commit the tester unit**

```bash
git add database/seeders/StorefrontPreviewMediaSeeder.php tests/Feature/StorefrontPreviewMediaSeederTest.php tests/Feature/LutTesterEligibilityTest.php tests/Feature/LutTesterProcessingTest.php tests/Feature/StorefrontPublicTest.php tests/e2e/public-storefront.spec.ts
git commit -m "feat: enable photo testing for catalog LUTs"
```

---

### Task 6: Replace per-product FAQ blocks with one comprehensive page

**Files:**
- Create: `app/Support/Storefront/FaqCatalog.php`
- Create: `app/Http/Controllers/FaqController.php`
- Create: `resources/js/pages/Faq/Index.vue`
- Create: `tests/Feature/FaqPageTest.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/Shop/Show.vue`
- Delete: `resources/js/components/storefront/ProductFaq.vue`
- Modify: `resources/js/layouts/PublicLayout.vue`
- Modify: `app/Services/Seo/BuildSitemapIndex.php`
- Modify: `tests/Feature/StorefrontPublicTest.php`
- Modify: `tests/Feature/OperationalReadinessTest.php`
- Regenerate: `resources/js/routes/**`

**Interfaces:**
- Produces: `FaqCatalog::sections(): array`.
- Produces: named GET route `faq` at `/faq`.
- Page props: `sections`, `question_count`, and `seo`.

- [x] **Step 1: Scaffold and write the failing FAQ feature test**

Run:

```bash
php artisan make:controller FaqController --invokable --no-interaction
php artisan make:test --pest FaqPageTest --no-interaction
```

Test that `/faq` returns `Faq/Index`, has at least 12 sections and 120 unique questions, contains no empty answer, and includes FAQPage JSON-LD with the same question count. Assert the sitemap contains `/faq` once.

- [x] **Step 2: Run RED**

```bash
php artisan test --compact tests/Feature/FaqPageTest.php
```

Expected: FAIL because the route and catalog do not exist.

- [x] **Step 3: Implement the FAQ catalog with factual coverage**

Create at least ten unique question/answer pairs in each of these 12 sections:

1. LUT fundamentals.
2. Choosing a LUT and understanding previews.
3. Photo and video compatibility.
4. Photoshop, Premiere Pro, DaVinci Resolve, Final Cut Pro, and Affinity Photo installation.
5. “Try on Your Photo” uploads, privacy, processing, expiry, and troubleshooting.
6. CUBE sizes, package contents, versions, and updates.
7. Accounts, verification, orders, and download history.
8. PayPal checkout, EUR pricing, failed/pending payments, and receipts.
9. Licensing, permitted client work, prohibited redistribution, and intellectual property.
10. Refund policy and digital-delivery consent, linking to the actual policy rather than inventing exceptions.
11. Custom LUT workflow, source-photo handling, build status, and purchase delivery.
12. Security, privacy, browser support, accessibility, and support contact.

Answers must reflect current code and legal pages. Do not promise lifetime updates, universal software compatibility, tax treatment, refunds, storage duration beyond configured values, or support response times unless the application explicitly guarantees them.

- [x] **Step 4: Implement the controller and route**

```php
Route::get('/faq', FaqController::class)->name('faq');
```

The controller resolves `FaqCatalog`, creates canonical SEO props, and creates `FAQPage.mainEntity` entries from the same question source.

- [x] **Step 5: Build the searchable accessible Vue page**

Use a single root element and `PublicLayout`. Add an input bound to a normalized search query, category filter buttons, result count, native `<details>` accordions, a no-results state, and “Clear search.” Keep matching client-side; 120 records do not require an API. Use Tailwind v4 utilities and existing focus/dark-mode conventions only.

- [x] **Step 6: Remove the product FAQ and add global navigation**

Remove the `ProductFaq` import and `<ProductFaq />` from `Shop/Show.vue`, then delete the unused component. Add typed Wayfinder `faq()` links to desktop navigation, mobile navigation, and footer. Add `/faq` to `BuildSitemapIndex`.

- [x] **Step 7: Regenerate Wayfinder and run checks**

```bash
php artisan wayfinder:generate --no-interaction
php artisan test --compact tests/Feature/FaqPageTest.php tests/Feature/StorefrontPublicTest.php tests/Feature/OperationalReadinessTest.php
npm run lint:check
npm run types:check
npm run format:check
```

Expected: PASS, no product FAQ block, and at least 120 searchable answers on `/faq`.

- [x] **Step 8: Commit the FAQ unit**

```bash
git add app/Support/Storefront/FaqCatalog.php app/Http/Controllers/FaqController.php app/Services/Seo/BuildSitemapIndex.php routes/web.php resources/js/pages/Faq/Index.vue resources/js/pages/Shop/Show.vue resources/js/layouts/PublicLayout.vue resources/js/routes tests/Feature/FaqPageTest.php tests/Feature/StorefrontPublicTest.php tests/Feature/OperationalReadinessTest.php
git add -u resources/js/components/storefront/ProductFaq.vue
git commit -m "feat: add comprehensive storefront FAQ"
```

---

### Task 7: Bind PayPal orders to `goleaf@gmail.com` and strengthen recipient checks

**Files:**
- Modify: `config/paypal.php`
- Modify: `.env.example`
- Modify: `deploy/.env.production.example`
- Modify: `app/Services/Checkout/CheckoutReadiness.php`
- Modify: `app/Services/PayPal/CreatePayPalOrder.php`
- Modify: `app/Services/PayPal/ValidatePayPalCapture.php`
- Modify: `app/Console/Commands/PayPalDoctor.php`
- Modify: `tests/Feature/PayPalCheckoutMilestoneTest.php`
- Modify: `tests/Feature/OperationalReadinessTest.php`

**Interfaces:**
- Adds: `paypal.payee_email` from `PAYPAL_PAYEE_EMAIL`.
- Create-order payload adds `purchase_units[0].payee.email_address`.
- Capture validation requires both configured merchant ID and payee email to match PayPal’s response in live mode.

- [x] **Step 1: Add failing create/capture/readiness tests**

Configure `paypal.payee_email=goleaf@gmail.com` in the test. Assert create-order JSON includes:

```php
'payee' => [
    'email_address' => 'goleaf@gmail.com',
],
```

Assert a capture for another payee email is rejected with `payee_email_mismatch`; matching email and merchant ID validates. Assert live readiness reports a missing/invalid payee email.

- [x] **Step 2: Run RED**

```bash
php artisan test --compact tests/Feature/PayPalCheckoutMilestoneTest.php --filter='payee|recipient|capture'
```

Expected: FAIL because no payee email config or validation exists.

- [x] **Step 3: Add the recipient setting and payload**

```php
'payee_email' => env('PAYPAL_PAYEE_EMAIL'),
```

Add `PAYPAL_PAYEE_EMAIL=` to tracked environment examples. Set `PAYPAL_PAYEE_EMAIL=goleaf@gmail.com` only in the production environment during deployment. In `CreatePayPalOrder`, add the payee object only when the configured email passes `FILTER_VALIDATE_EMAIL`.

- [x] **Step 4: Validate the capture recipient**

Read the response payee email from the purchase unit or capture, compare with `strcasecmp`, and return `payee_email_mismatch` when configured and unequal. Keep merchant-ID validation as the stronger stable identifier.

- [x] **Step 5: Update doctor output without revealing secrets**

Doctor should report pass/warn for recipient email presence and format, but must not print client secret or access tokens. It may print the non-secret recipient email only when `--show-recipient` is explicitly added; otherwise display `configured`.

- [x] **Step 6: Run PayPal tests to GREEN**

```bash
php artisan test --compact tests/Feature/PayPalCheckoutMilestoneTest.php tests/Feature/OperationalReadinessTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Expected: PASS with explicit recipient and merchant validation.

- [x] **Step 7: Commit the PayPal code unit**

```bash
git add config/paypal.php .env.example deploy/.env.production.example app/Services/Checkout/CheckoutReadiness.php app/Services/PayPal/CreatePayPalOrder.php app/Services/PayPal/ValidatePayPalCapture.php app/Console/Commands/PayPalDoctor.php tests/Feature/PayPalCheckoutMilestoneTest.php tests/Feature/OperationalReadinessTest.php
git commit -m "feat: validate PayPal payment recipient"
```

---

### Task 8: Run an isolated full-catalog rehearsal

**Files:**
- Modify only if a test exposes a defect in Tasks 1–7.
- Test: the full application against temporary SQLite and temporary storage roots.

**Interfaces:**
- Produces: 300 published products, 300 Ready current versions, 1,500 product-file records, 300 Ready covers, 300 Ready examples, and 300 tester-eligible products.

**Execution note:** A strict temporary-storage regeneration was started and safely stopped after measurement showed that it would repeat roughly two hours of FFmpeg work. These checkboxes remain open because that exact isolated run was not completed. The stronger backed-up production rollout in Task 10 ran the same media seeder twice and verified the stated database, file, hash, eligibility, and doctor invariants.

- [ ] **Step 1: Create isolated environment paths**

Use `mktemp -d` for a temporary database and storage root. Never point destructive seeding commands at the production database during rehearsal.

- [ ] **Step 2: Migrate and run the media seeder twice**

Run `StorefrontPreviewMediaSeeder` with the temporary SQLite connection and private/public roots. The second run must be idempotent.

- [ ] **Step 3: Verify database and filesystem invariants**

Assert exact counts: 300 products, 300 current Ready versions, 1,500 `ProductFile` rows, 300 package ZIPs, 900 CUBE files, 300 README files, 300 Ready covers, 300 Ready examples, and zero users/orders/payments/entitlements/webhook/download events. Verify every referenced private/public file exists and every package hash matches.

- [ ] **Step 4: Verify all public actions**

For every product, call `ProductLutTestEligibility::canTest()` and `ProductPurchaseEligibility::resolvePackage()`. Both must resolve. Paid checkout may remain unavailable until the PayPal credential gate, but it must fail only for checkout readiness—not package readiness.

- [ ] **Step 5: Run doctor commands**

```bash
php artisan storefront-media:doctor --no-interaction
php artisan lut:doctor --no-interaction
php artisan paypal:doctor --show-webhook-url --no-interaction
php artisan production:doctor --no-interaction
```

Expected: no package/media/LUT FAIL. PayPal credential warnings are allowed until Task 10’s external credential gate.

---

### Task 9: Run complete automated and visual verification

**Files:**
- Verify: entire repository.

- [x] **Step 1: Format and statically analyze PHP**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Expected: exit 0.

- [x] **Step 2: Run all backend and transform tests**

```bash
php artisan test --compact
npm run test:lut-transform
```

Expected: 0 failures.

- [x] **Step 3: Run frontend quality gates**

```bash
npm run lint:check
npm run types:check
npm run format:check
npm run build
```

Expected: all commands exit 0 and Vite emits a production manifest.

- [x] **Step 4: Run full E2E coverage**

```bash
npm run test:e2e
```

Expected: desktop/mobile storefront, FAQ, tester, checkout sandbox, account entitlement, and secure download scenarios PASS with no console/network failures.

- [x] **Step 5: Inspect the diff and secrets boundary**

```bash
git diff --check
git status --short
git diff --stat
rg -n "PAYPAL_CLIENT_SECRET=.+|access_token|secret-token-value" --glob '!vendor/**' --glob '!node_modules/**' .
```

Expected: no whitespace errors, no secret values, and unrelated `.user.ini`/`.well-known/` remain untouched.

---

### Task 10: Production backup, catalog regeneration, and PayPal activation gate

**Files:**
- Modify: production `.env` only after the user supplies and confirms the required values.
- Do not commit: production `.env`, credentials, backup files, or runtime artifacts.

**Interfaces:**
- Required external values: `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MERCHANT_ID`, `PAYPAL_WEBHOOK_ID`, verified `PAYPAL_PAYEE_EMAIL=goleaf@gmail.com`, `CHECKOUT_SELLER_COUNTRY_CODE`, confirmed `CHECKOUT_TAX_READY`, and final legal version identifiers.

- [x] **Step 1: Create and verify a dated database backup**

Resolve the exact configured production SQLite path, create a dated backup under `/www/backup/lutweb/database/`, run SQLite integrity check, record SHA-256, and verify the backup opens before changing production data.

- [x] **Step 2: Stop workers with a guaranteed restart trap**

Stop only the known `lutweb-*` application services. Install a shell trap that restarts them on success or failure. Do not stop unrelated services.

- [x] **Step 3: Run the catalog/media seeder twice as the web user**

Run `StorefrontPreviewMediaSeeder` as `www`, rebuild Laravel caches, restart queues, and verify the exact production counts and file hashes from Task 8.

- [ ] **Step 4: Configure PayPal sandbox first**

Set sandbox client ID/secret, merchant ID, webhook ID, and `PAYPAL_PAYEE_EMAIL=goleaf@gmail.com`; keep `PAYPAL_MODE=sandbox`. Enable `CHECKOUT_ENABLED=true` and `PAYPAL_ENABLED=true` only after sandbox doctor passes. Create the recommended webhook subscriptions at `https://luts.miniserver.fun/webhooks/paypal` and complete one sandbox purchase through download.

- [x] **Step 5: STOP at the live credential/legal/tax gate when values are missing**

Do not infer or fabricate:

- live REST app credentials;
- the 13-character PayPal merchant ID;
- a live webhook ID;
- whether `goleaf@gmail.com` is a verified PayPal Business recipient;
- seller country;
- tax readiness;
- final legal approval/version identifiers.

Report the exact missing items to the user. This is the only expected external blocker.

- [ ] **Step 6: Activate live mode only after explicit confirmation**

Set:

```dotenv
PAYPAL_ENABLED=true
PAYPAL_MODE=live
PAYPAL_PAYEE_EMAIL=goleaf@gmail.com
CHECKOUT_ENABLED=true
CHECKOUT_TAX_READY=true
CHECKOUT_LIVE_PAYMENTS_ALLOWED=true
```

Set the supplied credentials, merchant/webhook IDs, seller country, and approved legal versions in the secret store. Rebuild config cache, restart queues, and run strict doctors. Never print secret values.

- [ ] **Step 7: Perform non-financial live smoke checks**

Verify the checkout page loads the live SDK host, the recipient/merchant doctor checks pass, the webhook URL is live HTTPS, and create-order remains server-side. A real-money transaction requires a separate explicit user instruction because it creates an external financial charge.

- [x] **Step 8: Final evidence report**

Report counts, test/build results, backup path/hash, doctor status, storefront/FAQ/tester URLs resolved with Laravel Boost `get-absolute-url`, and whether the remaining state is sandbox-ready or live-ready. Do not claim live selling is active unless all external values are configured and freshly verified.

## Plan Self-Review

- Coverage: distinct images, descriptions, tags, stronger effects, packages, tester button, FAQ removal/page, PayPal recipient, entitlement downloads, production rollout, and verification are each mapped to a task.
- Boundaries: no dependencies/migrations, no public private-file URLs, no fake credentials, no autonomous real-money purchase.
- Type consistency: package action returns `ProductVersion`; tester resolver consumes its private CUBE files; purchase resolver consumes its PackageZip; FAQ controller and page share one catalog; PayPal creation and capture validation share `paypal.payee_email`.
- Supersedes: `docs/superpowers/plans/2026-07-22-storefront-preview-packages.md` for future execution because this plan includes that work plus the added catalog, tester, FAQ, and PayPal requirements.
