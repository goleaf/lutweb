<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

import { dashboard, home, login, register } from '@/routes';
import type { Auth } from '@/types/auth';

const page = usePage<{ auth: Auth }>();
const user = page.props.auth.user;
</script>

<template>
    <Head title="LUT Web" />

    <main class="min-h-screen bg-stone-50 text-stone-950">
        <header class="border-b border-stone-200 bg-white">
            <nav
                aria-label="Main navigation"
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <Link
                    :href="home()"
                    class="rounded-sm text-sm font-semibold tracking-wide text-stone-950 underline-offset-4 hover:text-teal-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    LUT Web
                </Link>

                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        :href="home()"
                        class="rounded-md px-3 py-2 font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Home
                    </Link>
                    <span
                        class="rounded-md px-3 py-2 font-medium text-stone-500"
                        aria-disabled="true"
                    >
                        Shop
                        <span class="ml-1 text-xs text-amber-700">
                            Coming soon
                        </span>
                    </span>
                    <span
                        class="rounded-md px-3 py-2 font-medium text-stone-500"
                        aria-disabled="true"
                    >
                        Create Your LUT
                        <span class="ml-1 text-xs text-amber-700">
                            Coming soon
                        </span>
                    </span>
                    <Link
                        v-if="user"
                        :href="dashboard()"
                        class="rounded-md bg-stone-950 px-3 py-2 font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-2 font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Login
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-md bg-stone-950 px-3 py-2 font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <section
            class="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_24rem] lg:px-8 lg:py-20"
        >
            <div class="flex flex-col justify-center">
                <p
                    class="text-sm font-semibold tracking-wide text-teal-800 uppercase"
                >
                    LUT marketplace foundation
                </p>
                <h1
                    class="mt-5 max-w-3xl text-4xl font-semibold tracking-normal text-stone-950 sm:text-5xl"
                >
                    Professional LUTs for photographers and creators.
                </h1>
                <p
                    class="mt-5 max-w-2xl text-base leading-7 text-stone-700 sm:text-lg"
                >
                    Try looks on your photos, create custom LUTs, and securely
                    download your purchases.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        v-if="user"
                        :href="dashboard()"
                        class="rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Open dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="register()"
                            class="rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Register
                        </Link>
                        <Link
                            :href="login()"
                            class="rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:border-stone-400 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Login
                        </Link>
                    </template>
                </div>
            </div>

            <div
                aria-label="LUT preview placeholder"
                class="grid content-center gap-4"
            >
                <div
                    class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="aspect-[4/3] overflow-hidden rounded-md bg-stone-900"
                    >
                        <div class="grid h-full grid-cols-5">
                            <div class="bg-stone-800" />
                            <div class="bg-teal-700" />
                            <div class="bg-amber-500" />
                            <div class="bg-rose-500" />
                            <div class="bg-sky-700" />
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <div class="h-2 rounded-full bg-stone-300" />
                        <div class="h-2 rounded-full bg-teal-500" />
                        <div class="h-2 rounded-full bg-amber-400" />
                    </div>
                </div>
                <p class="text-sm leading-6 text-stone-600">
                    Product browsing, custom LUT creation, checkout, uploads,
                    and downloads will be added in later milestones.
                </p>
            </div>
        </section>
    </main>
</template>
