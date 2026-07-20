<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthShell from '@/components/AuthShell.vue';
import InputError from '@/components/InputError.vue';
import { login } from '@/routes';
import { email as passwordEmail } from '@/routes/password';

defineProps<{
    status?: string | null;
}>();

const form = useForm('ForgotPasswordForm', {
    email: '',
});

function submit(): void {
    form.submit(passwordEmail());
}
</script>

<template>
    <Head title="Forgot password" />

    <AuthShell
        title="Reset your password"
        description="Enter the email address for your LUT Web account and we will send a reset link."
    >
        <div
            v-if="status"
            class="mb-5 rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm font-medium text-teal-900"
        >
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label
                    for="email"
                    class="block text-sm font-medium text-stone-800"
                >
                    Email address
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    required
                    class="mt-2 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base text-stone-950 shadow-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                />
                <InputError :message="form.errors.email" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex w-full items-center justify-center rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
            >
                {{ form.processing ? 'Sending link...' : 'Send reset link' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">
            Remembered it?
            <Link
                :href="login()"
                class="font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
            >
                Log in
            </Link>
        </p>
    </AuthShell>
</template>
