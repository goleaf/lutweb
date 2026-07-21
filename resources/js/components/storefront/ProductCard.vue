<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import ResponsivePicture from '@/components/storefront/ResponsivePicture.vue';
import { show as productShow } from '@/routes/shop';
import type { PublicProductCard } from '@/types/storefront';

withDefaults(
    defineProps<{
        product: PublicProductCard;
        loading?: 'eager' | 'lazy';
    }>(),
    {
        loading: 'lazy',
    },
);
</script>

<template>
    <article
        class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm transition hover:border-stone-300"
    >
        <Link :href="productShow(product.slug)" class="block">
            <ResponsivePicture
                v-if="product.cover?.image"
                :image="product.cover.image"
                sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"
                class="aspect-[4/3] w-full object-cover"
                :loading="loading"
                :fetchpriority="loading === 'eager' ? 'high' : 'auto'"
            />
            <img
                v-else-if="product.cover?.url"
                :src="product.cover.url"
                :alt="product.cover.alt_text"
                :width="product.cover.width ?? undefined"
                :height="product.cover.height ?? undefined"
                class="aspect-[4/3] w-full object-cover"
                :loading="loading"
                decoding="async"
            />
            <div
                v-else
                class="grid aspect-[4/3] content-center bg-stone-900 px-6 text-center text-sm font-medium text-stone-100"
            >
                LUT Web
            </div>
        </Link>

        <div class="space-y-4 p-4">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-md bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-900"
                    >
                        {{ product.type_label }}
                    </span>
                    <span
                        v-if="product.is_featured"
                        class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900"
                    >
                        Featured
                    </span>
                </div>

                <h3 class="text-base font-semibold text-stone-950">
                    <Link
                        :href="productShow(product.slug)"
                        class="rounded-sm underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                    >
                        {{ product.name }}
                    </Link>
                </h3>

                <p class="line-clamp-2 text-sm leading-6 text-stone-600">
                    {{ product.short_description }}
                </p>

                <div
                    v-if="product.categories.length > 0"
                    class="flex flex-wrap gap-2"
                    aria-label="Product categories"
                >
                    <span
                        v-for="category in product.categories.slice(0, 2)"
                        :key="category.id"
                        class="rounded-full border border-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600"
                    >
                        {{ category.name }}
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-stone-950">
                    {{ product.formatted_price }}
                </span>
                <Link
                    :href="productShow(product.slug)"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    View LUT
                </Link>
            </div>
        </div>
    </article>
</template>
