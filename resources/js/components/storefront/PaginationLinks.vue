<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { paginationLabel } from '@/lib/storefront';
import type { Pagination } from '@/types/storefront';

defineProps<{
    meta: Pagination;
}>();
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        aria-label="Pagination"
        class="flex flex-wrap items-center justify-center gap-2"
    >
        <template v-for="link in meta.links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-md border px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                :class="
                    link.active
                        ? 'border-stone-950 bg-stone-950 text-white'
                        : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500 hover:text-stone-950'
                "
                :aria-current="link.active ? 'page' : undefined"
            >
                {{ paginationLabel(link.label) }}
            </Link>
            <span
                v-else
                class="rounded-md border border-stone-200 bg-stone-100 px-3 py-2 text-sm font-medium text-stone-400"
                aria-disabled="true"
            >
                {{ paginationLabel(link.label) }}
            </span>
        </template>
    </nav>
</template>
