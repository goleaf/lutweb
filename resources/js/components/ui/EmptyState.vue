<script setup lang="ts">
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';

const props = withDefaults(
    defineProps<{
        icon: AppIconName;
        title: string;
        message?: string;
        variant?: 'solid' | 'dashed';
        iconClass?: string;
    }>(),
    {
        message: undefined,
        variant: 'solid',
        iconClass: 'text-stone-400',
    },
);

const frameClass = computed(() =>
    props.variant === 'dashed'
        ? 'border-dashed border-stone-300'
        : 'border-stone-200 shadow-sm',
);
</script>

<template>
    <div
        role="status"
        :class="[
            'rounded-lg border bg-white px-6 py-10 text-center text-sm text-stone-600',
            frameClass,
        ]"
    >
        <AppIcon
            :name="icon"
            :class="['mx-auto mb-3 size-8', iconClass]"
            :stroke-width="1.6"
        />
        <h3 class="text-base font-semibold text-stone-950">
            {{ title }}
        </h3>
        <p v-if="message" class="mt-2 leading-6">
            {{ message }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
