<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
</script>

<template>
    <section class="border-y border-stone-200 bg-stone-100">
        <div
            class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-12 sm:px-6 md:grid-cols-[1fr_auto] lg:px-8"
        >
            <SectionHeading
                icon="wand"
                title="Custom LUT wizard"
                description="Create and preview a custom LUT draft with controlled variations. Purchase and downloadable custom LUT files are coming soon."
            />
            <Link
                href="/custom-lut"
                class="inline-flex h-fit items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                <AppIcon name="wand" class="size-4" />
                Create Your LUT
            </Link>
        </div>
    </section>
</template>
