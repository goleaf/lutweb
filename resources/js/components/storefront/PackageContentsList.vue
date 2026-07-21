<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';

withDefaults(
    defineProps<{
        items: string[];
        columns?: boolean;
        emptyMessage?: string | null;
        itemClass?: string;
    }>(),
    {
        columns: false,
        emptyMessage: null,
        itemClass: 'border-stone-200 bg-white font-medium text-stone-700',
    },
);
</script>

<template>
    <div>
        <ul
            v-if="items.length > 0"
            :class="['grid gap-2', columns ? 'sm:grid-cols-2' : '']"
        >
            <li
                v-for="item in items"
                :key="item"
                :class="[
                    'inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
                    itemClass,
                ]"
            >
                <AppIcon
                    name="check-circle"
                    class="size-4 shrink-0 text-teal-800"
                />
                {{ item }}
            </li>
        </ul>

        <p
            v-else-if="emptyMessage"
            class="rounded-md bg-stone-100 p-3 text-sm text-stone-600"
        >
            {{ emptyMessage }}
        </p>
    </div>
</template>
