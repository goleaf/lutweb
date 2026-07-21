<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AccountPagination from '@/components/account/AccountPagination.vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import AccountLayout from '@/layouts/AccountLayout.vue';

type OrderRow = {
    id: string;
    number: string;
    kind_label: string;
    name: string;
    version: string | null;
    amount: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    created_at: string | null;
    url: string;
};

type OrdersPage = {
    data: OrderRow[];
    meta: {
        prev_page_url?: string | null;
        next_page_url?: string | null;
        total?: number;
    };
};

defineProps<{
    orders: OrdersPage;
}>();
</script>

<template>
    <AccountLayout title="Orders">
        <section class="space-y-5">
            <SectionHeading
                as="h1"
                icon="receipt"
                eyebrow="Purchases"
                title="Orders"
            />

            <div
                v-if="orders.data.length > 0"
                class="overflow-hidden rounded-lg border border-stone-200 bg-white"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-stone-200 bg-stone-50 text-xs font-semibold text-stone-600 uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-4 py-3">Order</th>
                            <th scope="col" class="px-4 py-3">Item</th>
                            <th scope="col" class="px-4 py-3">Type</th>
                            <th scope="col" class="px-4 py-3">Amount</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        <tr v-for="order in orders.data" :key="order.id">
                            <td class="px-4 py-3 font-medium text-stone-950">
                                {{ order.number }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                <span class="block font-medium text-stone-950">
                                    {{ order.name }}
                                </span>
                                <span class="text-xs text-stone-500">
                                    {{ order.version ?? 'Version' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ order.kind_label }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ order.amount }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ order.payment_status }} /
                                {{ order.fulfillment_status }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="order.url"
                                    class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                                >
                                    View
                                    <AppIcon
                                        name="arrow-right"
                                        class="size-4"
                                    />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                icon="receipt"
                title="No orders yet."
                message="Orders will appear here after checkout."
            />

            <AccountPagination
                :prev-page-url="orders.meta.prev_page_url"
                :next-page-url="orders.meta.next_page_url"
                label="Orders pagination"
            />
        </section>
    </AccountLayout>
</template>
