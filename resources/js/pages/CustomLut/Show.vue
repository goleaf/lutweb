<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    project: {
        id: string;
        name: string;
        revision: number;
        parameters_hash: string;
        selected_style: {
            id: string | null;
            name: string;
        } | null;
    };
}>();
</script>

<template>
    <PublicLayout>
        <Head :title="project.name">
            <meta name="robots" content="noindex,nofollow" />
        </Head>

        <section class="mx-auto grid w-full max-w-5xl gap-5 px-4 py-10 sm:px-6 lg:px-8">
            <div>
                <p class="text-sm font-semibold text-teal-800">Custom LUT</p>
                <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                    {{ project.name }}
                </h1>
                <p class="mt-3 text-sm text-stone-600">
                    Revision {{ project.revision }}
                </p>
            </div>

            <section class="rounded-lg border border-stone-200 bg-white p-4">
                <h2 class="font-semibold text-stone-950">Selected Style</h2>
                <p class="mt-2 text-sm text-stone-600">
                    {{ project.selected_style?.name ?? 'No style selected yet.' }}
                </p>
            </section>
        </section>
    </PublicLayout>
</template>
