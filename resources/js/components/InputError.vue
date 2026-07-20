<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p v-if="message" class="mt-2 text-sm font-medium text-red-700">
        {{ message }}
    </p>
</template>
