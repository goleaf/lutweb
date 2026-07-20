<?php

use App\Enums\ProductFileKind;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\ProductExample;
use App\Models\ProductFile;
use App\Models\ProductMedia;
use App\Models\ProductVersion;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function storefrontProduct(array $overrides = []): Product
{
    $category = $overrides['category'] ?? null;
    unset($overrides['category']);

    $product = Product::factory()->published()->create([
        'name' => 'Cinematic Forest LUT',
        'slug' => 'cinematic-forest-lut',
        'short_description' => 'A clean cinematic grade for outdoor portraits.',
        'description' => "Soft contrast for greenery.\nBalanced skin tones.",
        'price_cents' => 1999,
        'published_at' => now()->subHour(),
        ...$overrides,
    ]);

    $category ??= Category::factory()->create();

    if ($category instanceof Category) {
        $product->categories()->syncWithoutDetaching([$category->id]);
    }

    return $product->refresh();
}

function storefrontProductWithFullDetail(array $overrides = []): Product
{
    $product = storefrontProduct($overrides);

    ProductMedia::factory()->cover()->for($product)->create([
        'path' => 'products/media/cover-'.$product->id.'.jpg',
        'alt_text' => 'Warm cinematic LUT applied to a portrait',
        'width' => 1600,
        'height' => 1200,
    ]);

    ProductMedia::factory()->for($product)->create([
        'path' => 'products/media/gallery-'.$product->id.'.webp',
        'alt_text' => 'Gallery preview for the LUT',
        'width' => 1200,
        'height' => 900,
        'sort_order' => 2,
    ]);

    ProductExample::factory()->active()->for($product)->create([
        'title' => 'Forest portrait',
        'before_path' => 'products/examples/before-'.$product->id.'.jpg',
        'after_path' => 'products/examples/after-'.$product->id.'.jpg',
        'sort_order' => 2,
    ]);

    $version = ProductVersion::factory()
        ->ready()
        ->current()
        ->for($product)
        ->create();

    ProductFile::factory()->for($version, 'productVersion')->create([
        'kind' => ProductFileKind::SourceCube,
        'path' => 'products/releases/source-'.$product->id.'.cube',
        'original_name' => 'private-source-'.$product->id.'.cube',
    ]);

    ProductFile::factory()->packageZip()->for($version, 'productVersion')->create([
        'path' => 'products/releases/private-package-'.$product->id.'.zip',
        'original_name' => 'private-package-'.$product->id.'.zip',
    ]);

    ProductFile::factory()->for($version, 'productVersion')->create([
        'kind' => ProductFileKind::Cube33,
        'path' => 'products/releases/private-cube33-'.$product->id.'.cube',
        'original_name' => 'private-cube33-'.$product->id.'.cube',
    ]);

    ProductFile::factory()->for($version, 'productVersion')->create([
        'kind' => ProductFileKind::GuidePdf,
        'path' => 'products/releases/private-guide-'.$product->id.'.pdf',
        'original_name' => 'private-guide-'.$product->id.'.pdf',
    ]);

    return $product->refresh();
}

function storefrontProductNames(array $products): array
{
    return collect($products)->pluck('name')->all();
}

test('home page renders for a guest', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('auth.user', null)
            ->has('featuredProducts')
            ->has('categories')
            ->has('freeProducts'));
});

test('home page renders for an authenticated user', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('auth.user.id', $user->id));
});

test('public header shows Login and Register to guests', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null));
});

test('public header shows Dashboard to authenticated users', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id));
});

test('shop page renders', function () {
    $this->get(route('shop.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Index')
            ->has('products.data')
            ->has('filters')
            ->has('filterOptions'));
});

test('shop returns only currently published products', function () {
    $published = storefrontProduct(['name' => 'Published LUT', 'slug' => 'published-lut']);
    storefrontProduct(['name' => 'Future LUT', 'slug' => 'future-lut', 'published_at' => now()->addDay()]);

    $names = storefrontProductNames($this->get(route('shop.index'))->inertiaProps('products.data'));

    expect($names)->toContain($published->name)
        ->not->toContain('Future LUT');
});

test('shop excludes draft products', function () {
    storefrontProduct(['name' => 'Draft LUT', 'slug' => 'draft-lut', 'status' => ProductStatus::Draft]);

    $names = storefrontProductNames($this->get(route('shop.index'))->inertiaProps('products.data'));

    expect($names)->not->toContain('Draft LUT');
});

