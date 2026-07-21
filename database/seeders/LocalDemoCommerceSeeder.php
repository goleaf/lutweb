<?php

namespace Database\Seeders;

use App\Enums\DigitalAssetKind;
use App\Models\DownloadEvent;
use App\Models\Entitlement;
use App\Models\NotificationDispatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalDemoCommerceSeeder extends Seeder
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

        $user = $this->demoUser();
        $product = Product::query()->where('slug', 'demo-cinematic-portrait')->firstOrFail();
        $version = $product->currentVersion()->firstOrFail();
        $package = $version->files()->where('kind', 'package_zip')->firstOrFail();

        $order = $this->order($user);
        $item = $this->item($order, $product, $version, $package);
        $this->payment($order);
        $entitlement = $this->entitlement($user, $order, $item, $product, $version, $package);
        $this->downloadEvent($user, $order, $entitlement, $product, $version, $package);
        $this->notificationDispatch($user, $order);
    }

    private function order(User $user): Order
    {
        $order = Order::query()->where('number', 'ORD-DEMO-CATALOG')->first();

        if ($order instanceof Order) {
            return $order;
        }

        return Order::factory()
            ->completed()
            ->for($user)
            ->create([
                'number' => 'ORD-DEMO-CATALOG',
                'checkout_idempotency_key' => 'local-demo-catalog-order',
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_country_code' => $user->country_code,
            ]);
    }

    private function item(Order $order, Product $product, ProductVersion $version, ProductFile $package): OrderItem
    {
        $item = OrderItem::query()->where('order_id', $order->id)->first();

        if ($item instanceof OrderItem) {
            return $item;
        }

        return OrderItem::factory()
            ->for($order)
            ->create([
                'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
                'product_id' => $product->id,
                'product_version_id' => $version->id,
                'product_file_id' => $package->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_type' => $product->type->value,
                'product_sku' => $product->sku,
                'product_version' => $version->version,
                'unit_price_cents' => $product->price_cents,
                'total_cents' => $product->price_cents,
            ]);
    }

    private function payment(Order $order): Payment
    {
        $payment = Payment::query()->where('order_id', $order->id)->first();

        if ($payment instanceof Payment) {
            return $payment;
        }

        return Payment::factory()
            ->completed()
            ->for($order)
            ->create([
                'amount_cents' => $order->total_cents,
                'currency' => $order->currency,
                'paypal_order_id' => 'DEMO-PAYPAL-ORDER',
                'paypal_capture_id' => 'DEMO-PAYPAL-CAPTURE',
                'payer_email' => 'demo-payer@example.test',
                'payee_merchant_id' => null,
            ]);
    }

    private function entitlement(
        User $user,
        Order $order,
        OrderItem $item,
        Product $product,
        ProductVersion $version,
        ProductFile $package,
    ): Entitlement {
        $entitlement = Entitlement::query()->where('order_item_id', $item->id)->first();

        if ($entitlement instanceof Entitlement) {
            return $entitlement;
        }

        return Entitlement::factory()
            ->for($user)
            ->for($order)
            ->for($item, 'orderItem')
            ->for($product)
            ->for($version, 'productVersion')
            ->for($package, 'productFile')
            ->create([
                'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
            ]);
    }

    private function downloadEvent(
        User $user,
        Order $order,
        Entitlement $entitlement,
        Product $product,
        ProductVersion $version,
        ProductFile $package,
    ): DownloadEvent {
        $event = DownloadEvent::query()->where('entitlement_id', $entitlement->id)->first();

        if ($event instanceof DownloadEvent) {
            return $event;
        }

        return DownloadEvent::factory()
            ->for($entitlement)
            ->for($user)
            ->for($order)
            ->for($product)
            ->for($version, 'productVersion')
            ->for($package, 'productFile')
            ->create([
                'digital_asset_kind' => DigitalAssetKind::CatalogProduct,
                'item_display_name_snapshot' => $product->name,
                'item_version_snapshot' => $version->version,
                'completed_at' => now(),
                'size_bytes' => $package->size_bytes,
            ]);
    }

    private function notificationDispatch(User $user, Order $order): NotificationDispatch
    {
        $eventKey = 'order:'.$order->id.':payment-confirmed';
        $dispatch = NotificationDispatch::query()->where('event_key', $eventKey)->first();

        if ($dispatch instanceof NotificationDispatch) {
            return $dispatch;
        }

        return NotificationDispatch::factory()
            ->for($user)
            ->for($order, 'related')
            ->create([
                'event_key' => $eventKey,
            ]);
    }

    private function demoUser(): User
    {
        $user = User::query()->where('email', LocalDemoUserSeeder::CustomerEmail)->first();

        if ($user instanceof User) {
            return $user;
        }

        $this->call(LocalDemoUserSeeder::class);

        return User::query()
            ->where('email', LocalDemoUserSeeder::CustomerEmail)
            ->firstOrFail();
    }

    private function ensureLocalOrTesting(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Local demo commerce data may only be seeded in local or testing environments.');
        }
    }
}
