<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

type SourceAttribution = {
    key: string;
    title: string;
    creator: string;
    license: string;
    license_url: string;
    source_page: string;
    modification: string;
    reuse_notice: string;
    attribution_required: boolean;
    share_alike_required: boolean;
};

type AttributionCategory = {
    slug: string;
    name: string;
    sources: readonly SourceAttribution[];
};

const props = defineProps<{
    source_count: number;
    source_attributions: readonly AttributionCategory[];
}>();

const searchQuery = ref('');
const normalizedQuery = computed(() =>
    searchQuery.value.trim().toLocaleLowerCase(),
);
const filteredCategories = computed(() => {
    if (normalizedQuery.value === '') {
        return props.source_attributions;
    }

    return props.source_attributions
        .map((category) => ({
            ...category,
            sources: category.sources.filter((source) =>
                [
                    category.name,
                    source.key,
                    source.title,
                    source.creator,
                    source.license,
                ].some((value) =>
                    value.toLocaleLowerCase().includes(normalizedQuery.value),
                ),
            ),
        }))
        .filter((category) => category.sources.length > 0);
});

function cleanTitle(title: string): string {
    return title.replace(/^File:/, '');
}
</script>

<template>
    <PublicLayout>
        <Head title="License Agreement and Image Credits">
            <meta
                name="description"
                content="LUT Web license status and source credits for the reusable photographs used in storefront LUT previews."
            />
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:px-8 lg:py-16"
            >
                <div class="max-w-3xl">
                    <p
                        class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-teal-800"
                    >
                        <AppIcon name="shield" class="size-4" />
                        LUT Web legal
                    </p>
                    <h1
                        class="mt-3 text-3xl font-semibold tracking-tight text-stone-950 sm:text-4xl"
                    >
                        License Agreement
                    </h1>
                    <p class="mt-5 text-base leading-8 text-stone-700">
                        The final customer License Agreement must be reviewed
                        before production sales are enabled. Customers receive a
                        usage license for LUT files; intellectual-property
                        rights remain with the store owner.
                    </p>
                </div>

                <aside
                    class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950"
                    aria-label="License readiness status"
                >
                    <p class="flex items-center gap-2 font-semibold">
                        <AppIcon name="alert-circle" class="size-5" />
                        Legal review required
                    </p>
                    <p class="mt-2 leading-6">
                        Paid checkout remains fail-closed until the final legal
                        document versions and payment readiness checks pass.
                    </p>
                </aside>
            </div>
        </section>

        <section
            id="image-source-credits"
            class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16"
            aria-labelledby="image-source-credits-heading"
        >
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="max-w-3xl">
                    <p
                        class="inline-flex items-center gap-2 text-sm font-semibold text-teal-800"
                    >
                        <AppIcon name="image" class="size-4" />
                        Transparent source record
                    </p>
                    <h2
                        id="image-source-credits-heading"
                        class="mt-3 text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl"
                    >
                        Image source credits
                    </h2>
                    <p class="mt-4 text-sm leading-7 text-stone-700">
                        The {{ source_count }} source photographs below are used
                        to demonstrate LUT color treatments. Each record links
                        to the original Wikimedia Commons file and its license.
                        The images were cropped and resized; their source color
                        was not changed before LUT previews were generated.
                    </p>
                </div>

                <label class="block self-end">
                    <span class="text-sm font-semibold text-stone-800"
                        >Search credits</span
                    >
                    <span class="relative mt-2 block">
                        <AppIcon
                            name="search"
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-500"
                        />
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="w-full rounded-lg border border-stone-300 bg-white py-2.5 pr-3 pl-10 text-sm text-stone-950 outline-none placeholder:text-stone-500 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                            placeholder="Title, creator, license…"
                        />
                    </span>
                </label>
            </div>

            <p
                class="mt-5 text-sm text-stone-600"
                role="status"
                aria-live="polite"
            >
                {{
                    filteredCategories.reduce(
                        (total, category) => total + category.sources.length,
                        0,
                    )
                }}
                matching sources
            </p>

            <div v-if="filteredCategories.length > 0" class="mt-6 space-y-4">
                <details
                    v-for="(category, index) in filteredCategories"
                    :key="category.slug"
                    :open="normalizedQuery !== '' || index === 0"
                    class="group overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-teal-700"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-800"
                            >
                                <AppIcon name="palette" class="size-4" />
                            </span>
                            <span>
                                <span
                                    class="block font-semibold text-stone-950"
                                >
                                    {{ category.name }}
                                </span>
                                <span class="block text-xs text-stone-500">
                                    {{ category.sources.length }} sources
                                </span>
                            </span>
                        </span>
                        <AppIcon
                            name="chevron-right"
                            class="size-5 shrink-0 text-stone-500 transition-transform group-open:rotate-90"
                        />
                    </summary>

                    <div
                        class="grid gap-3 border-t border-stone-200 bg-stone-50 p-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="source in category.sources"
                            :key="source.key"
                            class="min-w-0 rounded-lg border border-stone-200 bg-white p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-teal-800 uppercase"
                            >
                                {{ source.key }}
                            </p>
                            <h3
                                class="mt-2 text-sm leading-6 font-semibold break-words text-stone-950"
                            >
                                {{ cleanTitle(source.title) }}
                            </h3>
                            <p class="mt-1 text-xs leading-5 text-stone-600">
                                By {{ source.creator }}
                            </p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <a
                                    :href="source.source_page"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-stone-300 px-2.5 py-1.5 text-xs font-semibold text-stone-800 hover:border-teal-700 hover:text-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                                >
                                    <AppIcon name="globe" class="size-3.5" />
                                    Original
                                </a>
                                <a
                                    :href="source.license_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-stone-950 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                                >
                                    <AppIcon
                                        name="check-circle"
                                        class="size-3.5"
                                    />
                                    {{ source.license }}
                                </a>
                            </div>

                            <dl
                                class="mt-4 space-y-2 border-t border-stone-100 pt-3 text-xs leading-5 text-stone-600"
                            >
                                <div>
                                    <dt class="font-semibold text-stone-800">
                                        Modification
                                    </dt>
                                    <dd>{{ source.modification }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-stone-800">
                                        Reuse
                                    </dt>
                                    <dd>{{ source.reuse_notice }}</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </details>
            </div>

            <div
                v-else
                class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white px-6 py-10 text-center"
            >
                <AppIcon name="search" class="mx-auto size-6 text-stone-500" />
                <p class="mt-3 text-sm font-semibold text-stone-900">
                    No source credits match that search.
                </p>
            </div>
        </section>
    </PublicLayout>
</template>
