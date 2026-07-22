<?php

namespace Database\Seeders;

use App\Actions\Storefront\GenerateStorefrontPreviewCover;
use App\Models\Product;
use App\Support\Storefront\StorefrontPreviewCatalog;
use Illuminate\Database\Seeder;

class StorefrontPreviewMediaSeeder extends Seeder
{
    public function __construct(
        private readonly StorefrontPreviewCatalog $catalog,
        private readonly GenerateStorefrontPreviewCover $generateCover,
    ) {}

    public function run(): void
    {
        $this->call(StorefrontPreviewSeeder::class);

        $entries = $this->catalog->entries();
        $products = Product::query()
            ->whereIn('sku', collect($entries)->pluck('attributes.sku'))
            ->get()
            ->keyBy('sku');

        foreach ($entries as $index => $entry) {
            $product = $products->get($entry['attributes']['sku']);

            if (! $product instanceof Product) {
                continue;
            }

            $this->generateCover->handle($product, $entry);

            if (($index + 1) % 25 === 0) {
                $this->command->line('Generated preview covers: '.($index + 1).'/'.count($entries));
            }
        }
    }
}
