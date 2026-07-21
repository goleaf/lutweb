<?php

namespace Database\Factories;

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
            'product_id' => Product::factory(),
            'product_version_id' => ProductVersion::factory(),
            'product_file_id' => ProductFile::factory()->packageZip(),
            'product_name' => fake()->words(3, true),
            'product_slug' => fake()->slug(),
            'product_type' => ProductType::SingleLut->value,
            'product_sku' => fake()->optional()->bothify('LUT-####'),
            'product_version' => '1.0.0',
            'unit_price_cents' => 1999,
            'quantity' => 1,
            'total_cents' => 1999,
        ];
    }
}
