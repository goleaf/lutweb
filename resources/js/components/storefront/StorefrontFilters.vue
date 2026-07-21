<script setup lang="ts">
import { computed, reactive, watch } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import { collectionItems } from '@/lib/storefront';
import type {
    PublicCategory,
    StorefrontFilterOptions,
    StorefrontFilters,
} from '@/types/storefront';

const props = withDefaults(
    defineProps<{
        filters: StorefrontFilters;
        options: StorefrontFilterOptions;
        fixedCategory?: PublicCategory | null;
        processing: boolean;
    }>(),
    {
        fixedCategory: null,
    },
);

const emit = defineEmits<{
    change: [filters: StorefrontFilters];
    reset: [];
}>();

const localFilters = reactive<StorefrontFilters>({ ...props.filters });
let searchTimer: number | undefined;

const categories = computed(() => collectionItems(props.options.categories));

watch(
    () => props.filters,
    (filters) => {
        Object.assign(localFilters, filters);
    },
);

function emitChange(): void {
    emit('change', { ...localFilters });
}

function queueSearch(): void {
    if (searchTimer !== undefined) {
        window.clearTimeout(searchTimer);
    }

    searchTimer = window.setTimeout(() => {
        emitChange();
    }, 400);
}
</script>

<template>
    <section
        class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm"
        aria-label="Catalog filters"
    >
        <div class="flex items-center justify-between gap-3">
            <h2
                class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
            >
                <AppIcon name="filter" class="size-4 text-teal-800" />
                Filters
            </h2>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-semibold text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                @click="emit('reset')"
            >
                <AppIcon name="reset" class="size-3.5" />
                Reset filters
            </button>
        </div>

        <div class="mt-4 grid gap-4">
            <label class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="search" class="size-4 text-stone-500" />
                    Search
                </span>
                <input
                    v-model="localFilters.q"
                    type="search"
                    autocomplete="off"
                    maxlength="100"
                    placeholder="Search LUTs"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @input="queueSearch"
                />
            </label>

            <div
                v-if="fixedCategory"
                class="rounded-md border border-stone-200 bg-stone-50 p-3 text-sm"
            >
                <p
                    class="inline-flex items-center gap-2 font-medium text-stone-950"
                >
                    <AppIcon name="tag" class="size-4 text-stone-500" />
                    Category
                </p>
                <p class="mt-1 text-stone-600">{{ fixedCategory.name }}</p>
            </div>

            <label v-else class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="tag" class="size-4 text-stone-500" />
                    Category
                </span>
                <select
                    v-model="localFilters.category"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @change="emitChange"
                >
                    <option :value="null">All categories</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.slug"
                    >
                        {{ category.name }} ({{ category.products_count }})
                    </option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="sparkles" class="size-4 text-stone-500" />
                    Tag
                </span>
                <select
                    v-model="localFilters.tag"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @change="emitChange"
                >
                    <option :value="null">All tags</option>
                    <option
                        v-for="tag in options.tags"
                        :key="tag.id"
                        :value="tag.slug"
                    >
                        {{ tag.name }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="monitor" class="size-4 text-stone-500" />
                    Compatible software
                </span>
                <select
                    v-model="localFilters.software"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @change="emitChange"
                >
                    <option :value="null">All software</option>
                    <option
                        v-for="software in options.software"
                        :key="software.id"
                        :value="software.slug"
                    >
                        {{ software.name }}
                    </option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="package" class="size-4 text-stone-500" />
                    Product type
                </span>
                <select
                    v-model="localFilters.type"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @change="emitChange"
                >
                    <option value="all">All types</option>
                    <option value="single_lut">Single LUT</option>
                    <option value="bundle">Bundle</option>
                    <option value="free_lut">Free LUT</option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="credit-card" class="size-4 text-stone-500" />
                    Pricing
                </span>
                <select
                    v-model="localFilters.pricing"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @change="emitChange"
                >
                    <option value="all">All prices</option>
                    <option value="free">Free</option>
                    <option value="paid">Paid</option>
                </select>
            </label>

            <label class="grid gap-2 text-sm font-medium text-stone-800">
                <span class="inline-flex items-center gap-2">
                    <AppIcon name="sliders" class="size-4 text-stone-500" />
                    Sort
                </span>
                <select
                    v-model="localFilters.sort"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-950 focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20 focus:outline-none"
                    @change="emitChange"
                >
                    <option value="featured">Featured</option>
                    <option value="newest">Newest</option>
                    <option value="price_asc">Price: low to high</option>
                    <option value="price_desc">Price: high to low</option>
                    <option value="name_asc">Name: A to Z</option>
                </select>
            </label>
        </div>

        <p
            class="mt-4 inline-flex items-center gap-2 text-sm text-stone-500"
            aria-live="polite"
            role="status"
        >
            <AppIcon
                :name="processing ? 'refresh' : 'check-circle'"
                class="size-4"
            />
            {{ processing ? 'Updating results...' : 'Filters ready.' }}
        </p>
    </section>
</template>
