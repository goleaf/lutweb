<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { home, register } from '@/routes';
</script>

<template>
    <Head title="Privacy Policy" />

    <main
        class="min-h-screen bg-stone-50 px-4 py-8 text-stone-950 sm:px-6 lg:px-8"
    >
        <article
            class="mx-auto max-w-3xl rounded-lg border border-stone-200 bg-white p-6 shadow-sm sm:p-8"
        >
            <p class="text-sm font-semibold tracking-wide text-teal-800">
                LUT Web
            </p>
            <h1 class="mt-4 text-3xl font-semibold text-stone-950">
                Privacy Policy
            </h1>
            <p class="mt-4 text-base leading-7 text-stone-700">
                Final Privacy Policy text will be added before production
                launch. This placeholder exists so account registration can link
                to the future legal document during early marketplace
                development.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <Link
                    :href="register()"
                    class="rounded-md bg-stone-950 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Return to registration
                </Link>
                <Link
                    :href="home()"
                    class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Home
                </Link>
            </div>
        </article>
    </main>
</template>
