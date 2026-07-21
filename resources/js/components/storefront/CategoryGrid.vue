<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import { show as categoryShow } from '@/routes/categories';
import type { PublicCategory } from '@/types/storefront';

defineProps<{
    categories: PublicCategory[];
}>();
</script>

<template>
    <section class="border-y border-stone-200 bg-white">
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <SectionHeading icon="tag" title="Browse by category" />
            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Link
                    v-for="category in categories"
                    :key="category.id"
                    :href="categoryShow(category.slug)"
                    class="rounded-lg border border-stone-200 bg-stone-50 p-4 hover:border-teal-700 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <span class="flex items-start gap-2">
                        <AppIcon
                            name="tag"
                            class="mt-0.5 size-4 shrink-0 text-teal-800"
                        />
                        <span class="font-semibold text-stone-950">
                            {{ category.name }}
                        </span>
                    </span>
                    <span class="mt-2 block text-sm text-stone-600">
                        {{ category.products_count }} published
                    </span>
                </Link>
            </div>
        </div>
    </section>
</template>
