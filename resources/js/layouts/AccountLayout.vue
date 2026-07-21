<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { logout } from '@/routes';
import { index as downloadsIndex } from '@/routes/account/downloads';
import { index as lutsIndex } from '@/routes/account/luts';
import { index as ordersIndex } from '@/routes/account/orders';
import type { Auth } from '@/types/auth';

const props = defineProps<{
    title: string;
}>();

const page = usePage<{ auth: Auth }>();
const user = computed(() => page.props.auth.user);
const logoutForm = useForm({});

const links = [
    { label: 'My LUTs', href: lutsIndex().url },
    { label: 'Custom LUTs', href: '/account/custom-luts' },
    { label: 'Orders', href: ordersIndex().url },
    { label: 'Downloads', href: downloadsIndex().url },
];

function current(path: string): boolean {
    return page.url === path || page.url.startsWith(`${path}?`);
}

function submitLogout(): void {
    logoutForm.submit(logout());
}
</script>

<template>
    <Head :title="props.title" />

    <main class="min-h-screen bg-stone-50 text-stone-950">
        <header class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div>
                    <Link
                        href="/"
                        class="rounded-sm text-sm font-semibold tracking-wide text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                    >
                        LUT Web
                    </Link>
                    <p class="text-xs text-stone-500">
                        {{ user?.email }}
                    </p>
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
                class="h-fit rounded-lg border border-stone-200 bg-white p-3 shadow-sm"
            >
                <nav aria-label="Account navigation" class="space-y-2">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        :aria-current="current(link.href) ? 'page' : undefined"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium text-stone-700 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 aria-[current=page]:bg-stone-950 aria-[current=page]:text-white"
                    >
                        {{ link.label }}
                    </Link>
                    <span
                        class="flex w-full items-center justify-between rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-left text-sm text-stone-500"
                    >
                        Profile
                        <span class="text-xs font-medium text-amber-700">
                            Coming soon
                        </span>
                    </span>
                </nav>
            </aside>

            <section class="min-w-0 space-y-6">
                <slot />
            </section>
        </div>
    </main>
</template>
