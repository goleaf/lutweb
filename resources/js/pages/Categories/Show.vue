<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import PaginationLinks from '@/components/storefront/PaginationLinks.vue';
import ProductGrid from '@/components/storefront/ProductGrid.vue';
import StorefrontFilterPanel from '@/components/storefront/StorefrontFilters.vue';
import StorefrontSeoHead from '@/components/storefront/StorefrontSeoHead.vue';
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
        <StorefrontSeoHead :seo="seo" />

        <section class="border-b border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <nav aria-label="Breadcrumbs" class="text-sm text-stone-600">
                    <Link
                        :href="shopIndex()"
                        class="inline-flex items-center gap-1.5 rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="shop" class="size-3.5" />
                        Shop
                    </Link>
                    <AppIcon
                        name="chevron-right"
                        class="mx-2 inline size-3.5 text-stone-400"
                        aria-hidden="true"
                    />
                    <span>{{ category.name }}</span>
                </nav>

                <p
                    class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-teal-800"
                >
                    <AppIcon name="tag" class="size-4" />
                    Category
                </p>
                <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                    {{ category.name }} LUTs
                </h1>
                <p
                    v-if="category.description"
                    class="mt-3 max-w-2xl text-sm leading-6 whitespace-pre-line text-stone-600"
                >
                    {{ category.description }}
                </p>
                <div
                    class="mt-4 flex flex-wrap gap-2 text-sm text-stone-600"
                    aria-live="polite"
                >
                    <p
                        class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5"
                    >
                        <AppIcon name="package" class="size-4 text-teal-800" />
                        {{ category.products_count ?? 0 }} published product{{
                            category.products_count === 1 ? '' : 's'
                        }}
                    </p>
                    <p
                        class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5"
                    >
                        <AppIcon name="search" class="size-4 text-teal-800" />
                        {{ resultCount }} result{{
                            resultCount === 1 ? '' : 's'
                        }}
                        shown
                    </p>
                </div>
            </div>
        </section>

        <section
            class="mx-auto grid w-full max-w-7xl items-start gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:px-8"
        >
            <aside
                :class="productItems.length === 0 ? 'order-2 lg:order-1' : ''"
            >
                <StorefrontFilterPanel
                    :filters="filters"
                    :options="filterOptions"
                    :fixed-category="category"
                    :processing="processing"
                    @change="visit"
                    @reset="resetFilters"
                />
            </aside>

            <div
                :class="[
                    'grid min-w-0 content-start gap-6',
                    productItems.length === 0 ? 'order-1 lg:order-2' : '',
                ]"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2
                        class="inline-flex items-center gap-2 text-lg font-semibold text-stone-950"
                    >
                        <AppIcon name="tag" class="size-5 text-teal-800" />
                        {{ category.name }} catalog
                    </h2>
                    <Link
                        :href="shopIndex()"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="shop" class="size-4" />
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
