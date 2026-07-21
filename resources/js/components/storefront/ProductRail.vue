<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';
import ProductCard from '@/components/storefront/ProductCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import type { PublicProductCard } from '@/types/storefront';

withDefaults(
    defineProps<{
        products: PublicProductCard[];
        title: string;
        description?: string;
        icon: AppIconName;
        iconClass?: string;
        actionHref?: string;
        actionLabel?: string;
        actionIcon?: AppIconName;
        emptyTitle?: string;
        emptyMessage?: string;
        emptyVariant?: 'solid' | 'dashed';
        columns?: 'three' | 'four';
        eagerCount?: number;
    }>(),
    {
        description: undefined,
        iconClass: 'text-teal-800',
        actionHref: undefined,
        actionLabel: undefined,
        actionIcon: 'arrow-right',
        emptyTitle: 'Products are coming soon.',
        emptyMessage: 'Published products will appear here automatically.',
        emptyVariant: 'solid',
        columns: 'three',
        eagerCount: 0,
    },
);
</script>

<template>
    <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading
                :icon="icon"
                :icon-class="iconClass"
                :title="title"
                :description="description"
            />
            <Link
                v-if="actionHref && actionLabel"
                :href="actionHref"
                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                {{ actionLabel }}
                <AppIcon :name="actionIcon" class="size-4" />
            </Link>
        </div>

        <div
            v-if="products.length > 0"
            :class="[
                'mt-6 grid gap-5 sm:grid-cols-2',
                columns === 'four' ? 'lg:grid-cols-4' : 'lg:grid-cols-3',
            ]"
        >
            <ProductCard
                v-for="(product, index) in products"
                :key="product.id"
                :product="product"
                :loading="index < eagerCount ? 'eager' : 'lazy'"
            />
        </div>
        <EmptyState
            v-else
            class="mt-6"
            icon="package"
            :title="emptyTitle"
            :message="emptyMessage"
            :variant="emptyVariant"
        />
    </section>
</template>