test('shop excludes archived products', function () {
    storefrontProduct(['name' => 'Archived LUT', 'slug' => 'archived-lut', 'status' => ProductStatus::Archived]);

    $names = storefrontProductNames($this->get(route('shop.index'))->inertiaProps('products.data'));

    expect($names)->not->toContain('Archived LUT');
});

test('shop excludes soft-deleted products', function () {
    $product = storefrontProduct(['name' => 'Deleted LUT', 'slug' => 'deleted-lut']);
    $product->delete();

    $names = storefrontProductNames($this->get(route('shop.index'))->inertiaProps('products.data'));

    expect($names)->not->toContain('Deleted LUT');
});

test('shop excludes products scheduled for the future', function () {
    storefrontProduct(['name' => 'Scheduled LUT', 'slug' => 'scheduled-lut', 'published_at' => now()->addDay()]);

    $names = storefrontProductNames($this->get(route('shop.index'))->inertiaProps('products.data'));

    expect($names)->not->toContain('Scheduled LUT');
});

test('shop filters by category slug', function () {
    $wantedCategory = Category::factory()->create(['slug' => 'portrait']);
    $otherCategory = Category::factory()->create(['slug' => 'travel']);
    storefrontProduct(['name' => 'Portrait LUT', 'slug' => 'portrait-lut', 'category' => $wantedCategory]);
    storefrontProduct(['name' => 'Travel LUT', 'slug' => 'travel-lut', 'category' => $otherCategory]);

    $names = storefrontProductNames($this->get(route('shop.index', ['category' => 'portrait']))->inertiaProps('products.data'));

    expect($names)->toContain('Portrait LUT')
        ->not->toContain('Travel LUT');
});

test('shop filters by tag slug', function () {
    $wanted = Tag::factory()->create(['slug' => 'matte']);
    $other = Tag::factory()->create(['slug' => 'golden']);
    $matte = storefrontProduct(['name' => 'Matte LUT', 'slug' => 'matte-lut']);
    $golden = storefrontProduct(['name' => 'Golden LUT', 'slug' => 'golden-lut']);
    $matte->tags()->attach($wanted);
    $golden->tags()->attach($other);

    $names = storefrontProductNames($this->get(route('shop.index', ['tag' => 'matte']))->inertiaProps('products.data'));

    expect($names)->toContain('Matte LUT')
        ->not->toContain('Golden LUT');
});

test('shop filters by compatible-software slug', function () {
    $resolve = CompatibleSoftware::factory()->create(['slug' => 'davinci-resolve']);
    $photoshop = CompatibleSoftware::factory()->create(['slug' => 'adobe-photoshop']);
    $resolveProduct = storefrontProduct(['name' => 'Resolve LUT', 'slug' => 'resolve-lut']);
    $photoshopProduct = storefrontProduct(['name' => 'Photoshop LUT', 'slug' => 'photoshop-lut']);
    $resolveProduct->compatibleSoftware()->attach($resolve);
    $photoshopProduct->compatibleSoftware()->attach($photoshop);

    $names = storefrontProductNames($this->get(route('shop.index', ['software' => 'davinci-resolve']))->inertiaProps('products.data'));

    expect($names)->toContain('Resolve LUT')
        ->not->toContain('Photoshop LUT');
});

test('shop filters by product type', function () {
    storefrontProduct(['name' => 'Single LUT', 'slug' => 'single-lut', 'type' => ProductType::SingleLut]);
    storefrontProduct(['name' => 'Bundle LUT', 'slug' => 'bundle-lut', 'type' => ProductType::Bundle]);

    $names = storefrontProductNames($this->get(route('shop.index', ['type' => ProductType::Bundle->value]))->inertiaProps('products.data'));

    expect($names)->toContain('Bundle LUT')
        ->not->toContain('Single LUT');
});

test('shop filters free products', function () {
    storefrontProduct(['name' => 'Free LUT', 'slug' => 'free-lut', 'type' => ProductType::FreeLut, 'price_cents' => 0]);
    storefrontProduct(['name' => 'Paid LUT', 'slug' => 'paid-lut', 'price_cents' => 1900]);

    $names = storefrontProductNames($this->get(route('shop.index', ['pricing' => 'free']))->inertiaProps('products.data'));

    expect($names)->toContain('Free LUT')
        ->not->toContain('Paid LUT');
});

