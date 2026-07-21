<script setup lang="ts">
import { computed, ref } from 'vue';

import ResponsivePicture from '@/components/storefront/ResponsivePicture.vue';
import type { PublicProductExample } from '@/types/storefront';

const props = defineProps<{
    example: PublicProductExample;
}>();

const mode = ref<'slider' | 'hold' | 'side'>('slider');
const position = ref(50);
const showOriginal = ref(false);
const failedImages = ref<string[]>([]);
const beforeUrl = computed(() => props.example.before.fallback_jpeg_url);
const afterUrl = computed(() => props.example.after.fallback_jpeg_url);
const holdImage = computed(() =>
    showOriginal.value ? props.example.before : props.example.after,
);

const beforeClipStyle = computed(() => ({
    clipPath: `inset(0 ${100 - position.value}% 0 0)`,
}));

const hasFailure = computed(
    () =>
        failedImages.value.includes(beforeUrl.value) ||
        failedImages.value.includes(afterUrl.value),
);

function markFailed(url: string): void {
    if (!failedImages.value.includes(url)) {
        failedImages.value = [...failedImages.value, url];
    }
}

function revealOriginal(): void {
    showOriginal.value = true;
}

function restoreResult(): void {
    showOriginal.value = false;
}
</script>

<template>
    <section class="rounded-lg border border-stone-200 bg-white p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-stone-950">
                    {{ example.title ?? 'Before and after' }}
                </h3>
                <p class="mt-1 text-sm text-stone-600">
                    Compare the prepared original image with the finished LUT
                    look.
                </p>
            </div>

            <div
                class="inline-flex rounded-md border border-stone-200 bg-stone-50 p-1"
                aria-label="Comparison mode"
            >
                <button
                    type="button"
                    class="rounded px-2.5 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    :class="
                        mode === 'slider'
                            ? 'bg-white text-stone-950 shadow-sm'
                            : 'text-stone-600 hover:text-stone-950'
                    "
                    @click="mode = 'slider'"
                >
                    Slider
                </button>
                <button
                    type="button"
                    class="rounded px-2.5 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    :class="
                        mode === 'hold'
                            ? 'bg-white text-stone-950 shadow-sm'
                            : 'text-stone-600 hover:text-stone-950'
                    "
                    @click="mode = 'hold'"
                >
                    Hold
                </button>
                <button
                    type="button"
                    class="rounded px-2.5 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    :class="
                        mode === 'side'
                            ? 'bg-white text-stone-950 shadow-sm'
                            : 'text-stone-600 hover:text-stone-950'
                    "
                    @click="mode = 'side'"
                >
                    Side by side
                </button>
            </div>
        </div>

        <div
            v-if="hasFailure"
            class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-900"
        >
            This comparison image could not be loaded.
        </div>

        <div v-else class="mt-4">
            <div v-if="mode === 'slider'" class="grid gap-3">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-md bg-stone-900"
                >
                    <ResponsivePicture
                        :image="example.after"
                        sizes="(min-width: 768px) 720px, 100vw"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        @error="markFailed(afterUrl)"
                    />
                    <ResponsivePicture
                        :image="example.before"
                        sizes="(min-width: 768px) 720px, 100vw"
                        class="absolute inset-0 h-full w-full object-cover"
                        :style="beforeClipStyle"
                        loading="lazy"
                        @error="markFailed(beforeUrl)"
                    />
                    <div
                        class="pointer-events-none absolute inset-y-0 w-0.5 bg-white shadow"
                        :style="{ left: `${position}%` }"
                    />
                    <span
                        class="absolute top-3 left-3 rounded-full bg-stone-950/80 px-2 py-1 text-xs font-semibold text-white"
                    >
                        Before
                    </span>
                    <span
                        class="absolute top-3 right-3 rounded-full bg-stone-950/80 px-2 py-1 text-xs font-semibold text-white"
                    >
                        After
                    </span>
                </div>
                <label class="grid gap-2 text-sm font-medium text-stone-800">
                    Comparison position
                    <input
                        v-model.number="position"
                        type="range"
                        min="0"
                        max="100"
                        class="accent-teal-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                        :aria-label="`Comparison position for ${example.title ?? 'example'}`"
                    />
                </label>
            </div>

            <div v-else-if="mode === 'hold'" class="grid gap-3">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-md bg-stone-900"
                >
                    <ResponsivePicture
                        :image="holdImage"
                        sizes="(min-width: 768px) 720px, 100vw"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        @error="markFailed(showOriginal ? beforeUrl : afterUrl)"
                    />
                    <span
                        class="absolute top-3 left-3 rounded-full bg-stone-950/80 px-2 py-1 text-xs font-semibold text-white"
                    >
                        {{ showOriginal ? 'Before' : 'After' }}
                    </span>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    @pointerdown="revealOriginal"
                    @pointerup="restoreResult"
                    @pointercancel="restoreResult"
                    @pointerleave="restoreResult"
                    @keydown.space.prevent="revealOriginal"
                    @keyup.space.prevent="restoreResult"
                    @keydown.enter.prevent="revealOriginal"
                    @keyup.enter.prevent="restoreResult"
                    @blur="restoreResult"
                >
                    Press and hold to show original
                </button>
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2">
                <figure class="grid gap-2">
                    <div
                        class="aspect-[4/3] overflow-hidden rounded-md bg-stone-900"
                    >
                        <ResponsivePicture
                            :image="example.before"
                            sizes="(min-width: 768px) 50vw, 100vw"
                            class="h-full w-full object-cover"
                            loading="lazy"
                            @error="markFailed(beforeUrl)"
                        />
                    </div>
                    <figcaption class="text-sm font-semibold text-stone-800">
                        Before
                    </figcaption>
                </figure>
                <figure class="grid gap-2">
                    <div
                        class="aspect-[4/3] overflow-hidden rounded-md bg-stone-900"
                    >
                        <ResponsivePicture
                            :image="example.after"
                            sizes="(min-width: 768px) 50vw, 100vw"
                            class="h-full w-full object-cover"
                            loading="lazy"
                            @error="markFailed(afterUrl)"
                        />
                    </div>
                    <figcaption class="text-sm font-semibold text-stone-800">
                        After
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>
</template>
