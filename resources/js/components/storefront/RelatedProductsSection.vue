<script setup lang="ts">
import ProductCard from '@/components/storefront/ProductCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import type { PublicProductCard } from '@/types/storefront';

defineProps<{
    products: PublicProductCard[];
}>();
</script>

<template>
    <section class="border-t border-stone-200 bg-white">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <SectionHeading icon="layers" title="Related LUTs" />
            <div
                v-if="products.length > 0"
                class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in products"
                    :key="product.id"
                    :product="product"
                />
            </div>
            <EmptyState
                v-else
                class="mt-6"
                icon="layers"
                title="Related LUTs are coming soon."
                message="Related published LUTs will appear here when available."
            />
        </div>
    </section>
</template>
