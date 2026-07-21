<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\LutTestUpload;
use App\Models\PayPalWebhookEvent;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class LocalDemoOperationsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureLocalOrTesting();

        if (! Product::query()->where('slug', 'demo-cinematic-portrait')->exists()) {
            $this->call(LocalDemoProductSeeder::class);
        }

        $actor = $this->demoUser(LocalDemoUserSeeder::AdminEmail);
        $customer = $this->demoUser(LocalDemoUserSeeder::CustomerEmail);
        $product = Product::query()->where('slug', 'demo-cinematic-portrait')->firstOrFail();
        $version = $product->currentVersion()->firstOrFail();
        $cube = $version->files()->where('kind', 'cube_33')->first();

        $this->auditEvent($actor, $product);
        $this->webhookEvent();
        $this->lutTestUpload($customer, $product, $version, $cube?->id);
    }

    private function auditEvent(User $actor, Product $product): AuditEvent
    {
        $event = AuditEvent::query()
            ->where('action', 'product.published')
            ->where('auditable_type', Product::class)
            ->where('auditable_id', (string) $product->id)
            ->first();

        if ($event instanceof AuditEvent) {
            return $event;
        }

        return AuditEvent::factory()
            ->for($actor, 'actor')
            ->for($product, 'auditable')
            ->create([
                'auditable_id' => (string) $product->id,
                'target_user_id' => null,
                'metadata' => [
                    'product_id' => $product->id,
                    'source' => 'local_demo',
                ],
            ]);
    }

    private function webhookEvent(): PayPalWebhookEvent
    {
        $event = PayPalWebhookEvent::query()
            ->where('paypal_event_id', 'WH-DEMO-LOCAL-SEED')
            ->first();

        if ($event instanceof PayPalWebhookEvent) {
            return $event;
        }

        return PayPalWebhookEvent::factory()->create([
            'paypal_event_id' => 'WH-DEMO-LOCAL-SEED',
            'transmission_id' => (string) Str::uuid(),
        ]);
    }

    private function lutTestUpload(User $user, Product $product, ProductVersion $version, ?int $productFileId): LutTestUpload
    {
        $upload = LutTestUpload::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($upload instanceof LutTestUpload) {
            return $upload;
        }

        return LutTestUpload::factory()
            ->ready()
            ->for($user)
            ->for($product)
            ->create([
                'product_version_id' => $version->id,
                'product_file_id' => $productFileId,
                'raw_path' => 'lut-tests/demo/'.$user->id.'/raw/photo.jpg',
                'normalized_path' => 'lut-tests/demo/'.$user->id.'/normalized/photo.png',
                'before_preview_path' => 'lut-tests/demo/'.$user->id.'/before.webp',
                'after_preview_path' => 'lut-tests/demo/'.$user->id.'/after.webp',
            ]);
    }

    private function demoUser(string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            return $user;
        }

        $this->call(LocalDemoUserSeeder::class);

        return User::query()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function ensureLocalOrTesting(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo operational data may only be seeded in local or testing environments.');
        }
    }
}
