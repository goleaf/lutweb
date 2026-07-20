<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthShell from '@/components/AuthShell.vue';
import InputError from '@/components/InputError.vue';
import { register } from '@/routes';
import { store as loginStore } from '@/routes/login';
import { request as passwordRequest } from '@/routes/password';

defineProps<{
    status?: string | null;
}>();

const form = useForm('LoginForm', {
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.submit(loginStore(), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Log in" />

    <AuthShell
        title="Log in"
        description="Access your LUT Web account to download purchases and manage future marketplace tools."
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
                    autocomplete="current-password"
                    required
                    class="mt-2 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base text-stone-950 shadow-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input
                        v-model="form.remember"
                        name="remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-stone-300 text-teal-700 focus:ring-2 focus:ring-teal-700/30"
                    />
                    Remember me
                </label>

                <Link
                    :href="passwordRequest()"
                    class="rounded-sm text-sm font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    Forgot password?
                </Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex w-full items-center justify-center rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
            >
                {{ form.processing ? 'Logging in...' : 'Log in' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">
            New to LUT Web?
            <Link
                :href="register()"
                class="font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
            >
                Create an account
            </Link>
        </p>
    </AuthShell>
</template>
