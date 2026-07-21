<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <Head title="Refund Policy" />

        <section class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-semibold text-stone-950">Refund Policy</h1>
            <p class="mt-4 text-sm leading-7 text-stone-700">
                Final refund or final-sale policy text must be reviewed and
                added before production launch. Digital-product sales remain
                subject to any refund or remedy required by applicable law.
            </p>
        </section>
    </PublicLayout>
</template>
