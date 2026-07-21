<?php

namespace Database\Factories;

use App\Enums\DigitalAssetKind;
use App\Enums\EntitlementStatus;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entitlement>
 */
class EntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_id' => Product::factory(),
            'product_version_id' => ProductVersion::factory(),
            'product_file_id' => ProductFile::factory()->packageZip(),
            'wizard_project_id' => null,
            'custom_lut_build_id' => null,
            'custom_lut_build_file_id' => null,
            'status' => EntitlementStatus::Active,
            'granted_at' => now(),
            'revoked_at' => null,
            'revoke_reason' => null,
            'restored_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EntitlementStatus::Revoked,
            'revoked_at' => now(),
            'revoke_reason' => 'Test revocation',
        ]);
    }
}
