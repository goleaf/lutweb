<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { show as productShow } from '@/routes/shop';
import type { PublicProductCard } from '@/types/storefront';

defineProps<{
    product: PublicProductCard;
}>();
</script>

<template>
    <article
        class="overflow-hidden rounded-lg border border-stone-200 bg-white shadow-sm transition hover:border-stone-300"
    >
        <Link :href="productShow(product.slug)" class="block">
            <img
                v-if="product.cover"
                :src="product.cover.url"
                :alt="product.cover.alt_text"
                class="aspect-[4/3] w-full object-cover"
                loading="lazy"
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
            </div>

            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-stone-950">
                    {{ product.formatted_price }}
                </span>
                <Link
                    :href="productShow(product.slug)"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    View
                </Link>
            </div>
        </div>
    </article>
</template>
