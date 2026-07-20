<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PaginationLinks from '@/components/storefront/PaginationLinks.vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import StorefrontFilterPanel from '@/components/storefront/StorefrontFilters.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { storefrontQuery } from '@/lib/storefront';
import { show as categoryShow } from '@/routes/categories';
import { index as shopIndex } from '@/routes/shop';
import type {
    PaginatedResource,
    PublicCategory,
    PublicProductCard,
    StorefrontFilterOptions,
    StorefrontFilters,
    StorefrontSeo,
} from '@/types/storefront';

const props = defineProps<{
    category: PublicCategory;
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
        categoryShow.url(props.category.slug, {
            query: storefrontQuery(filters, false),
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
    router.visit(categoryShow.url(props.category.slug), {
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
                <nav aria-label="Breadcrumbs" class="text-sm text-stone-600">
                    <Link
                        :href="shopIndex()"
                        class="rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                    >
                        Shop
                    </Link>
                    <span aria-hidden="true" class="mx-2">/</span>
                    <span>{{ category.name }}</span>
                </nav>

                <p class="mt-6 text-sm font-semibold text-teal-800">Category</p>
                <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                    {{ category.name }} LUTs
                </h1>
                <p
                    v-if="category.description"
                    class="mt-3 max-w-2xl text-sm leading-6 whitespace-pre-line text-stone-600"
                >
                    {{ category.description }}
                </p>
                <p class="mt-3 text-sm text-stone-600" aria-live="polite">
                    {{ resultCount }} published product{{
                        resultCount === 1 ? '' : 's'
                    }}
                    in this category.
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
                    :fixed-category="category"
                    :processing="processing"
                    @change="visit"
                    @reset="resetFilters"
                />
            </aside>

            <div class="grid gap-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-stone-950">
                        {{ category.name }} catalog
                    </h2>
                    <Link
                        :href="shopIndex()"
                        class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Full shop
                    </Link>
                </div>

                <ProductGrid
                    :products="productItems"
                    empty-title="No published LUTs in this category yet."
                    empty-message="Try resetting filters or browse the full shop for other looks."
                />

                <PaginationLinks :meta="products.meta" />
            </div>
        </section>
    </PublicLayout>
</template>
