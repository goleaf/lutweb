<script setup lang="ts">
import ProductCard from '@/components/storefront/ProductCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
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

    <EmptyState
        v-else
        icon="search"
        :title="emptyTitle ?? 'No products found'"
        :message="
            emptyMessage ??
            'The catalog does not have matching published LUTs yet.'
        "
    />
</template>
