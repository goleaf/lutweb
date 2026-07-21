<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';
import type { PublicProductDetail } from '@/types/storefront';

const props = defineProps<{
    product: PublicProductDetail;
    purchaseHref: string | null;
    purchaseLabel: string;
    purchaseMessage: string;
}>();

const purchaseIcon = computed<AppIconName>(() => {
    if (props.product.purchase.action === 'owned') {
        return 'folder';
    }

    if (props.product.purchase.action === 'claim') {
        return 'download';
    }

    return 'credit-card';
});
</script>

<template>
    <section class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700"
            >
                <AppIcon name="package" class="size-3.5" />
                {{ product.type_label }}
            </span>
            <span
                v-if="product.is_featured"
                class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"
            >
                <AppIcon name="star" class="size-3.5" />
                Featured
            </span>
        </div>
        <h1 class="mt-4 text-3xl font-semibold text-stone-950">
            {{ product.name }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-stone-600">
            {{ product.short_description }}
        </p>
        <p class="mt-5 text-2xl font-semibold text-stone-950">
            {{ product.formatted_price }}
        </p>
        <div class="mt-5 grid gap-2">
            <Link
                v-if="purchaseHref"
                :href="purchaseHref"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-teal-700 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon :name="purchaseIcon" class="size-4" />
                {{ purchaseLabel }}
            </Link>
            <button
                v-else
                type="button"
                disabled
                class="inline-flex items-center justify-center gap-2 rounded-md border border-stone-300 bg-stone-100 px-4 py-2.5 text-sm font-semibold text-stone-500"
            >
                <AppIcon name="alert-circle" class="size-4" />
                Unavailable
            </button>
            <p class="text-sm leading-6 text-stone-600">
                {{ purchaseMessage }}
            </p>
            <Link
                v-if="product.try_url"
                :href="product.try_url"
                class="inline-flex items-center justify-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-stone-800 hover:border-teal-700 hover:text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="image" class="size-4" />
                Try on Your Photo
            </Link>
            <button
                v-else
                type="button"
                disabled
                class="inline-flex items-center justify-center gap-2 rounded-md border border-stone-300 bg-stone-100 px-4 py-2.5 text-sm font-semibold text-stone-500"
            >
                <AppIcon name="image" class="size-4" />
                Try on Your Photo
            </button>
        </div>
    </section>
</template>
