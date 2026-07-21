<script setup lang="ts">
import { computed, ref } from 'vue';

import type { ResponsiveImage } from '@/types/storefront';

const props = withDefaults(
    defineProps<{
        image: ResponsiveImage | null;
        sizes: string;
        loading?: 'eager' | 'lazy';
        fetchpriority?: 'high' | 'low' | 'auto';
        objectFit?: 'cover' | 'contain';
    }>(),
    {
        loading: 'lazy',
        fetchpriority: 'auto',
        objectFit: 'cover',
    },
);

const emit = defineEmits<{
    error: [url: string];
}>();

const failed = ref(false);

const hasImage = computed(
    () => props.image !== null && props.image.fallback_jpeg_url !== '',
);

const aspectRatio = computed(() => props.image?.aspect_ratio ?? '4 / 3');
const backgroundColor = computed(
    () => props.image?.placeholder_color ?? '#292524',
);
const fitClass = computed(() =>
    props.objectFit === 'contain' ? 'object-contain' : 'object-cover',
);

function markFailed(): void {
    if (props.image !== null) {
        emit('error', props.image.fallback_jpeg_url);
    }

    failed.value = true;
}
</script>

<template>
    <span
        class="block overflow-hidden bg-stone-900"
        :style="{ aspectRatio, backgroundColor }"
    >
        <picture v-if="hasImage && !failed">
            <source
                v-if="image?.webp_srcset"
                :srcset="image.webp_srcset"
                :sizes="sizes"
                type="image/webp"
            />
            <source
                v-if="image?.jpeg_srcset"
                :srcset="image.jpeg_srcset"
                :sizes="sizes"
                type="image/jpeg"
            />
            <img
                :src="image?.fallback_jpeg_url"
                :alt="image?.alt_text ?? ''"
                :width="image?.width ?? undefined"
                :height="image?.height ?? undefined"
                :loading="loading"
                decoding="async"
                :fetchpriority="fetchpriority"
                class="h-full w-full"
                :class="fitClass"
                @error="markFailed"
            />
        </picture>
        <span
            v-else
            class="flex h-full w-full items-center justify-center px-4 text-center text-sm font-medium text-stone-100"
            role="img"
            :aria-label="image?.alt_text || 'Image preview placeholder'"
        >
            Image preview is being prepared.
        </span>
    </span>
</template>
