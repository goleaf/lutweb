<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppIcon from '@/components/AppIcon.vue';

const props = withDefaults(
    defineProps<{
        termsUrl: string;
        licenseUrl: string;
        refundPolicyUrl: string;
        termsVersion?: string | null;
        licenseVersion?: string | null;
        refundPolicyVersion?: string | null;
        digitalDeliveryConsentVersion?: string | null;
        showVersions?: boolean;
        description?: string;
    }>(),
    {
        termsVersion: null,
        licenseVersion: null,
        refundPolicyVersion: null,
        digitalDeliveryConsentVersion: null,
        showVersions: false,
        description:
            'All sales of digital products are final except where a refund or another remedy is required by applicable law.',
    },
);

const termsAccepted = defineModel<boolean>('termsAccepted', {
    required: true,
});
const digitalDeliveryAccepted = defineModel<boolean>(
    'digitalDeliveryAccepted',
    {
        required: true,
    },
);

const hasVersionDetails = computed(
    () =>
        props.showVersions &&
        (props.termsVersion !== null ||
            props.licenseVersion !== null ||
            props.refundPolicyVersion !== null ||
            props.digitalDeliveryConsentVersion !== null),
);
</script>

<template>
    <section class="rounded-lg border border-stone-200 bg-white p-5">
        <h2
            class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
        >
            <AppIcon name="shield" class="size-4 text-teal-800" />
            Legal consent
        </h2>
        <p class="mt-2 text-sm leading-6 text-stone-600">
            {{ description }}
        </p>
        <p
            v-if="hasVersionDetails"
            class="mt-2 text-xs leading-5 text-stone-500"
        >
            Terms version {{ termsVersion ?? 'current' }}. License version
            {{ licenseVersion ?? 'current' }}. Refund Policy version
            {{ refundPolicyVersion ?? 'current' }}. Digital delivery consent
            version {{ digitalDeliveryConsentVersion ?? 'current' }}.
        </p>

        <label class="mt-4 flex gap-3 text-sm text-stone-700">
            <input
                v-model="termsAccepted"
                type="checkbox"
                class="mt-1 size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-700"
            />
            <span>
                I agree to the
                <Link
                    :href="termsUrl"
                    class="font-medium text-teal-800 underline-offset-4 hover:underline"
                    >Terms of Sale</Link
                >
                and
                <Link
                    :href="licenseUrl"
                    class="font-medium text-teal-800 underline-offset-4 hover:underline"
                    >License Agreement</Link
                >.
            </span>
        </label>

        <label class="mt-4 flex gap-3 text-sm text-stone-700">
            <input
                v-model="digitalDeliveryAccepted"
                type="checkbox"
                class="mt-1 size-4 rounded border-stone-300 text-teal-700 focus:ring-teal-700"
            />
            <span>
                I request immediate access to this digital product and
                acknowledge that, where permitted by applicable law, I lose my
                withdrawal right once digital delivery begins.
            </span>
        </label>

        <p class="mt-4 text-xs leading-5 text-stone-500">
            Refund Policy:
            <Link
                :href="refundPolicyUrl"
                class="font-medium text-teal-800 underline-offset-4 hover:underline"
                >review policy</Link
            >.
        </p>
    </section>
</template>
