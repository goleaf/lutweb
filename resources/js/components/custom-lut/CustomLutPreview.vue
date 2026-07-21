<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import { WebGlLutRenderer } from '@/lib/lut-preview/WebGlLutRenderer';
import type {
    CanonicalLutParameters,
    LutWorkerRequest,
    LutWorkerResponse,
    LutWizardConfig,
    WizardProjectPhoto,
    WebGlRendererState,
} from '@/types/lut-wizard';

const props = defineProps<{
    photos: WizardProjectPhoto[];
    parameters: CanonicalLutParameters;
    config: LutWizardConfig;
}>();

const selectedPhotoId = defineModel<string | null>('selectedPhotoId', {
    required: true,
});

const container = ref<HTMLElement | null>(null);
const transformedCanvas = ref<HTMLCanvasElement | null>(null);
const originalImage = ref<HTMLImageElement | null>(null);
const status = ref('Add a photo to start previewing.');
const mode = ref<'transformed' | 'compare' | 'side-by-side'>('transformed');
const comparison = ref(50);
const holdingOriginal = ref(false);
const rendererState = ref<WebGlRendererState>('idle');
const useFallback = ref(false);
const worker = ref<Worker | null>(null);
const renderer = ref<WebGlLutRenderer | null>(null);
const lutRequestId = ref(0);
const fallbackRequestId = ref(0);
const lutDebounce = ref<number | null>(null);

const readyPhotos = computed(() =>
    props.photos.filter(
        (photo) => photo.status === 'ready' && photo.preview_url,
    ),
);
const selectedPhoto = computed(() => {
    if (selectedPhotoId.value !== null) {
        const selected = readyPhotos.value.find(
            (photo) => photo.id === selectedPhotoId.value,
        );

        if (selected) {
            return selected;
        }
    }

    return readyPhotos.value[0] ?? null;
});

const parametersWithoutIntensityKey = computed(() =>
    JSON.stringify({
        ...props.parameters,
        intensity: 1000,
    }),
);

function createWorker(): Worker {
    return new Worker(
        new URL('../../workers/lut-preview.worker.ts', import.meta.url),
        {
            type: 'module',
        },
    );
}

function setRendererState(state: WebGlRendererState, message?: string): void {
    rendererState.value = state;

    if (state === 'unsupported' || state === 'failed') {
        useFallback.value = true;
        status.value = message ?? 'Compatibility preview is being used.';
        void renderFallback();
    } else if (state === 'context-lost') {
        status.value = message ?? 'The preview context was interrupted.';
    }
}

function startRenderer(): void {
    renderer.value?.dispose();
    renderer.value = null;

    if (container.value !== null && transformedCanvas.value !== null) {
        const webglRenderer = new WebGlLutRenderer(
            transformedCanvas.value,
            container.value,
            setRendererState,
        );
        renderer.value = webglRenderer;
        useFallback.value = !webglRenderer.initialize(
            props.config.preview_lut_size,
        );
    }
}

async function loadImage(photo: WizardProjectPhoto): Promise<HTMLImageElement> {
    const image = new Image();
    image.decoding = 'async';
    image.loading = 'eager';
    image.src = photo.preview_url ?? '';

    await image.decode();

    return image;
}

async function loadSelectedPhoto(): Promise<void> {
    const photo = selectedPhoto.value;

    if (photo === null) {
        status.value = 'Upload a photo to preview your look.';

        return;
    }

    try {
        const image = await loadImage(photo);
        originalImage.value = image;

        if (selectedPhotoId.value !== photo.id) {
            selectedPhotoId.value = photo.id;
        }

        await nextTick();

        if (!useFallback.value && renderer.value !== null) {
            renderer.value.setImage(image);
            renderer.value.setIntensity(props.parameters.intensity);
            scheduleLutGeneration(0);
            status.value = 'WebGL preview ready.';
        } else {
            await renderFallback();
        }
    } catch {
        status.value = 'This preview image could not be loaded.';
    }
}

function scheduleLutGeneration(delay = 120): void {
    if (worker.value === null || useFallback.value) {
        return;
    }

    if (lutDebounce.value !== null) {
        window.clearTimeout(lutDebounce.value);
    }

    lutDebounce.value = window.setTimeout(() => {
        const requestId = ++lutRequestId.value;
        const message: LutWorkerRequest = {
            type: 'GenerateLut',
            requestId,
            parameters: props.parameters,
            size: props.config.preview_lut_size,
        };

        worker.value?.postMessage(message);
        status.value = 'Preparing preview LUT...';
    }, delay);
}

async function renderFallback(): Promise<void> {
    const image = originalImage.value;
    const canvas = transformedCanvas.value;

    if (worker.value === null || image === null || canvas === null) {
        return;
    }

    const scale = Math.min(
        1,
        props.config.cpu_fallback_maximum_edge /
            Math.max(image.naturalWidth, image.naturalHeight),
    );
    const width = Math.max(1, Math.round(image.naturalWidth * scale));
    const height = Math.max(1, Math.round(image.naturalHeight * scale));
    canvas.width = width;
    canvas.height = height;
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;

    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (context === null) {
        status.value = 'Preview fallback is unavailable in this browser.';

        return;
    }

    context.drawImage(image, 0, 0, width, height);
    const imageData = context.getImageData(0, 0, width, height);
    const pixels = new Uint8ClampedArray(imageData.data);
    const requestId = ++fallbackRequestId.value;
    const message: LutWorkerRequest = {
        type: 'TransformImageFallback',
        requestId,
        width,
        height,
        parameters: props.parameters,
        data: pixels.buffer,
    };

    worker.value.postMessage(message, [pixels.buffer]);
    status.value = 'Compatibility preview is being used.';
}

