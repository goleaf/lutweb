<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';

defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        class="mt-2 flex items-start gap-1.5 text-sm font-medium text-red-700"
    >
        <AppIcon name="alert-circle" class="mt-0.5 size-4 shrink-0" />
        <span>{{ message }}</span>
    </p>
</template>
