<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';
import type { WizardStyle } from '@/types/lut-wizard';

defineProps<{
    styles: WizardStyle[];
    selectedStyleId: string | null;
}>();

const emit = defineEmits<{
    select: [style: WizardStyle | null];
}>();
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="palette" class="size-4 text-teal-800" />
                    Starting Style
                </h2>
                <p class="mt-1 text-sm text-stone-600">
                    Choose an administrator-managed look, or start from neutral.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                @click="emit('select', null)"
            >
                <AppIcon name="reset" class="size-4" />
                Start from Neutral
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <article
                v-for="style in styles"
                :key="style.id"
                class="rounded-lg border border-stone-200 bg-white p-4"
                :class="{
                    'ring-2 ring-teal-700': style.id === selectedStyleId,
                }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3
                            class="inline-flex items-center gap-2 font-semibold text-stone-950"
                        >
                            <AppIcon
                                name="palette"
                                class="size-4 text-teal-800"
                            />
                            {{ style.name }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            {{
                                style.description ??
                                'Controlled custom LUT starting style.'
                            }}
                        </p>
                    </div>
                    <span
                        v-if="style.id === selectedStyleId"
                        class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2 py-1 text-xs font-semibold text-teal-800"
                    >
                        <AppIcon name="check-circle" class="size-3.5" />
                        Selected
                    </span>
                </div>
                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-2 rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    @click="emit('select', style)"
                >
                    <AppIcon name="check-circle" class="size-4" />
                    Use This Style
                </button>
            </article>
        </div>
    </section>
</template>
