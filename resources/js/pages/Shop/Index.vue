<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PaginationLinks from '@/components/storefront/PaginationLinks.vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import StorefrontFilterPanel from '@/components/storefront/StorefrontFilters.vue';
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
        <Head :title="seo.title">
            <meta name="description" :content="seo.description" />
            <link rel="canonical" :href="seo.canonical_url" />
            <meta property="og:title" :content="seo.title" />
            <meta property="og:description" :content="seo.description" />
            <meta property="og:type" content="website" />
            <meta name="twitter:card" content="summary" />
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <p class="text-sm font-semibold text-teal-800">Shop</p>
                <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                    Browse professional LUTs
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">
                    Filter the published catalog by style, software, type, and
                    price. Checkout actions are marked coming soon in this
                    milestone.
                </p>
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
                    <h2 class="text-lg font-semibold text-stone-950">
                        {{ resultCount }} result{{
                            resultCount === 1 ? '' : 's'
                        }}
                    </h2>
                    <p v-if="filters.q" class="text-sm text-stone-600">
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
