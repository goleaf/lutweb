<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PublicLayout from '@/layouts/PublicLayout.vue';
</script>

<template>
    <PublicLayout>
        <Head title="Terms of Sale" />

        <section class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-semibold text-stone-950">Terms of Sale</h1>
            <p class="mt-4 text-sm leading-7 text-stone-700">
                Final Terms of Sale must be reviewed and added before production
                launch. This placeholder exists so checkout can record a version
                and block unsafe live sales until the legal text is final.
            </p>
        </section>
    </PublicLayout>
</template>
