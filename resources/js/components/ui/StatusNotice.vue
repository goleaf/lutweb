<script setup lang="ts">
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';

type NoticeTone = 'neutral' | 'success' | 'warning' | 'danger';

const props = withDefaults(
    defineProps<{
        icon: AppIconName;
        tone?: NoticeTone;
        message?: string;
        compact?: boolean;
        live?: boolean;
        role?: 'status' | 'alert';
    }>(),
    {
        tone: 'neutral',
        message: undefined,
        compact: false,
        live: false,
        role: undefined,
    },
);

const toneClass = computed(() => {
    if (props.tone === 'success') {
        return 'border-teal-200 bg-teal-50 text-teal-950';
    }

    if (props.tone === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    if (props.tone === 'danger') {
        return 'border-red-200 bg-red-50 text-red-900';
    }

    return 'border-stone-200 bg-stone-50 text-stone-700';
});

const iconClass = computed(() => {
    if (props.tone === 'success') {
        return 'text-teal-800';
    }

    if (props.tone === 'warning') {
        return 'text-amber-700';
    }

    if (props.tone === 'danger') {
        return 'text-red-700';
    }

    return 'text-stone-500';
});
</script>

<template>
    <div
        :role="role"
        :aria-live="live ? 'polite' : undefined"
        :class="[
            'flex items-start gap-2 border text-sm',
            compact ? 'rounded-md px-3 py-2' : 'rounded-lg p-4 leading-6',
            toneClass,
        ]"
    >
        <AppIcon
            :name="icon"
            :class="['mt-0.5 size-4 shrink-0', iconClass]"
        />
        <span>
            <slot>{{ message }}</slot>
        </span>
    </div>
</template>
