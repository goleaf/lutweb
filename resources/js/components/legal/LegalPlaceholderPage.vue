<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import type { AppIconName } from '@/components/AppIcon.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    title: string;
    message: string;
    icon: AppIconName;
}>();
</script>

<template>
    <PublicLayout>
        <Head :title="title" />

        <section class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <SectionHeading as="h1" size="page" :icon="icon" :title="title" />
            <p class="mt-4 text-sm leading-7 text-stone-700">
                {{ message }}
            </p>
        </section>
    </PublicLayout>
</template>
