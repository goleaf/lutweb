<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CompatibleSoftware;
use App\Models\Product;
use App\Models\Tag;
use App\Support\Storefront\StorefrontPreviewCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StorefrontPreviewSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(private readonly StorefrontPreviewCatalog $catalog) {}

    /**
     * Seed a minimal public catalog without accounts, orders, or fake files.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $entries = $this->catalog->entries();
        /** @var array<string, int> $categoryIds */
        $categoryIds = Category::query()
            ->whereIn('slug', collect($entries)->pluck('category_slugs')->flatten()->unique())
            ->pluck('id', 'slug')
            ->all();
        /** @var array<string, int> $tagIds */
        $tagIds = Tag::query()
            ->whereIn('slug', collect($entries)->pluck('tag_slugs')->flatten()->unique())
            ->pluck('id', 'slug')
            ->all();
        $softwareIds = CompatibleSoftware::query()
            ->whereIn('slug', [
                'adobe-photoshop',
                'adobe-premiere-pro',
                'affinity-photo',
                'davinci-resolve',
                'final-cut-pro',
            ])
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($categoryIds, $entries, $softwareIds, $tagIds): void {
            $previewSkus = collect($entries)->pluck('attributes.sku')->all();

            Product::query()
                ->where('sku', 'like', 'PREVIEW-%')
                ->whereNotIn('sku', $previewSkus)
                ->delete();

            foreach ($entries as $entry) {
                $attributes = $entry['attributes'];
                $sku = $attributes['sku'];
                unset($attributes['sku']);

                $product = Product::query()
                    ->withTrashed()
                    ->updateOrCreate(['sku' => $sku], $attributes);

                if ($product->trashed()) {
                    $product->restore();
                }

                $product->categories()->sync(
                    collect($entry['category_slugs'])->map(fn (string $slug): int => $categoryIds[$slug]),
                );
                $product->tags()->sync(
                    collect($entry['tag_slugs'])->map(fn (string $slug): int => $tagIds[$slug]),
                );
                $product->compatibleSoftware()->sync($softwareIds);
            }
        });

        $this->command->warn(
            'Storefront preview products contain no downloadable files and must be replaced before accepting real orders.',
        );
    }
}
