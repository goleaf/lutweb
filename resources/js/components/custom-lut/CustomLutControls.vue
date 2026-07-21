<script setup lang="ts">
import { computed } from 'vue';

import type {
    CanonicalLutParameters,
    LutParameterDefinition,
    LutParameterKey,
} from '@/types/lut-wizard';

const props = defineProps<{
    parameters: CanonicalLutParameters;
    schema: LutParameterDefinition[];
    minimums: CanonicalLutParameters | null;
    maximums: CanonicalLutParameters | null;
    disabled: boolean;
}>();

const emit = defineEmits<{
    update: [parameters: CanonicalLutParameters];
    commit: [];
    resetGroup: [group: LutParameterDefinition['group']];
    resetAll: [];
}>();

const grouped = computed(() => {
    const groups: Record<string, LutParameterDefinition[]> = {};

    for (const definition of props.schema) {
        groups[definition.group] ??= [];
        groups[definition.group].push(definition);
    }

    return groups;
});

function valueFor(key: LutParameterKey): number {
    return props.parameters[key];
}

function minimumFor(definition: LutParameterDefinition): number {
    return props.minimums?.[definition.key] ?? definition.minimum;
}

function maximumFor(definition: LutParameterDefinition): number {
    return props.maximums?.[definition.key] ?? definition.maximum;
}

function display(definition: LutParameterDefinition, value: number): string {
    const decimals = definition.display_scale === 100 ? 2 : 1;
    const number = value / definition.display_scale;
    const sign = number > 0 && definition.minimum < 0 ? '+' : '';

    return `${sign}${number.toFixed(decimals)}${definition.unit ? ` ${definition.unit}` : ''}`;
}

function updateValue(definition: LutParameterDefinition, value: string): void {
    const next = {
        ...props.parameters,
        [definition.key]: Number.parseInt(value, 10),
    };

    emit('update', next);
}

function resetValue(definition: LutParameterDefinition): void {
    const value = Math.min(
        maximumFor(definition),
        Math.max(minimumFor(definition), definition.default),
    );
    emit('update', {
        ...props.parameters,
        [definition.key]: value,
    });
    emit('commit');
}

function resetGroup(group: string): void {
    emit('resetGroup', group as LutParameterDefinition['group']);
}
</script>

<template>
    <div class="space-y-5">
        <section
            v-for="(definitions, group) in grouped"
            :key="group"
            class="border-b border-stone-200 pb-5 last:border-b-0 last:pb-0"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-stone-950">
                    {{ group }}
                </h3>
                <button
                    type="button"
                    class="rounded-md border border-stone-300 px-2 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                    :disabled="disabled"
                    @click="resetGroup(group)"
                >
                    Reset section
                </button>
            </div>

            <div class="grid gap-4">
                <div
                    v-for="definition in definitions"
                    :key="definition.key"
                    class="grid gap-2"
                >
                    <div class="flex items-center justify-between gap-3">
                        <label
                            :for="`control-${definition.key}`"
                            class="text-sm font-medium text-stone-800"
                        >
                            {{ definition.label }}
                        </label>
                        <span class="font-mono text-xs text-stone-600">
                            {{ display(definition, valueFor(definition.key)) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            :id="`control-${definition.key}`"
                            type="range"
                            class="w-full accent-teal-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            :min="minimumFor(definition)"
                            :max="maximumFor(definition)"
                            :step="definition.ui_step"
                            :value="valueFor(definition.key)"
                            :disabled="disabled"
                            :style="
                                definition.key.includes('hue')
                                    ? 'background: linear-gradient(90deg, #ef4444, #f59e0b, #eab308, #22c55e, #06b6d4, #3b82f6, #a855f7, #ef4444);'
                                    : undefined
                            "
                            @input="
                                updateValue(
                                    definition,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                            @change="emit('commit')"
                        />
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 px-2 py-1 text-xs font-semibold text-stone-700 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                            :disabled="disabled"
                            @click="resetValue(definition)"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <button
            type="button"
            class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
            :disabled="disabled"
            @click="emit('resetAll')"
        >
            Reset all
        </button>
    </div>
</template>
