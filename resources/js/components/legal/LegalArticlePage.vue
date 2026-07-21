<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

export type LegalArticleSection = {
    title: string;
    paragraphs: readonly string[];
};

defineProps<{
    title: string;
    metaDescription: string;
    effectiveDate: string;
    intro: string;
    sections: readonly LegalArticleSection[];
}>();
</script>

<template>
    <PublicLayout>
        <Head :title="title">
            <meta name="description" :content="metaDescription" />
        </Head>

        <section class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <p
                class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-teal-800"
            >
                <AppIcon name="shield" class="size-4" />
                LUT Web legal
            </p>
            <h1 class="mt-3 text-3xl font-semibold text-stone-950">
                {{ title }}
            </h1>
            <p
                class="mt-3 inline-flex items-center gap-2 text-sm text-stone-600"
            >
                <AppIcon name="clock" class="size-4 text-teal-800" />
                <span>Effective date: {{ effectiveDate }}</span>
            </p>
            <p class="mt-6 text-base leading-8 text-stone-700">
                {{ intro }}
            </p>

            <div class="mt-10 space-y-9">
                <section
                    v-for="section in sections"
                    :key="section.title"
                    class="border-t border-stone-200 pt-7"
                >
                    <h2 class="text-xl font-semibold text-stone-950">
                        {{ section.title }}
                    </h2>
                    <div
                        class="mt-4 space-y-4 text-sm leading-7 text-stone-700"
                    >
                        <p
                            v-for="paragraph in section.paragraphs"
                            :key="paragraph"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                </section>
            </div>

            <nav
                v-if="$slots['related-links']"
                aria-label="Related legal links"
                class="mt-10 flex flex-wrap gap-3 border-t border-stone-200 pt-6"
            >
                <slot name="related-links" />
            </nav>
        </section>
    </PublicLayout>
</template>
