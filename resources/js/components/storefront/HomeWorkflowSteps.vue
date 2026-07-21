<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';

const steps: {
    icon: AppIconName;
    title: string;
    description: string;
}[] = [
    {
        icon: 'search',
        title: 'Choose a look.',
        description:
            'Filter the public catalog by style, software, product type, and price.',
    },
    {
        icon: 'image',
        title: 'Compare before and after.',
        description:
            'Product pages can show accessible comparisons prepared by the catalog team.',
    },
    {
        icon: 'shield',
        title: 'Purchase and download securely.',
        description:
            'Checkout and customer downloads will be enabled in a later milestone.',
    },
];
</script>

<template>
    <section class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <SectionHeading icon="sliders" title="How it works" />
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <article
                v-for="step in steps"
                :key="step.title"
                class="rounded-lg border border-stone-200 bg-white p-5"
            >
                <span
                    class="grid size-9 place-items-center rounded-md bg-teal-50 text-teal-800"
                    aria-hidden="true"
                >
                    <AppIcon :name="step.icon" class="size-5" />
                </span>
                <h3 class="mt-3 text-lg font-semibold text-stone-950">
                    {{ step.title }}
                </h3>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    {{ step.description }}
                </p>
            </article>
        </div>
    </section>
</template>
