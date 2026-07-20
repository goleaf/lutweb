<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthShell from '@/components/AuthShell.vue';
import InputError from '@/components/InputError.vue';
import { login, privacy, terms } from '@/routes';
import { store as registerStore } from '@/routes/register';

defineProps<{
    countries: Record<string, string>;
}>();

const form = useForm('RegisterForm', {
    name: '',
    email: '',
    country_code: '',
    password: '',
    password_confirmation: '',
    accept_terms: false,
    accept_privacy: false,
});

function submit(): void {
    form.submit(registerStore(), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Register" />

    <AuthShell
        title="Create your account"
        description="Register before uploading photos, purchasing products, or downloading LUT files."
    >
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label
                    for="name"
                    class="block text-sm font-medium text-stone-800"
                >
                    Full name
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    name="name"
                    type="text"
                    autocomplete="name"
                    required
                    class="mt-2 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base text-stone-950 shadow-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                />
                <InputError :message="form.errors.name" />
            </div>

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
                    for="country_code"
                    class="block text-sm font-medium text-stone-800"
                >
                    Country
                </label>
                <select
                    id="country_code"
                    v-model="form.country_code"
                    name="country_code"
                    autocomplete="country"
                    required
                    class="mt-2 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-base text-stone-950 shadow-sm transition outline-none focus:border-teal-700 focus:ring-2 focus:ring-teal-700/20"
                >
                    <option value="">Select a country</option>
                    <option
                        v-for="(country, code) in countries"
                        :key="code"
                        :value="code"
                    >
                        {{ country }}
                    </option>
                </select>
                <InputError :message="form.errors.country_code" />
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

            <div class="space-y-3">
                <label
                    class="flex items-start gap-3 text-sm leading-6 text-stone-700"
                >
                    <input
                        v-model="form.accept_terms"
                        name="accept_terms"
                        type="checkbox"
                        class="mt-1 h-4 w-4 rounded border-stone-300 text-teal-700 focus:ring-2 focus:ring-teal-700/30"
                    />
                    <span>
                        I accept the
                        <Link
                            :href="terms()"
                            class="font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                        >
                            Terms of Use
                        </Link>
                    </span>
                </label>
                <InputError :message="form.errors.accept_terms" />

                <label
                    class="flex items-start gap-3 text-sm leading-6 text-stone-700"
                >
                    <input
                        v-model="form.accept_privacy"
                        name="accept_privacy"
                        type="checkbox"
                        class="mt-1 h-4 w-4 rounded border-stone-300 text-teal-700 focus:ring-2 focus:ring-teal-700/30"
                    />
                    <span>
                        I accept the
                        <Link
                            :href="privacy()"
                            class="font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                        >
                            Privacy Policy
                        </Link>
                    </span>
                </label>
                <InputError :message="form.errors.accept_privacy" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex w-full items-center justify-center rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
            >
                {{ form.processing ? 'Creating account...' : 'Create account' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">
            Already have an account?
            <Link
                :href="login()"
                class="font-medium text-teal-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
            >
                Log in
            </Link>
        </p>
    </AuthShell>
</template>
