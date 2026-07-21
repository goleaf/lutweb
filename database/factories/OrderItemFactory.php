<?php

namespace Database\Factories;

use App\Enums\DigitalAssetKind;
use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
            'product_id' => Product::factory(),
            'product_version_id' => ProductVersion::factory(),
            'product_file_id' => ProductFile::factory()->packageZip(),
            'wizard_project_id' => null,
            'custom_lut_build_id' => null,
            'custom_lut_build_file_id' => null,
            'product_name' => fake()->words(3, true),
            'product_slug' => fake()->slug(),
            'product_type' => ProductType::SingleLut->value,
            'product_sku' => fake()->optional()->bothify('LUT-####'),
            'product_version' => '1.0.0',
            'custom_lut_build_fingerprint' => null,
            'custom_lut_parameters_hash' => null,
            'custom_lut_transform_version' => null,
            'custom_lut_generator_version' => null,
            'custom_lut_package_schema_version' => null,
            'custom_lut_package_sha256' => null,
            'custom_lut_package_size_bytes' => null,
            'custom_lut_style_name_snapshot' => null,
            'custom_lut_pricing_version' => null,
            'unit_price_cents' => 1999,
            'quantity' => 1,
            'total_cents' => 1999,
        ];
    }
}
