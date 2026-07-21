<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import PaginationLinks from '@/components/storefront/PaginationLinks.vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import StorefrontFilterPanel from '@/components/storefront/StorefrontFilters.vue';
import StorefrontSeoHead from '@/components/storefront/StorefrontSeoHead.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { storefrontQuery } from '@/lib/storefront';
import { index as shopIndex } from '@/routes/shop';
import type {
    PaginatedResource,
    PublicProductCard,
    StorefrontFilterOptions,
    StorefrontFilters,
    StorefrontSeo,
} from '@/types/storefront';

const props = defineProps<{
    products: PaginatedResource<PublicProductCard>;
    resultCount: number;
    filters: StorefrontFilters;
    filterOptions: StorefrontFilterOptions;
    seo: StorefrontSeo;
}>();

const processing = ref(false);
const productItems = computed(() => props.products.data);

function visit(filters: StorefrontFilters): void {
    router.visit(
        shopIndex.url({
            query: storefrontQuery(filters, true),
        }),
        {
            method: 'get',
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function resetFilters(): void {
    router.visit(shopIndex.url(), {
        method: 'get',
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <PublicLayout>
        <StorefrontSeoHead :seo="seo" />

        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <SectionHeading
                    as="h1"
                    size="page"
                    icon="shop"
                    eyebrow="Shop"
                    title="Browse professional LUTs"
                    description="Filter the published catalog by style, software, type, and price. Checkout actions are marked coming soon in this milestone."
                />
            </div>
        </section>

        <section
            class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:px-8"
        >
            <aside>
                <StorefrontFilterPanel
                    :filters="filters"
                    :options="filterOptions"
                    :processing="processing"
                    @change="visit"
                    @reset="resetFilters"
                />
            </aside>

            <div class="grid gap-6">
                <div
                    class="flex flex-wrap items-center justify-between gap-3"
                    aria-live="polite"
                >
                    <h2
                        class="inline-flex items-center gap-2 text-lg font-semibold text-stone-950"
                    >
                        <AppIcon name="package" class="size-5 text-teal-800" />
                        {{ resultCount }} result{{
                            resultCount === 1 ? '' : 's'
                        }}
                    </h2>
                    <p
                        v-if="filters.q"
                        class="inline-flex items-center gap-2 text-sm text-stone-600"
                    >
                        <AppIcon name="search" class="size-4" />
                        Search: &ldquo;{{ filters.q }}&rdquo;
                    </p>
                </div>

                <ProductGrid
                    :products="productItems"
                    empty-title="No LUTs matched these filters."
                    empty-message="Try a broader search, choose a different category, or reset the filters."
                />

                <PaginationLinks :meta="products.meta" />
            </div>
        </section>
    </PublicLayout>
</template>
