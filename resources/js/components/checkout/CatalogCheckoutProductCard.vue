<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import PackageContentsList from '@/components/storefront/PackageContentsList.vue';

defineProps<{
    product: {
        name: string;
        type_label: string;
        cover: { url: string; alt_text: string } | null;
        version: string | null;
        package_contents: string[];
    };
}>();
</script>

<template>
    <div class="rounded-lg border border-stone-200 bg-white p-5">
        <div class="flex gap-4">
            <img
                v-if="product.cover"
                :src="product.cover.url"
                :alt="product.cover.alt_text"
                class="size-24 rounded-md object-cover"
            />
            <span
                v-else
                class="grid size-24 shrink-0 place-items-center rounded-md bg-stone-200"
                aria-hidden="true"
            >
                <AppIcon name="image" class="size-8 text-stone-500" />
            </span>
            <div>
                <p
                    class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-teal-800 uppercase"
                >
                    <AppIcon name="package" class="size-3.5" />
                    {{ product.type_label }}
                </p>
                <h2 class="mt-1 text-xl font-semibold text-stone-950">
                    {{ product.name }}
                </h2>
                <p class="mt-1 text-sm text-stone-600">
                    Version {{ product.version ?? 'current' }}
                </p>
            </div>
        </div>

        <PackageContentsList
            class="mt-5"
            :items="product.package_contents"
            item-class="border-stone-200 bg-stone-50 text-stone-700"
        />
    </div>
</template>
