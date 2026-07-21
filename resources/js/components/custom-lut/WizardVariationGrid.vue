<script setup lang="ts">
import type { WizardProjectVariant } from '@/types/lut-wizard';

defineProps<{
    variants: WizardProjectVariant[];
    previewingHash: string | null;
    selectedHash: string;
    busy: boolean;
}>();

const emit = defineEmits<{
    generateFresh: [];
    generateMore: [];
    preview: [variant: WizardProjectVariant];
    use: [variant: WizardProjectVariant];
    current: [];
}>();

const labels = ['Variant A', 'Variant B', 'Variant C', 'Variant D'];
</script>

<template>
    <section class="space-y-3">
        <div>
            <h2 class="text-sm font-semibold text-stone-950">Variations</h2>
            <p class="mt-1 text-sm text-stone-600">
                Generate four controlled variations, preview locally, then
                explicitly save one.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
                :disabled="busy"
                @click="emit('generateFresh')"
            >
                Generate 4 Variations
            </button>
            <button
                type="button"
                class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                :disabled="busy"
                @click="emit('generateMore')"
            >
                Generate 4 More Like This
            </button>
            <button
                v-if="previewingHash"
                type="button"
                class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                @click="emit('current')"
            >
                Current Look
            </button>
        </div>

        <div v-if="variants.length > 0" class="grid gap-3 sm:grid-cols-2">
            <article
                v-for="(variant, index) in variants"
                :key="variant.id"
                class="rounded-lg border border-stone-200 bg-white p-4"
                :class="{
                    'ring-2 ring-teal-700':
                        previewingHash === variant.parameters_hash ||
                        variant.selected,
                }"
            >
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-semibold text-stone-950">
                        {{ labels[index] ?? `Variant ${variant.position}` }}
                    </h3>
                    <span class="text-xs font-semibold text-stone-500">
                        {{
                            variant.selected
                                ? 'Selected'
                                : previewingHash === variant.parameters_hash
                                  ? 'Previewing'
                                  : 'Ready'
                        }}
                    </span>
                </div>
                <p class="mt-2 font-mono text-xs text-stone-500">
                    {{ variant.parameters_hash.slice(0, 10) }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="emit('preview', variant)"
                    >
                        Preview
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="emit('use', variant)"
                    >
                        Use This Look
                    </button>
                </div>
            </article>
        </div>
        <p
            v-else
            class="rounded-lg border border-dashed border-stone-300 bg-white p-5 text-sm text-stone-600"
        >
            No variations generated yet.
        </p>
    </section>
</template>
