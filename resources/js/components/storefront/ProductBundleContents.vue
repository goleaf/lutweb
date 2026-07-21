<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import ResponsivePicture from '@/components/storefront/ResponsivePicture.vue';
import type { PublicBundleItem } from '@/types/storefront';

defineProps<{
    items: PublicBundleItem[];
}>();
</script>

<template>
    <div>
        <ul v-if="items.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-3 rounded-lg border border-stone-200 bg-white p-3"
            >
                <ResponsivePicture
                    v-if="item.cover?.image"
                    :image="item.cover.image"
                    sizes="64px"
                    class="size-16 rounded-md object-cover"
                    loading="lazy"
                />
                <img
                    v-else-if="item.cover?.url"
                    :src="item.cover.url"
                    :alt="item.cover.alt_text"
                    :width="item.cover.width ?? undefined"
                    :height="item.cover.height ?? undefined"
                    class="size-16 rounded-md object-cover"
                    loading="lazy"
                    decoding="async"
                />
                <span
                    v-else
                    class="size-16 rounded-md bg-[linear-gradient(135deg,#292524,#0f766e)]"
                    aria-hidden="true"
                />
                <Link
                    v-if="item.url"
                    :href="item.url"
                    class="rounded-sm font-semibold text-stone-950 underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    {{ item.name }}
                </Link>
                <span v-else class="font-semibold text-stone-950">
                    {{ item.name }}
                </span>
            </li>
        </ul>
        <p v-else class="mt-3 text-sm text-stone-600">
            Bundle components are being prepared.
        </p>
    </div>
</template>
