<?php

namespace Database\Seeders;

use App\Actions\Storefront\GenerateStorefrontPreviewCover;
use App\Actions\Storefront\GenerateStorefrontPreviewExample;
use App\Actions\Storefront\GenerateStorefrontPreviewPackage;
use App\Models\Product;
use App\Support\Storefront\StorefrontPreviewCatalog;
use Illuminate\Database\Seeder;

class StorefrontPreviewMediaSeeder extends Seeder
{
    public function __construct(
        private readonly StorefrontPreviewCatalog $catalog,
        private readonly GenerateStorefrontPreviewCover $generateCover,
        private readonly GenerateStorefrontPreviewPackage $generatePackage,
        private readonly GenerateStorefrontPreviewExample $generateExample,
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
            $this->generatePackage->handle($product, $entry);
            $this->generateExample->handle($product, $entry);

            if (($index + 1) % 25 === 0) {
                $this->command->line('Generated preview covers, packages, and examples: '.($index + 1).'/'.count($entries));
            }
        }
    }
}
