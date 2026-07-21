<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import BeforeAfterComparison from '@/components/storefront/BeforeAfterComparison.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type {
    PublicLutTestUpload,
    PublicProductExample,
    PublicTesterProduct,
    ResponsiveImage,
} from '@/types/storefront';

const props = defineProps<{
    product: PublicTesterProduct;
    test: PublicLutTestUpload | null;
}>();

const form = useForm<{ photo: File | null }>('LutTesterUploadForm', {
    photo: null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const isDragging = ref(false);
const intensity = ref(100);
let pollTimer: number | null = null;

const isProcessing = computed(
    () =>
        props.test?.status === 'queued' || props.test?.status === 'processing',
);

const comparisonExample = computed<PublicProductExample | null>(() => {
    if (
        props.test?.status !== 'ready' ||
        !props.test.before_url ||
        !props.test.after_url
    ) {
        return null;
    }

    return {
        id: 1,
        title: `${props.product.name} test preview`,
        before: previewImage(
            props.test.before_url,
            `Watermarked original preview for ${props.product.name}`,
        ),
        after: previewImage(
            props.test.after_url,
            `Watermarked LUT result preview for ${props.product.name}`,
        ),
    };
});

const afterOpacity = computed(() => intensity.value / 100);

function previewImage(url: string, altText: string): ResponsiveImage {
    return {
        alt_text: altText,
        aspect_ratio: '4 / 3',
        fallback_jpeg_url: url,
        webp_srcset: '',
        jpeg_srcset: '',
        width: null,
        height: null,
        placeholder_color: '#1c1917',
        credit: null,
    };
}

function chooseFile(): void {
    fileInput.value?.click();
}

function setFile(file: File | null): void {
    form.photo = file;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (file && file.type.startsWith('image/')) {
        previewUrl.value = URL.createObjectURL(file);
    }
}

function handleFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    setFile(input.files?.[0] ?? null);
}

function handleDrop(event: DragEvent): void {
    isDragging.value = false;
    setFile(event.dataTransfer?.files.item(0) ?? null);
}

function submit(): void {
    form.post(props.product.try_url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            setFile(null);
            form.reset();
        },
    });
}

function deleteTest(): void {
    if (!props.test?.delete_url || !props.test.can_delete) {
        return;
    }

    router.delete(props.test.delete_url, {
        preserveScroll: true,
    });
}

function startPolling(): void {
    stopPolling();

    if (!isProcessing.value) {
        return;
    }

    pollTimer = window.setInterval(() => {
        router.reload({
            only: ['test'],
        });
    }, 2000);
}

