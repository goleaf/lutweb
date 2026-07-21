<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AccountPagination from '@/components/account/AccountPagination.vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import AccountLayout from '@/layouts/AccountLayout.vue';
import type { CustomLutPurchasedItem } from '@/types/commerce';

type PurchasePaginator = {
    data: CustomLutPurchasedItem[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
};

defineProps<{
    purchases: PurchasePaginator;
}>();

function packageSize(bytes: number | null): string {
    if (bytes === null || bytes <= 0) {
        return 'Package size unavailable';
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <AccountLayout title="Purchased Custom LUTs">
        <section class="space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading
                    as="h1"
                    icon="wand"
                    eyebrow="Custom LUTs"
                    title="Purchased"
                />
                <div
                    class="flex rounded-md border border-stone-200 bg-white p-1 text-sm"
                >
                    <Link
                        href="/account/custom-luts"
                        class="inline-flex items-center gap-1.5 rounded-sm px-3 py-1.5 font-medium text-stone-700 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="folder" class="size-3.5" />
                        Drafts
                    </Link>
                    <Link
                        href="/account/custom-luts/purchased"
                        class="inline-flex items-center gap-1.5 rounded-sm bg-stone-950 px-3 py-1.5 font-medium text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="download" class="size-3.5" />
                        Purchased
                    </Link>
                </div>
            </div>

            <div v-if="purchases.data.length > 0" class="grid gap-3">
                <article
                    v-for="purchase in purchases.data"
                    :key="purchase.id"
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                        <div>
                            <p
                                class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-teal-800 uppercase"
                            >
                                <AppIcon name="check-circle" class="size-3.5" />
                                {{ purchase.status_label }}
                            </p>
                            <h2
                                class="mt-1 text-base font-semibold text-stone-950"
                            >
                                {{ purchase.name }}
                            </h2>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ purchase.style_name || 'Neutral' }} -
                                {{ purchase.version_label ?? 'Build' }} -
                                {{ purchase.transform_version ?? 'Transform' }}
                            </p>
                            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                <div>
                                    <dt class="text-stone-500">Order</dt>
                                    <dd class="font-medium text-stone-900">
                                        {{ purchase.order_number ?? 'Unknown' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-stone-500">Parameters</dt>
                                    <dd class="font-medium text-stone-900">
                                        {{ purchase.parameter_hash }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-stone-500">Package</dt>
                                    <dd class="font-medium text-stone-900">
                                        {{
                                            packageSize(
                                                purchase.package_size_bytes,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div
                            class="flex flex-wrap items-start gap-2 lg:justify-end"
                        >
                            <a
                                v-if="purchase.download_url"
                                :href="purchase.download_url"
                                class="inline-flex items-center gap-2 rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="download" class="size-4" />
                                Download ZIP
                            </a>
                            <span
                                v-else
                                class="inline-flex items-center gap-2 rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-600"
                            >
                                <AppIcon name="alert-circle" class="size-4" />
                                Download unavailable
                            </span>
                            <Link
                                :href="purchase.show_url"
                                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="receipt" class="size-4" />
                                Details
                            </Link>
                            <Link
                                v-if="purchase.order_url"
                                :href="purchase.order_url"
                                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="receipt" class="size-4" />
                                View Order
                            </Link>
                            <Link
                                v-if="purchase.project_url"
                                :href="purchase.project_url"
                                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            >
                                <AppIcon name="edit" class="size-4" />
                                Continue Editing
                            </Link>
                            <span
                                v-else
                                class="inline-flex items-center gap-2 rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-600"
                            >
                                <AppIcon name="trash" class="size-4" />
                                Project Deleted
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                icon="download"
                title="No purchased Custom LUTs yet."
                message="Purchased Custom LUT packages will appear here."
            />

            <AccountPagination
                :prev-page-url="purchases.prev_page_url"
                :next-page-url="purchases.next_page_url"
                label="Purchased Custom LUTs pagination"
            />
        </section>
    </AccountLayout>
</template>
