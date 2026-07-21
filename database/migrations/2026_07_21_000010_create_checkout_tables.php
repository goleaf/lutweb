<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->string('payment_status')->index();
            $table->string('fulfillment_status')->index();
            $table->char('currency', 3)->default('EUR');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->uuid('checkout_idempotency_key');
            $table->string('customer_name');
            $table->text('customer_email');
            $table->char('customer_country_code', 2)->nullable();
            $table->timestamp('terms_of_sale_accepted_at');
            $table->timestamp('license_accepted_at');
            $table->timestamp('digital_delivery_consent_at');
            $table->string('terms_of_sale_version');
            $table->string('license_version');
            $table->string('refund_policy_version');
            $table->string('digital_delivery_consent_version');
            $table->text('acceptance_ip_address')->nullable();
            $table->text('acceptance_user_agent')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checkout_idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_slug');
            $table->string('product_type');
            $table->string('product_sku')->nullable();
            $table->string('product_version');
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('status')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('paypal_order_id')->nullable()->unique();
            $table->string('paypal_capture_id')->nullable()->unique();
            $table->uuid('create_request_id')->unique();
            $table->uuid('capture_request_id')->nullable()->unique();
            $table->string('payer_id')->nullable();
            $table->text('payer_email')->nullable();
            $table->char('payer_country_code', 2)->nullable();
            $table->string('payee_merchant_id')->nullable();
            $table->unsignedBigInteger('paypal_fee_cents')->nullable();
            $table->unsignedBigInteger('net_amount_cents')->nullable();
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->string('provider_debug_id')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('download_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('entitlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->text('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['entitlement_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('paypal_webhook_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('paypal_event_id')->unique();
            $table->string('event_type')->index();
            $table->string('resource_type')->nullable();
            $table->string('transmission_id')->nullable();
            $table->timestamp('transmission_time')->nullable();
            $table->string('verification_status')->index();
            $table->string('processing_status')->index();
            $table->char('payload_sha256', 64);
            $table->longText('encrypted_payload')->nullable();
            $table->unsignedInteger('processing_attempts')->default(0);
            $table->string('failure_code')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('payload_purged_at')->nullable();
            $table->timestamps();

            $table->index(['processing_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');
        Schema::dropIfExists('download_events');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
