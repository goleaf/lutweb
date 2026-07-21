<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import { show as categoryShow } from '@/routes/categories';
import type { PublicProductDetail } from '@/types/storefront';

defineProps<{
    product: PublicProductDetail;
}>();
</script>

<template>
    <div class="grid gap-5">
        <section class="rounded-lg border border-stone-200 bg-white p-5">
            <h2
                class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
            >
                <AppIcon name="monitor" class="size-4 text-teal-800" />
                Compatible software
            </h2>
            <ul
                v-if="product.compatible_software.length > 0"
                class="mt-3 grid gap-2 text-sm text-stone-700"
            >
                <li
                    v-for="software in product.compatible_software"
                    :key="software.id"
                >
                    {{ software.name }}
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-stone-600">
                Compatibility details are being prepared.
            </p>
        </section>

        <section class="rounded-lg border border-stone-200 bg-white p-5">
            <h2
                class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
            >
                <AppIcon name="tag" class="size-4 text-teal-800" />
                Categories and tags
            </h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <Link
                    v-for="category in product.categories"
                    :key="category.id"
                    :href="categoryShow(category.slug)"
                    class="inline-flex items-center gap-1.5 rounded-full border border-stone-200 px-2.5 py-1 text-xs font-medium text-stone-700 hover:border-teal-700 hover:text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="tag" class="size-3.5" />
                    {{ category.name }}
                </Link>
                <span
                    v-for="tag in product.tags"
                    :key="tag.id"
                    class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700"
                >
                    <AppIcon name="sparkles" class="size-3.5" />
                    {{ tag.name }}
                </span>
            </div>
        </section>
    </div>
</template>
