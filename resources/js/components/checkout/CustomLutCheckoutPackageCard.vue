<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import PackageContentsList from '@/components/storefront/PackageContentsList.vue';
import type { CustomLutCheckoutItem } from '@/types/commerce';

defineProps<{
    item: CustomLutCheckoutItem;
    packageSize: string;
}>();
</script>

<template>
    <div class="space-y-5">
        <section class="rounded-lg border border-stone-200 bg-white p-5">
            <p
                class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide text-teal-800 uppercase"
            >
                <AppIcon name="wand" class="size-3.5" />
                Custom LUT package
            </p>
            <h2 class="mt-2 text-xl font-semibold text-stone-950">
                {{ item.name }}
            </h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-stone-500">Style</dt>
                    <dd class="font-medium text-stone-900">
                        {{ item.style_name || 'Neutral' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-stone-500">Build</dt>
                    <dd class="font-medium text-stone-900">
                        {{ item.version_label }}
                    </dd>
                </div>
                <div>
                    <dt class="text-stone-500">Transform</dt>
                    <dd class="font-medium text-stone-900">
                        {{ item.transform_version }}
                    </dd>
                </div>
                <div>
                    <dt class="text-stone-500">Package</dt>
                    <dd class="font-medium text-stone-900">
                        {{ packageSize }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg border border-stone-200 bg-white p-5">
            <h2
                class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
            >
                <AppIcon name="package" class="size-4 text-teal-800" />
                Generated package contents
            </h2>
            <PackageContentsList
                class="mt-4"
                :items="item.contents"
                columns
                item-class="border-stone-200 bg-stone-50 text-stone-700"
            />
            <p class="mt-4 text-sm leading-6 text-stone-600">
                The package was prepared as an immutable build and will not
                change after purchase.
            </p>
        </section>
    </div>
</template>
