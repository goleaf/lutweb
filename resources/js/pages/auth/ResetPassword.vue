<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import AuthShell from '@/components/AuthShell.vue';
import InputError from '@/components/InputError.vue';
import { update as passwordUpdate } from '@/routes/password';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm('ResetPasswordForm', {
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.submit(passwordUpdate(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Reset password" />

    <AuthShell
        title="Choose a new password"
        description="Set a fresh password for your LUT Web account."
    >
        <form class="space-y-5" @submit.prevent="submit">
            <input v-model="form.token" name="token" type="hidden" />

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

            <div>
                <label
                    for="password"
                    class="block text-sm font-medium text-stone-800"
                >
                    Password
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="mt-2 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base text-stone-950 shadow-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div>
                <label
                    for="password_confirmation"
                    class="block text-sm font-medium text-stone-800"
                >
                    Confirm password
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="mt-2 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base text-stone-950 shadow-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
            >
                <AppIcon name="key" class="size-4" />
                {{ form.processing ? 'Resetting...' : 'Reset password' }}
            </button>
        </form>
    </AuthShell>
</template>
