<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import AccountLayout from '@/layouts/AccountLayout.vue';
import type { DigitalOrderItem } from '@/types/commerce';

type OrderDetail = {
    id: string;
    number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    currency: 'EUR';
    amount: string;
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    license_version: string;
    item: DigitalOrderItem | null;
    capture_url: string;
};

defineProps<{
    order: OrderDetail;
}>();
</script>

<template>
    <AccountLayout :title="`Order ${order.number}`">
        <section class="space-y-5">
            <div>
                <Link
                    href="/account/orders"
                    class="inline-flex items-center gap-1.5 rounded-sm text-sm text-stone-600 underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    <AppIcon name="arrow-left" class="size-3.5" />
                    Back to orders
                </Link>
                <h1
                    class="mt-3 inline-flex items-center gap-2 text-2xl font-semibold text-stone-950"
                >
                    <AppIcon name="receipt" class="size-5 text-teal-800" />
                    Order {{ order.number }}
                </h1>
            </div>

            <section class="rounded-lg border border-stone-200 bg-white p-5">
                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-stone-500">Amount</dt>
                        <dd class="font-medium text-stone-900">
                            {{ order.amount }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Order status</dt>
                        <dd class="font-medium text-stone-900">
                            {{ order.status }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Payment status</dt>
                        <dd class="font-medium text-stone-900">
                            {{ order.payment_status }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Fulfillment status</dt>
                        <dd class="font-medium text-stone-900">
                            {{ order.fulfillment_status }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">License version</dt>
                        <dd class="font-medium text-stone-900">
                            {{ order.license_version }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border border-stone-200 bg-white p-5">
                <h2
                    class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
                >
                    <AppIcon name="package" class="size-4 text-teal-800" />
                    Item
                </h2>
                <div v-if="order.item" class="mt-3 text-sm text-stone-700">
                    <p class="font-medium text-stone-950">
                        {{ order.item.name }}
                    </p>
                    <p class="mt-1">
                        {{ order.item.kind_label }} -
                        {{ order.item.version ?? 'Version' }}
                    </p>
                </div>
                <p v-else class="mt-3 text-sm text-stone-600">
                    The order item snapshot is unavailable.
                </p>
            </section>
        </section>
    </AccountLayout>
</template>
