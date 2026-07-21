<?php

namespace Database\Factories;

use App\Enums\DigitalAssetKind;
use App\Enums\DownloadStatus;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadEvent>
 */
class DownloadEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entitlement_id' => Entitlement::factory(),
            'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_version_id' => ProductVersion::factory(),
            'product_file_id' => ProductFile::factory()->packageZip(),
            'wizard_project_id' => null,
            'custom_lut_build_id' => null,
            'custom_lut_build_file_id' => null,
            'item_display_name_snapshot' => fake()->words(3, true),
            'item_version_snapshot' => '1.0.0',
            'status' => DownloadStatus::Started,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'started_at' => now(),
            'completed_at' => null,
            'failed_at' => null,
            'size_bytes' => null,
        ];
    }
}
