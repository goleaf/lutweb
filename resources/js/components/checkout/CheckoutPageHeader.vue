<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';

withDefaults(
    defineProps<{
        backHref: string;
        backLabel: string;
        title: string;
        description?: string;
    }>(),
    {
        description: undefined,
    },
);
</script>

<template>
    <section class="border-b border-stone-200 bg-white">
        <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <Link
                :href="backHref"
                class="inline-flex items-center gap-1.5 rounded-sm text-sm text-stone-600 underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
            >
                <AppIcon name="arrow-left" class="size-3.5" />
                {{ backLabel }}
            </Link>
            <h1
                class="mt-3 inline-flex items-center gap-3 text-3xl font-semibold text-stone-950"
            >
                <AppIcon name="credit-card" class="size-6 text-teal-800" />
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-3 max-w-3xl text-sm leading-6 text-stone-600"
            >
                {{ description }}
            </p>
        </div>
    </section>
</template>
