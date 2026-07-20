<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import ProductCard from '@/components/storefront/ProductCard.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { collectionItems } from '@/lib/storefront';
import { show as categoryShow } from '@/routes/categories';
import { index as shopIndex } from '@/routes/shop';
import type {
    PublicCategory,
    PublicProductCard,
    ResourceCollection,
    StorefrontSeo,
} from '@/types/storefront';

const props = defineProps<{
    featuredProducts: ResourceCollection<PublicProductCard>;
    categories: ResourceCollection<PublicCategory>;
    freeProducts: ResourceCollection<PublicProductCard>;
    seo: StorefrontSeo;
}>();

const featuredProducts = computed(() =>
    collectionItems(props.featuredProducts),
);
const freeProducts = computed(() => collectionItems(props.freeProducts));
const categories = computed(() =>
    collectionItems(props.categories).slice(0, 10),
);
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
            <div
                class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[minmax(0,1fr)_24rem] lg:px-8 lg:py-20"
            >
                <div class="flex flex-col justify-center">
                    <p
                        class="text-sm font-semibold tracking-wide text-teal-800 uppercase"
                    >
                        LUT Web
                    </p>
                    <h1
                        class="mt-5 max-w-3xl text-4xl font-semibold tracking-normal text-stone-950 sm:text-5xl"
                    >
                        Professional LUTs for photographers and creators.
                    </h1>
                    <p
                        class="mt-5 max-w-2xl text-base leading-7 text-stone-700 sm:text-lg"
                    >
                        Try looks on your photos, create custom LUTs, and
                        securely download your purchases.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="shopIndex()"
                            class="rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Explore LUTs
                        </Link>
                        <button
                            type="button"
                            disabled
                            class="rounded-md border border-stone-300 bg-stone-100 px-4 py-2.5 text-sm font-semibold text-stone-500"
                        >
                            Create Your LUT
                            <span class="ml-2 text-xs text-amber-800">
                                Coming soon
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    class="grid content-center gap-4"
                    aria-label="Editorial LUT preview"
                >
                    <div
                        class="overflow-hidden rounded-lg border border-stone-200 bg-stone-950"
                    >
                        <div class="grid aspect-[4/3] grid-cols-2">
                            <div
                                class="bg-[linear-gradient(160deg,#292524,#57534e_45%,#d6d3d1)]"
                                aria-label="Original preview tones"
                                role="img"
                            />
                            <div
                                class="bg-[linear-gradient(160deg,#0f172a,#0f766e_45%,#f59e0b)]"
                                aria-label="LUT preview tones"
                                role="img"
                            />
                        </div>
                        <div
                            class="grid grid-cols-6 gap-1 p-4"
                            aria-hidden="true"
                        >
                            <span class="h-2 rounded-full bg-stone-500" />
                            <span class="h-2 rounded-full bg-teal-600" />
                            <span class="h-2 rounded-full bg-amber-500" />
                            <span class="h-2 rounded-full bg-rose-500" />
                            <span class="h-2 rounded-full bg-sky-600" />
                            <span class="h-2 rounded-full bg-stone-300" />
                        </div>
                    </div>
                    <p class="text-sm leading-6 text-stone-600">
                        This milestone opens public browsing. Checkout,
                        downloads, uploads, and the custom LUT wizard arrive in
                        later milestones.
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-stone-950">
                        Featured LUTs
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-600">
                        Browse published looks prepared for the marketplace
                        catalog.
                    </p>
                </div>
                <Link
                    :href="shopIndex()"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    View all
                </Link>
            </div>

            <div
                v-if="featuredProducts.length > 0"
                class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="(product, index) in featuredProducts"
                    :key="product.id"
                    :product="product"
                    :loading="index < 2 ? 'eager' : 'lazy'"
                />
            </div>
            <div
                v-else
                class="mt-6 rounded-lg border border-dashed border-stone-300 bg-white px-5 py-10 text-center"
                role="status"
            >
                <h3 class="text-lg font-semibold text-stone-950">
                    Featured LUTs are coming soon.
                </h3>
                <p class="mt-2 text-sm text-stone-600">
                    Published featured products will appear here automatically.
                </p>
            </div>
        </section>

        <section class="border-y border-stone-200 bg-white">
            <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-semibold text-stone-950">
                    Browse by category
                </h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="categoryShow(category.slug)"
                        class="rounded-lg border border-stone-200 bg-stone-50 p-4 hover:border-teal-700 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <span class="font-semibold text-stone-950">
                            {{ category.name }}
                        </span>
                        <span class="mt-2 block text-sm text-stone-600">
                            {{ category.products_count }} published
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-semibold text-stone-950">How it works</h2>
            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <article
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <p class="text-sm font-semibold text-teal-800">01</p>
                    <h3 class="mt-3 text-lg font-semibold text-stone-950">
                        Choose a look.
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Filter the public catalog by style, software, product
                        type, and price.
                    </p>
                </article>
                <article
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <p class="text-sm font-semibold text-teal-800">02</p>
                    <h3 class="mt-3 text-lg font-semibold text-stone-950">
                        Compare before and after.
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Product pages can show accessible comparisons prepared
                        by the catalog team.
                    </p>
                </article>
                <article
                    class="rounded-lg border border-stone-200 bg-white p-5"
                >
                    <p class="text-sm font-semibold text-teal-800">03</p>
                    <h3 class="mt-3 text-lg font-semibold text-stone-950">
                        Purchase and download securely.
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Checkout and customer downloads will be enabled in a
                        later milestone.
                    </p>
                </article>
            </div>
        </section>

        <section class="border-y border-stone-200 bg-stone-100">
            <div
                class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-12 sm:px-6 md:grid-cols-[1fr_auto] lg:px-8"
            >
                <div>
                    <h2 class="text-2xl font-semibold text-stone-950">
                        Custom LUT wizard
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-600">
                        A guided workflow for custom LUT creation is planned,
                        but it is not available in this read-only storefront
                        milestone.
                    </p>
                </div>
                <button
                    type="button"
                    disabled
                    class="h-fit rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-500"
                >
                    Coming soon
                </button>
            </div>
        </section>

        <section
            v-if="freeProducts.length > 0"
            class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8"
        >
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold text-stone-950">
                        Free LUTs
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Published free LUT products appear here when available.
                    </p>
                </div>
                <Link
                    :href="shopIndex({ query: { pricing: 'free' } })"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Browse free LUTs
                </Link>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <ProductCard
                    v-for="product in freeProducts"
                    :key="product.id"
                    :product="product"
                />
            </div>
        </section>
    </PublicLayout>
</template>
