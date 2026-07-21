<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';

defineProps<{
    prevPageUrl?: string | null;
    nextPageUrl?: string | null;
    label: string;
}>();
</script>

<template>
    <nav
        v-if="prevPageUrl || nextPageUrl"
        :aria-label="label"
        class="flex justify-between gap-3"
    >
        <Link
            v-if="prevPageUrl"
            :href="prevPageUrl"
            class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
        >
            <AppIcon name="arrow-left" class="size-4" />
            Previous
        </Link>
        <span v-else />
        <Link
            v-if="nextPageUrl"
            :href="nextPageUrl"
            class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
        >
            Next
            <AppIcon name="arrow-right" class="size-4" />
        </Link>
    </nav>
</template>
