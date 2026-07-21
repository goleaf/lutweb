<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    styles: {
        id: string;
        name: string;
        slug: string;
        description: string | null;
        is_featured: boolean;
    }[];
}>();
</script>

<template>
    <PublicLayout>
        <Head title="Create Your LUT">
            <meta name="robots" content="noindex,nofollow" />
        </Head>

        <section class="mx-auto grid w-full max-w-5xl gap-6 px-4 py-10 sm:px-6 lg:px-8">
            <div>
                <p class="text-sm font-semibold text-teal-800">Custom LUT</p>
                <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                    Create Your LUT
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">
                    Start from a style, tune the look, and prepare a private downloadable package.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="style in styles"
                    :key="style.id"
                    class="rounded-lg border border-stone-200 bg-white p-4"
                >
                    <h2 class="font-semibold text-stone-950">{{ style.name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        {{ style.description ?? 'Style preset' }}
                    </p>
                </article>
            </div>
        </section>
    </PublicLayout>
</template>
