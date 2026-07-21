<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

import { dashboard, home, login, register } from '@/routes';
import { index as shopIndex } from '@/routes/shop';
import type { Auth } from '@/types/auth';

const page = usePage<{ auth: Auth }>();
const user = page.props.auth.user;
</script>

<template>
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
                    <Link
                        :href="shopIndex()"
                        class="rounded-md px-3 py-2 font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Shop
                    </Link>
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

        <slot />
    </main>
</template>
