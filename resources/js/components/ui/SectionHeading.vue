<script setup lang="ts">
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';
import type { AppIconName } from '@/components/AppIcon.vue';

type HeadingTag = 'h1' | 'h2' | 'h3';
type HeadingSize = 'page' | 'section' | 'compact';

const props = withDefaults(
    defineProps<{
        title: string;
        eyebrow?: string;
        description?: string;
        icon?: AppIconName;
        headingId?: string;
        as?: HeadingTag;
        size?: HeadingSize;
        iconClass?: string;
    }>(),
    {
        eyebrow: undefined,
        description: undefined,
        icon: undefined,
        headingId: undefined,
        as: 'h2',
        size: 'section',
        iconClass: 'text-teal-800',
    },
);

const headingClass = computed(() => {
    if (props.size === 'page') {
        return 'text-3xl font-semibold text-stone-950';
    }

    if (props.size === 'compact') {
        return 'text-base font-semibold text-stone-950';
    }

    return 'text-2xl font-semibold text-stone-950';
});
</script>

<template>
    <div>
        <p
            v-if="eyebrow"
            class="flex w-fit items-center gap-2 text-sm font-semibold text-teal-800"
        >
            <AppIcon v-if="icon" :name="icon" class="size-4" />
            {{ eyebrow }}
        </p>
        <component
            :is="as"
            :id="headingId"
            :class="[
                'flex w-fit items-center gap-2 tracking-normal',
                eyebrow ? 'mt-3' : '',
                headingClass,
            ]"
        >
            <AppIcon
                v-if="icon && !eyebrow"
                :name="icon"
                :class="[
                    size === 'compact' ? 'size-4' : 'size-5',
                    iconClass,
                ]"
            />
            {{ title }}
        </component>
        <p
            v-if="description"
            class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
