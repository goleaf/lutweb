<script setup lang="ts">
import { computed } from 'vue';

import type { WizardProjectPhoto } from '@/types/lut-wizard';

const props = defineProps<{
    projectId: string;
    photos: WizardProjectPhoto[];
    maximum: number;
    selectedPhotoId: string | null;
}>();

const emit = defineEmits<{
    uploaded: [photo: WizardProjectPhoto];
    deleted: [photoId: string];
    select: [photoId: string];
    error: [message: string];
}>();

const csrf =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const slots = computed(() =>
    Array.from({ length: props.maximum }, (_, index) => {
        const slot = index + 1;

        return {
            slot,
            photo:
                props.photos.find((photo) => photo.sort_order === slot) ?? null,
        };
    }),
);

async function upload(slot: number, event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (file === null) {
        return;
    }

    const formData = new FormData();
    formData.append('photo', file);

    const response = await fetch(`/custom-lut/${props.projectId}/photos`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: formData,
    });

    if (!response.ok) {
        emit('error', 'Photo upload failed.');

        return;
    }

    const payload = (await response.json()) as { photo: WizardProjectPhoto };
    emit('uploaded', payload.photo);
    emit('select', payload.photo.id);
}

async function remove(photo: WizardProjectPhoto): Promise<void> {
    const response = await fetch(photo.delete_url, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
    });

    if (!response.ok) {
        emit('error', 'Photo removal failed.');

        return;
    }

    emit('deleted', photo.id);
}
</script>

<template>
    <section class="space-y-3">
        <div>
            <h2 class="text-sm font-semibold text-stone-950">Photos</h2>
            <p class="mt-1 text-sm leading-6 text-stone-600">
                Upload up to three photos to test the same LUT across different
                scenes. Photos and previews are automatically deleted one hour
                after upload.
            </p>
            <p class="mt-1 text-xs text-stone-500">
                JPEG, PNG, or WebP. Max 20 MB. Minimum 320 x 320.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <article
                v-for="item in slots"
                :key="item.slot"
                class="min-h-36 rounded-lg border border-stone-200 bg-white p-3"
                :class="{
                    'ring-2 ring-teal-700': item.photo?.id === selectedPhotoId,
                }"
            >
                <template v-if="item.photo">
                    <button
                        type="button"
                        class="block w-full text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        :disabled="item.photo.status !== 'ready'"
                        @click="
                            item.photo.status === 'ready'
                                ? emit('select', item.photo.id)
                                : null
                        "
                    >
                        <img
                            v-if="item.photo.preview_url"
                            :src="item.photo.preview_url"
                            :alt="item.photo.original_name"
                            class="aspect-[4/3] w-full rounded-md object-cover"
                            loading="lazy"
                        />
                        <div
                            v-else
                            class="grid aspect-[4/3] place-items-center rounded-md bg-stone-100 text-sm text-stone-600"
                        >
                            {{
                                item.photo.status === 'expired'
                                    ? 'Preview expired'
                                    : item.photo.status === 'failed'
                                      ? 'Failed'
                                      : 'Processing'
                            }}
                        </div>
                        <span
                            class="mt-2 block truncate text-xs font-medium text-stone-700"
                        >
                            {{ item.photo.original_name }}
                        </span>
                    </button>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <span class="text-xs text-stone-500 capitalize">{{
                            item.photo.status
                        }}</span>
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 px-2 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            @click="remove(item.photo)"
                        >
                            Remove
                        </button>
                    </div>
                    <p
                        v-if="item.photo.failure_message"
                        class="mt-2 text-xs text-red-700"
                    >
                        {{ item.photo.failure_message }}
                    </p>
                </template>
                <template v-else>
                    <label
                        class="grid min-h-28 cursor-pointer place-items-center rounded-md border border-dashed border-stone-300 bg-stone-50 p-3 text-center text-sm font-semibold text-stone-700 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-teal-700 hover:bg-white"
                    >
                        <span>Upload photo {{ item.slot }}</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="upload(item.slot, $event)"
                        />
                    </label>
                </template>
            </article>
        </div>
    </section>
</template>
