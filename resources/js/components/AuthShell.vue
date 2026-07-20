<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { home } from '@/routes';

defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <main
        class="min-h-screen bg-stone-50 px-4 py-8 text-stone-950 sm:px-6 lg:px-8"
    >
        <div
            class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-md flex-col justify-center"
        >
            <div class="mb-8">
                <Link
                    :href="home()"
                    class="inline-flex items-center rounded-sm text-sm font-semibold tracking-wide text-stone-950 underline-offset-4 hover:text-teal-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    LUT Web
                </Link>
                <h1 class="mt-8 text-3xl font-semibold text-stone-950">
                    {{ title }}
                </h1>
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