function stopPolling(): void {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(
    isProcessing,
    (processing) => {
        if (processing) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopPolling();

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});
</script>

<template>
    <PublicLayout>
        <Head :title="`Try ${product.name} on Your Photo`">
            <meta
                name="description"
                :content="`Upload one photo to preview ${product.name} with a watermarked LUT test.`"
            />
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_22rem] lg:px-8"
            >
                <div>
                    <p class="text-sm font-semibold text-teal-800">
                        Photo tester
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold text-stone-950">
                        Try {{ product.name }} on Your Photo
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">
                        Upload a photo to preview this LUT. Your photo and
                        generated previews are automatically deleted after one
                        hour.
                    </p>
                </div>

                <div
                    class="flex items-center gap-4 rounded-lg border border-stone-200 bg-stone-50 p-4"
                >
                    <img
                        v-if="product.cover"
                        :src="product.cover.url"
                        :alt="product.cover.alt_text"
                        :width="product.cover.width ?? undefined"
                        :height="product.cover.height ?? undefined"
                        class="size-20 rounded-md object-cover"
                    />
                    <span
                        v-else
                        class="size-20 rounded-md bg-[linear-gradient(135deg,#292524,#0f766e)]"
                        aria-hidden="true"
                    />
                    <div>
                        <p class="text-sm font-semibold text-stone-950">
                            {{ product.formatted_price }}
                        </p>
                        <Link
                            :href="product.url"
                            class="mt-2 inline-flex rounded-sm text-sm font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                        >
                            Back to LUT
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:px-8"
        >
            <form
                v-if="
                    !test ||
                    test.status === 'expired' ||
                    test.status === 'failed'
                "
                class="grid gap-5 rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
                @submit.prevent="submit"
            >
                <div>
                    <h2 class="text-xl font-semibold text-stone-950">
                        Upload a photo to preview this LUT.
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Accepted formats are JPG, PNG, and WebP. Maximum size is
                        20 MB, minimum dimensions are 320 by 320 pixels.
                    </p>
                </div>

                <div
                    class="grid gap-4 rounded-lg border border-dashed p-6 text-center transition"
                    :class="
                        isDragging
                            ? 'border-teal-700 bg-teal-50'
                            : 'border-stone-300 bg-stone-50'
                    "
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop"
                >
                    <input
                        ref="fileInput"
                        name="photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        class="sr-only"
                        @change="handleFileChange"
                    />

                    <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Selected photo preview"
                        class="mx-auto max-h-64 rounded-md object-contain"
                    />
                    <p v-else class="text-sm text-stone-600">
                        Drop one image here, or choose a file.
                    </p>

                    <button
                        type="button"
                        class="mx-auto rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="chooseFile"
                    >
                        Choose photo
                    </button>

                    <p
                        v-if="form.photo"
                        class="text-sm font-medium text-stone-800"
                    >
                        {{ form.photo.name }}
                    </p>
                </div>

                <InputError :message="form.errors.photo" />

                <div v-if="form.progress" class="grid gap-2" aria-live="polite">
                    <div class="h-2 overflow-hidden rounded-full bg-stone-200">
                        <div
                            class="h-full bg-teal-700 transition-[width] motion-reduce:transition-none"
                            :style="{
                                width: `${form.progress.percentage ?? 0}%`,
                            }"
                        />
                    </div>
                    <p class="text-sm text-stone-600">
                        Uploading {{ form.progress.percentage ?? 0 }}%
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing || !form.photo"
                        class="rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
                    >
                        {{
                            form.processing
                                ? 'Uploading photo...'
                                : 'Upload and preview'
                        }}
                    </button>
                    <Link
                        :href="product.url"
                        class="rounded-md px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Back to LUT
                    </Link>
                </div>
            </form>

            <section
                v-if="isProcessing && test"
                class="rounded-lg border border-stone-200 bg-white p-6"
                aria-live="polite"
            >
                <h2 class="text-xl font-semibold text-stone-950">
                    Processing your preview
                </h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Your photo is queued for private server-side processing. You
                    can leave this page open while the preview is prepared.
                </p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-stone-200">
                    <div
                        class="h-full w-1/2 animate-pulse rounded-full bg-teal-700 motion-reduce:animate-none"
                    />
                </div>
            </section>

            <section
                v-if="test?.status === 'ready' && comparisonExample"
                class="grid gap-6"
                aria-labelledby="ready-heading"
            >
                <div>
                    <h2
                        id="ready-heading"
                        class="text-2xl font-semibold text-stone-950"
                    >
                        Watermarked preview
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Adjust the client-side intensity preview without
                        creating another file.
                    </p>
                </div>

                <div
                    class="grid gap-4 rounded-lg border border-stone-200 bg-white p-4"
                >
                    <div
                        class="relative aspect-[4/3] overflow-hidden rounded-md bg-stone-900"
                    >
                        <img
                            :src="comparisonExample.before.fallback_jpeg_url"
                            :alt="comparisonExample.before.alt_text"
                            class="h-full w-full object-contain"
                        />
                        <img
                            :src="comparisonExample.after.fallback_jpeg_url"
                            :alt="comparisonExample.after.alt_text"
                            class="absolute inset-0 h-full w-full object-contain"
                            :style="{ opacity: afterOpacity }"
                        />
                    </div>

                    <label
                        class="grid gap-2 text-sm font-medium text-stone-800"
                    >
                        LUT intensity: {{ intensity }}%
                        <input
                            v-model.number="intensity"
                            type="range"
                            min="0"
                            max="100"
                            class="accent-teal-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                        />
                    </label>
                </div>

                <BeforeAfterComparison :example="comparisonExample" />

                <div class="flex flex-wrap gap-3">
                    <Link
                        :href="product.try_url"
                        class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 hover:border-stone-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Test another photo
                    </Link>
                    <button
                        type="button"
                        class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
                        @click="deleteTest"
                    >
                        Delete this test
                    </button>
                    <button
                        type="button"
                        disabled
                        class="rounded-md bg-stone-200 px-4 py-2 text-sm font-semibold text-stone-500"
                    >
                        {{ product.is_free ? 'Get Free LUT' : 'Buy Now' }}
                        <span class="ml-2 text-xs text-amber-800">
                            Coming soon
                        </span>
                    </button>
                </div>
            </section>

            <section
                v-if="test?.status === 'failed'"
                class="rounded-lg border border-amber-200 bg-amber-50 p-5"
            >
                <h2 class="text-xl font-semibold text-amber-950">
                    Preview failed
                </h2>
                <p class="mt-2 text-sm text-amber-900">
                    {{
                        test.failure_message ??
                        'We could not process this image.'
                    }}
                </p>
                <button
                    type="button"
                    class="mt-4 rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700"
                    @click="deleteTest"
                >
                    Delete this test
                </button>
            </section>

            <section
                v-if="test?.status === 'expired'"
                class="rounded-lg border border-stone-200 bg-white p-5"
            >
                <h2 class="text-xl font-semibold text-stone-950">
                    Preview expired
                </h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    This temporary preview is no longer available. You can start
                    a new test with another upload.
                </p>
                <button
                    type="button"
                    class="mt-4 rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    @click="deleteTest"
                >
                    Delete expired test
                </button>
            </section>
        </section>
    </PublicLayout>
</template>
