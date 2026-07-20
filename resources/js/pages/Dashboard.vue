<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';

import { logout } from '@/routes';
import type { Auth } from '@/types/auth';

const page = usePage<{ auth: Auth }>();
const user = page.props.auth.user;
const logoutForm = useForm({});

const navigationItems = ['My LUTs', 'Custom LUTs', 'Orders', 'Profile'];

function submitLogout(): void {
    logoutForm.submit(logout());
}
</script>

<template>
    <Head title="Dashboard" />

    <main class="min-h-screen bg-stone-50 text-stone-950">
        <header class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div>
                    <p
                        class="text-sm font-semibold tracking-wide text-stone-950"
                    >
                        LUT Web
                    </p>
                    <p class="text-xs text-stone-500">Marketplace account</p>
                </div>

                <form @submit.prevent="submitLogout">
                    <button
                        type="submit"
                        :disabled="logoutForm.processing"
                        class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 transition hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:text-stone-400"
                    >
                        Log out
                    </button>
                </form>
            </div>
        </header>

        <div
            class="mx-auto grid w-full max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[16rem_1fr] lg:px-8"
        >
            <aside
                class="rounded-lg border border-stone-200 bg-white p-3 shadow-sm"
            >
                <nav aria-label="Dashboard navigation" class="space-y-2">
                    <button
                        v-for="item in navigationItems"
                        :key="item"
                        type="button"
                        disabled
                        class="flex w-full items-center justify-between rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-left text-sm text-stone-500"
                    >
                        <span>{{ item }}</span>
                        <span class="text-xs font-medium text-amber-700">
                            Coming soon
                        </span>
                    </button>
                </nav>
            </aside>

            <section class="space-y-6">
                <div
                    class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm sm:p-6"
                >
                    <p class="text-sm font-medium text-teal-800">Dashboard</p>
                    <h1 class="mt-2 text-2xl font-semibold text-stone-950">
                        Hello, {{ user?.name }}.
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">
                        Your LUT Web account is ready for the marketplace tools
                        that will arrive in future milestones.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-stone-950">
                            Email verification
                        </h2>
                        <p class="mt-2 text-sm text-stone-600">
                            {{
                                user?.email_verified_at
                                    ? 'Your email address is verified.'
                                    : 'Your email address is not verified.'
                            }}
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
                    >
                        <h2 class="text-sm font-semibold text-stone-950">
                            Account email
                        </h2>
                        <p class="mt-2 text-sm break-words text-stone-600">
                            {{ user?.email }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
