<script setup lang="ts">
import type { WizardAutosaveState } from '@/types/lut-wizard';

defineProps<{
    state: WizardAutosaveState;
    savedAt: string | null;
}>();

const labels: Record<WizardAutosaveState, string> = {
    saved: 'Saved',
    unsaved: 'Unsaved changes',
    saving: 'Saving...',
    failed: 'Save failed',
    conflict: 'Updated in another tab',
};
</script>

<template>
    <p
        class="rounded-md border px-3 py-1.5 text-xs font-semibold"
        :class="{
            'border-emerald-200 bg-emerald-50 text-emerald-800':
                state === 'saved',
            'border-amber-200 bg-amber-50 text-amber-900':
                state === 'unsaved' || state === 'saving',
            'border-red-200 bg-red-50 text-red-800':
                state === 'failed' || state === 'conflict',
        }"
        aria-live="polite"
    >
        {{ labels[state] }}
        <span v-if="state === 'saved' && savedAt" class="font-normal">
            · {{ new Date(savedAt).toLocaleTimeString() }}
        </span>
    </p>
</template>
