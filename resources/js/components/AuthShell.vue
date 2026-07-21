<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import { home } from '@/routes';

defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <Head>
        <meta head-key="robots" name="robots" content="noindex,nofollow" />
    </Head>

    <main
        class="min-h-screen bg-stone-50 px-4 py-8 text-stone-950 sm:px-6 lg:px-8"
    >
        <div
            class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-md flex-col justify-center"
        >
            <div class="mb-8">
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-2 rounded-sm text-sm font-semibold tracking-wide text-stone-950 underline-offset-4 hover:text-teal-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    <AppIcon name="palette" class="size-4 text-teal-800" />
                    LUT Web
                </Link>
                <div class="mt-8 flex items-center gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-md bg-stone-950 text-white"
                        aria-hidden="true"
                    >
                        <AppIcon name="lock" class="size-5" />
                    </span>
                    <h1 class="text-3xl font-semibold text-stone-950">
                        {{ title }}
                    </h1>
                </div>
                <p class="mt-3 text-sm leading-6 text-stone-600">
                    {{ description }}
                </p>
            </div>

            <section
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm sm:p-6"
            >
                <slot />
            </section>
        </div>
    </main>
</template>