function handleWorkerMessage(event: MessageEvent<LutWorkerResponse>): void {
    const response = event.data;

    if (response.type === 'WorkerError') {
        status.value = response.message;

        return;
    }

    if (response.type === 'LutGenerated') {
        if (
            response.requestId !== lutRequestId.value ||
            renderer.value === null
        ) {
            return;
        }

        renderer.value.setLut(response.data, response.size);
        renderer.value.setIntensity(props.parameters.intensity);
        status.value = 'WebGL preview ready.';

        return;
    }

    if (response.requestId !== fallbackRequestId.value) {
        return;
    }

    const canvas = transformedCanvas.value;

    if (canvas === null) {
        return;
    }

    const context = canvas.getContext('2d');

    if (context === null) {
        return;
    }

    const pixels = new Uint8ClampedArray(response.data);
    const imageData = new ImageData(pixels, response.width, response.height);
    context.putImageData(imageData, 0, 0);
}

function clipStyle(): string {
    if (mode.value !== 'compare') {
        return 'inset(0 0 0 0)';
    }

    return `inset(0 0 0 ${comparison.value}%)`;
}

onMounted(() => {
    worker.value = createWorker();
    worker.value.addEventListener('message', handleWorkerMessage);
    startRenderer();

    void loadSelectedPhoto();
});

onBeforeUnmount(() => {
    if (lutDebounce.value !== null) {
        window.clearTimeout(lutDebounce.value);
    }

    if (worker.value !== null) {
        worker.value.postMessage({
            type: 'CancelGeneration',
            requestId: lutRequestId.value,
        } satisfies LutWorkerRequest);
        worker.value.terminate();
        worker.value = null;
    }

    renderer.value?.dispose();
    renderer.value = null;
});

watch(selectedPhoto, () => {
    void loadSelectedPhoto();
});

watch(mode, async () => {
    await nextTick();
    startRenderer();
    await loadSelectedPhoto();
});

watch(parametersWithoutIntensityKey, () => {
    if (useFallback.value) {
        void renderFallback();

        return;
    }

    scheduleLutGeneration();
});

watch(
    () => props.parameters.intensity,
    (value) => {
        renderer.value?.setIntensity(value);

        if (useFallback.value) {
            void renderFallback();
        }
    },
);
</script>

<template>
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-stone-950">Preview</h2>
                <p class="mt-1 text-sm text-stone-600" aria-live="polite">
                    {{ status }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    :aria-pressed="mode === 'transformed'"
                    @click="mode = 'transformed'"
                >
                    Transformed
                </button>
                <button
                    type="button"
                    class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    :aria-pressed="mode === 'compare'"
                    @click="mode = 'compare'"
                >
                    Compare
                </button>
                <button
                    type="button"
                    class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    :aria-pressed="mode === 'side-by-side'"
                    @click="mode = 'side-by-side'"
                >
                    Side by side
                </button>
            </div>
        </div>

        <div
            ref="container"
            class="min-h-72 overflow-hidden rounded-lg border border-stone-200 bg-stone-950"
            @pointerdown="holdingOriginal = true"
            @pointerup="holdingOriginal = false"
            @pointerleave="holdingOriginal = false"
        >
            <div
                v-if="selectedPhoto === null"
                class="grid min-h-72 place-items-center p-6 text-center text-sm text-stone-300"
            >
                Upload a watermarked preview photo to begin.
            </div>
            <div
                v-else-if="mode === 'side-by-side'"
                class="grid gap-1 bg-stone-950 p-1 md:grid-cols-2"
            >
                <img
                    v-if="selectedPhoto.preview_url"
                    :src="selectedPhoto.preview_url"
                    :alt="`${selectedPhoto.original_name} original preview`"
                    class="h-full w-full object-contain"
                />
                <canvas
                    ref="transformedCanvas"
                    class="mx-auto block max-w-full"
                    aria-label="Transformed watermarked preview"
                />
            </div>
            <div v-else class="relative grid min-h-72 place-items-center">
                <img
                    v-if="selectedPhoto.preview_url"
                    :src="selectedPhoto.preview_url"
                    :alt="`${selectedPhoto.original_name} original preview`"
                    class="max-h-full max-w-full object-contain"
                    :class="{
                        'opacity-100': holdingOriginal,
                        'opacity-80': !holdingOriginal,
                    }"
                />
                <div
                    class="absolute inset-0 grid place-items-center overflow-hidden"
                    :style="{
                        clipPath: holdingOriginal
                            ? 'inset(0 100% 0 0)'
                            : clipStyle(),
                    }"
                >
                    <canvas
                        ref="transformedCanvas"
                        class="block max-w-full"
                        aria-label="Transformed watermarked preview"
                    />
                </div>
            </div>
        </div>

        <label
            v-if="mode === 'compare'"
            class="grid gap-1 text-sm font-medium text-stone-800"
        >
            Before / after split
            <input
                v-model.number="comparison"
                type="range"
                min="0"
                max="100"
                step="1"
                class="accent-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            />
        </label>

        <p
            v-if="useFallback"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
        >
            Compatibility preview is being used because WebGL 2 preview is
            unavailable.
        </p>
        <p v-else class="text-xs text-stone-500">
            Renderer:
            {{ rendererState === 'ready' ? 'WebGL 2' : rendererState }}
        </p>
    </section>
</template>
