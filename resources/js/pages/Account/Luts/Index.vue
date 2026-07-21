<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AccountLayout from '@/layouts/AccountLayout.vue';

type CatalogLut = {
    id: string;
    product_name: string;
    product_type: string | null;
    product_version: string | null;
    order_number: string | null;
    purchase_date: string | null;
    status: string;
    status_label: string;
    cover: { url: string; alt_text: string | null } | null;
    download_url: string | null;
    message: string | null;
};

type EntitlementPage = {
    data: CatalogLut[];
    meta: {
        prev_page_url?: string | null;
        next_page_url?: string | null;
        total?: number;
    };
};

defineProps<{
    entitlements: EntitlementPage;
}>();
</script>

<template>
    <AccountLayout title="My LUTs">
        <section class="space-y-5">
            <div>
                <p class="text-sm font-medium text-teal-800">Library</p>
                <h1 class="mt-1 text-2xl font-semibold text-stone-950">
                    My LUTs
                </h1>
            </div>

            <div v-if="entitlements.data.length > 0" class="grid gap-3">
                <article
                    v-for="entitlement in entitlements.data"
                    :key="entitlement.id"
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div class="flex min-w-0 items-center gap-4">
                            <img
                                v-if="entitlement.cover"
                                :src="entitlement.cover.url"
                                :alt="entitlement.cover.alt_text ?? ''"
                                class="size-16 rounded-md object-cover"
                            />
                            <span
                                v-else
                                class="size-16 rounded-md bg-stone-200"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <h2
                                    class="truncate text-base font-semibold text-stone-950"
                                >
                                    {{ entitlement.product_name }}
                                </h2>
                                <p class="mt-1 text-sm text-stone-600">
                                    {{
                                        entitlement.product_version ?? 'Current'
                                    }}
                                    - {{ entitlement.status_label }}
                                </p>
                            </div>
                        </div>
                        <a
                            v-if="entitlement.download_url"
                            :href="entitlement.download_url"
                            class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Download ZIP
                        </a>
                        <span
                            v-else
                            class="rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-600"
                        >
                            {{ entitlement.message ?? 'Unavailable' }}
                        </span>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-lg border border-stone-200 bg-white p-6 text-sm text-stone-600"
            >
                Ready-made LUT purchases will appear here.
            </div>

            <nav
                v-if="
                    entitlements.meta.prev_page_url ||
                    entitlements.meta.next_page_url
                "
                aria-label="My LUTs pagination"
                class="flex justify-between gap-3"
            >
                <Link
                    v-if="entitlements.meta.prev_page_url"
                    :href="entitlements.meta.prev_page_url"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Previous
                </Link>
                <span v-else />
                <Link
                    v-if="entitlements.meta.next_page_url"
                    :href="entitlements.meta.next_page_url"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Next
                </Link>
            </nav>
        </section>
    </AccountLayout>
</template>