test('shop filters paid products', function () {
    storefrontProduct(['name' => 'Free LUT', 'slug' => 'free-lut', 'type' => ProductType::FreeLut, 'price_cents' => 0]);
    storefrontProduct(['name' => 'Paid LUT', 'slug' => 'paid-lut', 'price_cents' => 1900]);

    $names = storefrontProductNames($this->get(route('shop.index', ['pricing' => 'paid']))->inertiaProps('products.data'));

    expect($names)->toContain('Paid LUT')
        ->not->toContain('Free LUT');
});

test('shop searches product names', function () {
    storefrontProduct(['name' => 'Amber Coast LUT', 'slug' => 'amber-coast-lut']);
    storefrontProduct(['name' => 'Forest LUT', 'slug' => 'forest-lut']);

    $names = storefrontProductNames($this->get(route('shop.index', ['q' => 'amber']))->inertiaProps('products.data'));

    expect($names)->toContain('Amber Coast LUT')
        ->not->toContain('Forest LUT');
});

test('shop searches short descriptions', function () {
    storefrontProduct(['name' => 'Soft LUT', 'slug' => 'soft-lut', 'short_description' => 'Made for hazy beach evenings.']);
    storefrontProduct(['name' => 'Clean LUT', 'slug' => 'clean-lut', 'short_description' => 'Made for sharp city scenes.']);

    $names = storefrontProductNames($this->get(route('shop.index', ['q' => 'beach']))->inertiaProps('products.data'));

    expect($names)->toContain('Soft LUT')
        ->not->toContain('Clean LUT');
});

test('shop search does not allow unsafe raw SQL behavior', function () {
    storefrontProduct(['name' => 'Visible Match', 'slug' => 'visible-match']);
    storefrontProduct(['name' => 'Hidden Draft', 'slug' => 'hidden-draft', 'status' => ProductStatus::Draft]);

    $names = storefrontProductNames($this->get(route('shop.index', ['q' => "%' OR 1=1 --"]))->inertiaProps('products.data'));

    expect($names)->toBe([]);
});

test('shop defaults an unsupported sort value safely', function () {
    $featured = storefrontProduct(['name' => 'Featured LUT', 'slug' => 'featured-lut', 'is_featured' => true]);
    storefrontProduct(['name' => 'Ordinary LUT', 'slug' => 'ordinary-lut', 'is_featured' => false]);

    $products = $this->get(route('shop.index', ['sort' => 'unsafe_sql']))->inertiaProps('products.data');

    expect($products[0]['id'])->toBe($featured->id);
});

test('shop sorts newest correctly', function () {
    storefrontProduct(['name' => 'Older LUT', 'slug' => 'older-lut', 'published_at' => now()->subDays(2)]);
    $newest = storefrontProduct(['name' => 'Newest LUT', 'slug' => 'newest-lut', 'published_at' => now()->subHour()]);

    $products = $this->get(route('shop.index', ['sort' => 'newest']))->inertiaProps('products.data');

    expect($products[0]['id'])->toBe($newest->id);
});

test('shop sorts prices ascending correctly', function () {
    $cheap = storefrontProduct(['name' => 'Cheap LUT', 'slug' => 'cheap-lut', 'price_cents' => 900]);
    storefrontProduct(['name' => 'Premium LUT', 'slug' => 'premium-lut', 'price_cents' => 4900]);

    $products = $this->get(route('shop.index', ['sort' => 'price_asc']))->inertiaProps('products.data');

    expect($products[0]['id'])->toBe($cheap->id);
});

test('shop sorts prices descending correctly', function () {
    storefrontProduct(['name' => 'Cheap LUT', 'slug' => 'cheap-lut', 'price_cents' => 900]);
    $premium = storefrontProduct(['name' => 'Premium LUT', 'slug' => 'premium-lut', 'price_cents' => 4900]);

    $products = $this->get(route('shop.index', ['sort' => 'price_desc']))->inertiaProps('products.data');

    expect($products[0]['id'])->toBe($premium->id);
});

