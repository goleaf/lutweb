<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthShell from '@/components/AuthShell.vue';
import { logout } from '@/routes';
import { send as verificationSend } from '@/routes/verification';

defineProps<{
    status?: string | null;
}>();

const resendForm = useForm({});
const logoutForm = useForm({});

function resend(): void {
    resendForm.submit(verificationSend());
}

function submitLogout(): void {
    logoutForm.submit(logout());
}
</script>

<template>
    <Head title="Verify email" />

    <AuthShell
        title="Verify your email"
        description="Email verification is required before you can access marketplace account features."
    >
        <div class="space-y-5">
            <p class="text-sm leading-6 text-stone-700">
                We sent a verification link to your email address. Open that
                link to continue to your dashboard.
            </p>

            <div
                v-if="status === 'verification-link-sent'"
                class="rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-900"
            >
                A new verification link has been sent.
            </div>

            <form @submit.prevent="resend">
                <button
                    type="submit"
                    :disabled="resendForm.processing"
                    class="inline-flex w-full items-center justify-center rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
                >
                    {{
                        resendForm.processing
                            ? 'Sending...'
                            : 'Resend verification email'
                    }}
                </button>
            </form>

            <form @submit.prevent="submitLogout">
                <button
                    type="submit"
                    :disabled="logoutForm.processing"
                    class="inline-flex w-full items-center justify-center rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 transition hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                >
                    Log out
                </button>
            </form>
        </div>
    </AuthShell>
</template>
