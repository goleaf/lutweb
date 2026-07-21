<script setup lang="ts">
import ProductCard from '@/components/storefront/ProductCard.vue';
import type { PublicProductCard } from '@/types/storefront';

defineProps<{
    products: PublicProductCard[];
    emptyTitle?: string;
    emptyMessage?: string;
}>();
</script>

<template>
    <div
        v-if="products.length > 0"
        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
    >
        <ProductCard
            v-for="product in products"
            :key="product.id"
            :product="product"
        />
    </div>

    <div
        v-else
        role="status"
        class="rounded-lg border border-stone-200 bg-white px-6 py-10 text-center shadow-sm"
    >
        <h3 class="text-base font-semibold text-stone-950">
            {{ emptyTitle ?? 'No products found' }}
        </h3>
        <p class="mt-2 text-sm leading-6 text-stone-600">
            {{
                emptyMessage ??
                'The catalog does not have matching published LUTs yet.'
            }}
        </p>
    </div>
</template>