test('shop pagination preserves active filters', function () {
    $category = Category::factory()->create(['slug' => 'cinematic']);
    Product::factory()
        ->published()
        ->count(13)
        ->sequence(fn (Sequence $sequence): array => [
            'name' => 'Cinematic LUT '.$sequence->index,
            'slug' => 'cinematic-lut-'.$sequence->index,
        ])
        ->create()
        ->each(fn (Product $product) => $product->categories()->attach($category));

    $links = $this->get(route('shop.index', ['category' => 'cinematic', 'sort' => 'name_asc']))->inertiaProps('products.meta.links');

    expect(collect($links)->pluck('url')->filter()->implode(' '))
        ->toContain('category=cinematic')
        ->toContain('sort=name_asc');
});

test('product detail page renders a published product', function () {
    $product = storefrontProductWithFullDetail();

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Show')
            ->where('product.id', $product->id)
            ->where('product.name', $product->name));
});

test('product detail page returns 404 for a draft product', function () {
    $product = storefrontProduct(['status' => ProductStatus::Draft]);

    $this->get(route('shop.show', $product->slug))->assertNotFound();
});

test('product detail page returns 404 for an archived product', function () {
    $product = storefrontProduct(['status' => ProductStatus::Archived]);

    $this->get(route('shop.show', $product->slug))->assertNotFound();
});

test('product detail page returns 404 for a future product', function () {
    $product = storefrontProduct(['published_at' => now()->addDay()]);

    $this->get(route('shop.show', $product->slug))->assertNotFound();
});

test('product detail page returns 404 for a soft-deleted product', function () {
    $product = storefrontProduct();
    $product->delete();

    $this->get(route('shop.show', $product->slug))->assertNotFound();
});

test('product detail props do not expose ProductFile disk path private filename or URL', function () {
    $product = storefrontProductWithFullDetail();

    $payload = json_encode($this->get(route('shop.show', $product->slug))->inertiaProps('product'), JSON_THROW_ON_ERROR);

    expect($payload)
        ->not->toContain('disk')
        ->not->toContain('products/releases')
        ->not->toContain('private-package-'.$product->id.'.zip')
        ->not->toContain('sha256')
        ->not->toContain('/storage/products/releases');
});

test('SourceCube is not included in public package contents', function () {
    $product = storefrontProductWithFullDetail();

    $labels = $this->get(route('shop.show', $product->slug))->inertiaProps('product.package_contents');

    expect($labels)->not->toContain('Source CUBE');
});

test('safe package labels are derived from allowed file kinds', function () {
    $product = storefrontProductWithFullDetail();

    $labels = $this->get(route('shop.show', $product->slug))->inertiaProps('product.package_contents');

    expect($labels)->toContain('ZIP package')
        ->toContain('33-point CUBE LUT')
        ->toContain('Installation guide');
});

test('product detail includes only active examples', function () {
    $product = storefrontProductWithFullDetail();
    ProductExample::factory()->for($product)->create([
        'title' => 'Inactive sample',
        'is_active' => false,
    ]);

    $titles = collect($this->get(route('shop.show', $product->slug))->inertiaProps('product.examples'))->pluck('title');

    expect($titles)->not->toContain('Inactive sample');
});

test('product examples are ordered correctly', function () {
    $product = storefrontProduct();
    ProductExample::factory()->active()->for($product)->create(['title' => 'Second', 'sort_order' => 20]);
    ProductExample::factory()->active()->for($product)->create(['title' => 'First', 'sort_order' => 10]);

    $titles = collect($this->get(route('shop.show', $product->slug))->inertiaProps('product.examples'))->pluck('title')->all();

    expect($titles)->toBe(['First', 'Second']);
});

test('related products exclude the current product and non-published products', function () {
    $category = Category::factory()->create(['slug' => 'related']);
    $product = storefrontProduct(['name' => 'Current LUT', 'slug' => 'current-lut', 'category' => $category]);
    storefrontProduct(['name' => 'Related LUT', 'slug' => 'related-lut', 'category' => $category]);
    storefrontProduct(['name' => 'Draft Related LUT', 'slug' => 'draft-related-lut', 'category' => $category, 'status' => ProductStatus::Draft]);

    $names = collect($this->get(route('shop.show', $product->slug))->inertiaProps('relatedProducts.data'))->pluck('name');

    expect($names)->toContain('Related LUT')
        ->not->toContain('Current LUT')
        ->not->toContain('Draft Related LUT');
});

