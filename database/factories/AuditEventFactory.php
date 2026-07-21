<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $metadata = [
            'product_id' => fake()->numberBetween(1, 999),
            'source' => 'local_demo',
        ];

        return [
            'actor_user_id' => User::factory(),
            'action' => 'product.published',
            'auditable_type' => Product::class,
            'auditable_id' => Product::factory(),
            'target_user_id' => null,
            'request_id' => (string) Str::uuid(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Local demo seeder',
            'metadata' => $metadata,
            'occurred_at' => now(),
        ];
    }
}
