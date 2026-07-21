<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue';

defineProps<{
    subtotal: string;
    tax: string;
    total: string;
    note: string;
    accountEmail: string | null;
}>();
</script>

<template>
    <aside class="h-fit rounded-lg border border-stone-200 bg-white p-5">
        <h2
            class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
        >
            <AppIcon name="receipt" class="size-4 text-teal-800" />
            Order
        </h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-stone-600">Subtotal</dt>
                <dd class="font-medium text-stone-950">
                    {{ subtotal }}
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-stone-600">Tax</dt>
                <dd class="font-medium text-stone-950">
                    {{ tax }}
                </dd>
            </div>
            <div
                class="flex justify-between gap-4 border-t border-stone-200 pt-3 text-base"
            >
                <dt class="font-semibold text-stone-950">Total</dt>
                <dd class="font-semibold text-stone-950">
                    {{ total }}
                </dd>
            </div>
        </dl>

        <p class="mt-4 text-xs leading-5 text-stone-500">
            {{ note }}
        </p>

        <div v-if="$slots.actions" class="mt-5">
            <slot name="actions" />
        </div>

        <slot name="status" />

        <p class="mt-4 text-xs leading-5 text-stone-500">
            Account: {{ accountEmail }}
        </p>
    </aside>
</template>
