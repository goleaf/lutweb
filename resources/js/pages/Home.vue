<script setup lang="ts">
import { computed } from 'vue';

import CategoryGrid from '@/components/storefront/CategoryGrid.vue';
import CustomLutPromoBand from '@/components/storefront/CustomLutPromoBand.vue';
import HomeHero from '@/components/storefront/HomeHero.vue';
import HomeWorkflowSteps from '@/components/storefront/HomeWorkflowSteps.vue';
import ProductRail from '@/components/storefront/ProductRail.vue';
import StorefrontSeoHead from '@/components/storefront/StorefrontSeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { collectionItems } from '@/lib/storefront';
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
        <StorefrontSeoHead :seo="seo" />

        <HomeHero />

        <ProductRail
            :products="featuredProducts"
            title="Featured LUTs"
            description="Browse published looks prepared for the marketplace catalog."
            icon="star"
            icon-class="text-amber-700"
            :action-href="shopIndex.url()"
            action-label="View all"
            action-icon="arrow-right"
            empty-title="Featured LUTs are coming soon."
            empty-message="Published featured products will appear here automatically."
            empty-variant="dashed"
            columns="four"
            :eager-count="2"
        />

        <CategoryGrid :categories="categories" />

        <HomeWorkflowSteps />

        <CustomLutPromoBand />

        <ProductRail
            v-if="freeProducts.length > 0"
            :products="freeProducts"
            title="Free LUTs"
            description="Published free LUT products appear here when available."
            icon="download"
            :action-href="shopIndex.url({ query: { pricing: 'free' } })"
            action-label="Browse free LUTs"
            action-icon="download"
        />
    </PublicLayout>
</template>
