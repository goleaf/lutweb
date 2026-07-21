<script setup lang="ts">
import AccountPagination from '@/components/account/AccountPagination.vue';
import AppIcon from '@/components/AppIcon.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
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
            <SectionHeading
                as="h1"
                icon="folder"
                eyebrow="Library"
                title="My LUTs"
            />

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
                                class="grid size-16 shrink-0 place-items-center rounded-md bg-stone-200"
                                aria-hidden="true"
                            >
                                <AppIcon
                                    name="image"
                                    class="size-6 text-stone-500"
                                />
                            </span>
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
                            {{ entitlement.message ?? 'Unavailable' }}
                        </span>
                    </div>
                </article>
            </div>

            <EmptyState
                v-else
                icon="folder"
                title="No LUTs in your library yet."
                message="Ready-made LUT purchases will appear here."
            />

            <AccountPagination
                :prev-page-url="entitlements.meta.prev_page_url"
                :next-page-url="entitlements.meta.next_page_url"
                label="My LUTs pagination"
            />
        </section>
    </AccountLayout>
</template>
