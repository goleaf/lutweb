<script setup lang="ts">
import { computed, ref } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import StorefrontSeoHead from '@/components/storefront/StorefrontSeoHead.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { StorefrontSeo } from '@/types/storefront';

type FaqItem = {
    id: string;
    question: string;
    answer: string;
    keywords: string[];
};

type FaqSection = {
    slug: string;
    title: string;
    description: string;
    items: FaqItem[];
};

const props = defineProps<{
    sections: FaqSection[];
    question_count: number;
    seo: StorefrontSeo;
}>();

const query = ref('');
const searchTerms = computed(() =>
    query.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean),
);
const visibleSections = computed(() => {
    if (searchTerms.value.length === 0) {
        return props.sections;
    }

    return props.sections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => {
                const searchable = [
                    section.title,
                    item.question,
                    item.answer,
                    ...item.keywords,
                ]
                    .join(' ')
                    .toLocaleLowerCase();

                return searchTerms.value.every((term) =>
                    searchable.includes(term),
                );
            }),
        }))
        .filter((section) => section.items.length > 0);
});
const visibleQuestionCount = computed(() =>
    visibleSections.value.reduce(
        (total, section) => total + section.items.length,
        0,
    ),
);

function clearSearch(): void {
    query.value = '';
}
</script>

<template>
    <PublicLayout>
        <StorefrontSeoHead :seo="seo" />

        <section class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-9 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-end lg:px-8"
            >
                <SectionHeading
                    as="h1"
                    size="page"
                    icon="sparkles"
                    eyebrow="Help center"
                    title="LUT questions and answers"
                    :description="
                        question_count +
                        ' practical answers covering LUT selection, installation, color workflows, photo testing, downloads, PayPal, licensing, and support.'
                    "
                >
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-semibold text-stone-700"
                    >
                        <AppIcon name="receipt" class="size-4 text-teal-800" />
                        {{ sections.length }} topics ·
                        {{ question_count }} answers
                    </div>
                </SectionHeading>

                <div>
                    <label
                        for="faq-search"
                        class="text-sm font-semibold text-stone-950"
                    >
                        Search every answer
                    </label>
                    <div class="relative mt-2">
                        <AppIcon
                            name="search"
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-500"
                        />
                        <input
                            id="faq-search"
                            v-model="query"
                            type="search"
                            autocomplete="off"
                            placeholder="Try “Cube33”, “PayPal”, or “skin”"
                            class="w-full rounded-lg border border-stone-300 bg-white py-3 pr-10 pl-10 text-sm text-stone-950 shadow-sm outline-none placeholder:text-stone-500 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                        />
                        <button
                            v-if="query"
                            type="button"
                            aria-label="Clear FAQ search"
                            class="absolute top-1/2 right-2 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-stone-500 hover:bg-stone-100 hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            @click="clearSearch"
                        >
                            <AppIcon name="close" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:px-8"
        >
            <aside class="h-fit lg:sticky lg:top-6">
                <nav
                    aria-label="FAQ topics"
                    class="rounded-xl border border-stone-200 bg-white p-3 shadow-sm"
                >
                    <p
                        class="px-3 py-2 text-xs font-semibold tracking-wide text-stone-500 uppercase"
                    >
                        Browse topics
                    </p>
                    <a
                        v-for="section in sections"
                        :key="section.slug"
                        :href="'#' + section.slug"
                        class="block rounded-md px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        {{ section.title }}
                    </a>
                </nav>
            </aside>

            <main class="min-w-0">
                <div
                    class="mb-5 flex flex-wrap items-center justify-between gap-3"
                    aria-live="polite"
                >
                    <p class="text-sm font-medium text-stone-700">
                        {{ visibleQuestionCount }} answer{{
                            visibleQuestionCount === 1 ? '' : 's'
                        }}
                        {{ query ? 'matched your search' : 'available' }}
                    </p>
                    <button
                        v-if="query"
                        type="button"
                        class="rounded-md text-sm font-semibold text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                        @click="clearSearch"
                    >
                        Show all questions
                    </button>
                </div>

                <div v-if="visibleSections.length > 0" class="grid gap-10">
                    <section
                        v-for="section in visibleSections"
                        :id="section.slug"
                        :key="section.slug"
                        class="scroll-mt-6"
                        :aria-labelledby="section.slug + '-heading'"
                    >
                        <SectionHeading
                            :heading-id="section.slug + '-heading'"
                            icon="receipt"
                            :title="section.title"
                            :description="section.description"
                        />

                        <div class="mt-4 grid gap-3">
                            <details
                                v-for="item in section.items"
                                :id="item.id"
                                :key="item.id"
                                :open="searchTerms.length > 0"
                                class="group scroll-mt-6 rounded-xl border border-stone-200 bg-white shadow-sm open:border-teal-700/40"
                            >
                                <summary
                                    class="flex cursor-pointer list-none items-start justify-between gap-4 rounded-xl px-4 py-4 text-sm font-semibold text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 sm:px-5"
                                >
                                    <span>{{ item.question }}</span>
                                    <AppIcon
                                        name="chevron-right"
                                        class="mt-0.5 size-4 shrink-0 text-stone-500 transition-transform group-open:rotate-90"
                                    />
                                </summary>
                                <p
                                    class="border-t border-stone-100 px-4 py-4 text-sm leading-7 text-stone-700 sm:px-5"
                                >
                                    {{ item.answer }}
                                </p>
                            </details>
                        </div>
                    </section>
                </div>

                <section
                    v-else
                    class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center"
                    aria-labelledby="faq-empty-heading"
                >
                    <AppIcon
                        name="search"
                        class="mx-auto size-8 text-stone-400"
                    />
                    <h2
                        id="faq-empty-heading"
                        class="mt-3 text-lg font-semibold text-stone-950"
                    >
                        No answers matched that search
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Try fewer words, search for an application name, or
                        contact goleaf@gmail.com.
                    </p>
                    <button
                        type="button"
                        class="mt-4 rounded-md bg-stone-950 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="clearSearch"
                    >
                        Clear search
                    </button>
                </section>
            </main>
        </div>
    </PublicLayout>
</template>