test('category page renders an active category', function () {
    $category = Category::factory()->create(['name' => 'Portrait', 'slug' => 'portrait', 'is_active' => true]);

    $this->get(route('categories.show', $category->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Show')
            ->where('category.slug', 'portrait'));
});

test('category page returns 404 for an inactive category', function () {
    $category = Category::factory()->create(['slug' => 'inactive', 'is_active' => false]);

    $this->get(route('categories.show', $category->slug))->assertNotFound();
});

test('category page includes only published products from that category', function () {
    $category = Category::factory()->create(['slug' => 'street']);
    $otherCategory = Category::factory()->create(['slug' => 'travel']);
    storefrontProduct(['name' => 'Street LUT', 'slug' => 'street-lut', 'category' => $category]);
    storefrontProduct(['name' => 'Street Draft LUT', 'slug' => 'street-draft-lut', 'category' => $category, 'status' => ProductStatus::Draft]);
    storefrontProduct(['name' => 'Travel LUT', 'slug' => 'travel-lut', 'category' => $otherCategory]);

    $names = storefrontProductNames($this->get(route('categories.show', $category->slug))->inertiaProps('products.data'));

    expect($names)->toContain('Street LUT')
        ->not->toContain('Street Draft LUT')
        ->not->toContain('Travel LUT');
});

test('bundle page includes ordered component names and only public component links', function () {
    $bundle = storefrontProductWithFullDetail([
        'name' => 'Creator Bundle',
        'slug' => 'creator-bundle',
        'type' => ProductType::Bundle,
        'price_cents' => 5900,
    ]);
    $publicComponent = storefrontProduct(['name' => 'Public Component', 'slug' => 'public-component']);
    $hiddenComponent = storefrontProduct(['name' => 'Hidden Component', 'slug' => 'hidden-component', 'status' => ProductStatus::Draft]);

    BundleItem::factory()->for($bundle, 'bundle')->for($hiddenComponent, 'product')->create(['sort_order' => 20]);
    BundleItem::factory()->for($bundle, 'bundle')->for($publicComponent, 'product')->create(['sort_order' => 10]);

    $items = $this->get(route('shop.show', $bundle->slug))->inertiaProps('product.bundle_items');

    expect(collect($items)->pluck('name')->all())->toBe(['Public Component', 'Hidden Component'])
        ->and($items[0]['url'])->not->toBeNull()
        ->and($items[1]['url'])->toBeNull();
});

test('a product without a cover renders valid public props', function () {
    $product = storefrontProduct(['name' => 'No Cover LUT', 'slug' => 'no-cover-lut']);

    $props = $this->get(route('shop.show', $product->slug))->inertiaProps('product');

    expect($props['cover'])->toBeNull()
        ->and($props['media'])->toBe([]);
});

test('a product without examples renders successfully', function () {
    $product = storefrontProduct(['name' => 'No Examples LUT', 'slug' => 'no-examples-lut']);

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.examples', []));
});

test('public image URLs are generated only from the public disk', function () {
    $product = storefrontProduct();
    $media = ProductMedia::factory()->cover()->for($product)->create([
        'path' => 'products/media/public-only.jpg',
    ]);
    $media->forceFill(['disk' => 'private'])->saveQuietly();

    $cover = $this->get(route('shop.show', $product->slug))->inertiaProps('product.cover');

    expect($cover['url'])->toBe(Storage::disk('public')->url('products/media/public-only.jpg'));
});

test('public responses never contain a private ProductFile URL', function () {
    $product = storefrontProductWithFullDetail();

    $payload = json_encode($this->get(route('shop.show', $product->slug))->inertiaProps(), JSON_THROW_ON_ERROR);

    expect($payload)->not->toContain('/storage/products/releases/private-package-'.$product->id.'.zip');
});

test('product prices are formatted correctly', function () {
    $product = storefrontProduct(['price_cents' => 900]);

    expect($this->get(route('shop.show', $product->slug))->inertiaProps('product.formatted_price'))
        ->toBe('€9.00');
});

test('free products display as Free', function () {
    $product = storefrontProduct([
        'type' => ProductType::FreeLut,
        'price_cents' => 0,
    ]);

    expect($this->get(route('shop.show', $product->slug))->inertiaProps('product.formatted_price'))
        ->toBe('Free');
});
