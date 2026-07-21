<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import ResponsivePicture from '@/components/storefront/ResponsivePicture.vue';
import type { PublicMedia } from '@/types/storefront';

const props = defineProps<{
    media: PublicMedia[];
    productName: string;
}>();

const selectedIndex = ref(0);
const failedIds = ref<number[]>([]);

const selected = computed(() => props.media[selectedIndex.value] ?? null);
const selectedFailed = computed(
    () =>
        selected.value !== null && failedIds.value.includes(selected.value.id),
);

watch(
    () => props.media.length,
    () => {
        selectedIndex.value = 0;
        failedIds.value = [];
    },
);

function selectImage(index: number): void {
    selectedIndex.value = index;
}

function markFailed(id: number): void {
    if (!failedIds.value.includes(id)) {
        failedIds.value = [...failedIds.value, id];
    }
}
</script>

<template>
    <section aria-label="Product gallery" class="grid gap-3">
        <div
            class="aspect-[4/3] overflow-hidden rounded-lg border border-stone-200 bg-stone-900"
        >
            <ResponsivePicture
                v-if="selected?.image && !selectedFailed"
                :image="selected.image"
                sizes="(min-width: 1024px) 66vw, 100vw"
                loading="eager"
                fetchpriority="high"
                class="h-full w-full"
                @error="markFailed(selected.id)"
            />
            <img
                v-else-if="selected?.url && !selectedFailed"
                :src="selected.url"
                :alt="selected.alt_text"
                :width="selected.width ?? undefined"
                :height="selected.height ?? undefined"
                class="h-full w-full object-cover"
                loading="eager"
                @error="markFailed(selected.id)"
            />
            <div
                v-else
                class="flex h-full items-center justify-center bg-[linear-gradient(135deg,#1c1917_0%,#134e4a_55%,#a16207_100%)] p-6 text-center text-sm font-medium text-white"
                role="img"
                :aria-label="`${productName} gallery image placeholder`"
            >
                Image preview is being prepared.
            </div>
        </div>

        <div
            v-if="media.length > 1"
            class="grid grid-cols-4 gap-2 sm:grid-cols-5 lg:grid-cols-6"
            aria-label="Gallery thumbnails"
        >
            <button
                v-for="(image, index) in media"
                :key="image.id"
                type="button"
                class="aspect-[4/3] overflow-hidden rounded-md border bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                :class="
                    index === selectedIndex
                        ? 'border-stone-950'
                        : 'border-stone-200 hover:border-stone-500'
                "
                :aria-pressed="index === selectedIndex"
                :aria-label="`Show image ${index + 1} for ${productName}`"
                @click="selectImage(index)"
            >
                <ResponsivePicture
                    v-if="image.image && !failedIds.includes(image.id)"
                    :image="image.image"
                    sizes="96px"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    @error="markFailed(image.id)"
                />
                <img
                    v-else-if="image.url && !failedIds.includes(image.id)"
                    :src="image.url"
                    :alt="image.alt_text"
                    :width="image.width ?? undefined"
                    :height="image.height ?? undefined"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                    @error="markFailed(image.id)"
                />
                <span
                    v-else
                    class="flex h-full items-center justify-center px-2 text-center text-xs font-semibold text-stone-500"
                >
                    Preview unavailable
                </span>
            </button>
        </div>
    </section>
</template>
