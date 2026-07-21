<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import { index as shopIndex } from '@/routes/shop';

const props = defineProps<{
    status: 403 | 404 | 419 | 429 | 500 | 503;
}>();

const title = computed(() => {
    switch (props.status) {
        case 403:
            return 'Access unavailable';
        case 404:
            return 'Page not found';
        case 419:
            return 'Session expired';
        case 429:
            return 'Too many requests';
        case 503:
            return 'Service unavailable';
        default:
            return 'Something went wrong';
    }
});

const message = computed(() => {
    switch (props.status) {
        case 403:
            return 'You do not have access to this page.';
        case 404:
            return 'The page you are looking for is not available.';
        case 419:
            return 'Please refresh the page and try again.';
        case 429:
            return 'Please wait a moment before trying again.';
        case 503:
            return 'LUT Web is temporarily unavailable.';
        default:
            return 'The request could not be completed safely.';
    }
});
</script>

<template>
    <PublicLayout>
        <Head :title="title">
            <meta head-key="robots" name="robots" content="noindex,nofollow" />
            <meta
                head-key="description"
                name="description"
                :content="message"
            />
        </Head>

        <main
            class="mx-auto grid min-h-[70vh] w-full max-w-3xl place-items-center px-4 py-16 text-center sm:px-6 lg:px-8"
        >
            <section class="grid gap-6">
                <p
                    class="inline-flex items-center justify-center gap-2 text-sm font-semibold tracking-wide text-teal-800"
                >
                    <AppIcon name="alert-circle" class="size-4" />
                    Error {{ status }}
                </p>
                <div class="grid gap-3">
                    <h1 class="text-4xl font-semibold text-stone-950">
                        {{ title }}
                    </h1>
                    <p class="text-base leading-7 text-stone-600">
                        {{ message }}
                    </p>
                </div>
                <div class="flex flex-wrap justify-center gap-3">
                    <Link
                        :href="home()"
                        class="inline-flex items-center gap-2 rounded-md bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="home" class="size-4" />
                        Go home
                    </Link>
                    <Link
                        :href="shopIndex()"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="shop" class="size-4" />
                        Browse LUTs
                    </Link>
                </div>
            </section>
        </main>
    </PublicLayout>
</template>
