<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { dashboard, home, login, privacy, register, terms } from '@/routes';
import { index as shopIndex } from '@/routes/shop';
import type { Auth } from '@/types/auth';

const page = usePage<{ auth: Auth }>();
const user = computed(() => page.props.auth.user);
const mobileMenuOpen = ref(false);
const currentYear = new Date().getFullYear();

function isCurrent(path: string): boolean {
    return page.url === path || page.url.startsWith(`${path}?`);
}
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-950">
        <a
            href="#main-content"
            class="sr-only rounded-md bg-stone-950 px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:outline-2 focus:outline-offset-2 focus:outline-teal-500"
        >
            Skip to content
        </a>

        <header class="border-b border-stone-200 bg-white/95 backdrop-blur">
            <nav
                aria-label="Public navigation"
                class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <Link
                    :href="home()"
                    class="inline-flex items-center gap-3 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    <span
                        aria-hidden="true"
                        class="grid size-9 place-items-center rounded-md bg-stone-950 text-sm font-semibold text-white"
                    >
                        LW
                    </span>
                    <span class="text-sm font-semibold tracking-wide">
                        LUT Web
                    </span>
                </Link>

                <div class="hidden items-center gap-1 md:flex">
                    <Link
                        :href="home()"
                        :aria-current="
                            isCurrent(home.url()) ? 'page' : undefined
                        "
                        class="rounded-md px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 aria-[current=page]:bg-stone-100 aria-[current=page]:text-stone-950"
                    >
                        Home
                    </Link>
                    <Link
                        :href="shopIndex()"
                        :aria-current="
                            isCurrent(shopIndex.url()) ? 'page' : undefined
                        "
                        class="rounded-md px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 aria-[current=page]:bg-stone-100 aria-[current=page]:text-stone-950"
                    >
                        Shop
                    </Link>
                    <Link
                        href="/custom-lut"
                        :aria-current="
                            isCurrent('/custom-lut') ? 'page' : undefined
                        "
                        class="rounded-md px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 aria-[current=page]:bg-stone-100 aria-[current=page]:text-stone-950"
                    >
                        Create Your LUT
                    </Link>
                </div>

                <div class="hidden items-center gap-2 md:flex">
                    <Link
                        v-if="user"
                        :href="dashboard()"
                        class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-100 hover:text-stone-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Login
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Register
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-stone-300 bg-white p-2 text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 md:hidden"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="public-mobile-navigation"
                    aria-label="Toggle navigation"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg
                        v-if="!mobileMenuOpen"
                        aria-hidden="true"
                        class="size-5"
                        viewBox="0 0 20 20"
                        fill="none"
                    >
                        <path
                            d="M3 5h14M3 10h14M3 15h14"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                    </svg>
                    <svg
                        v-else
                        aria-hidden="true"
                        class="size-5"
                        viewBox="0 0 20 20"
                        fill="none"
                    >
                        <path
                            d="m5 5 10 10M15 5 5 15"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        />
                    </svg>
                </button>
            </nav>

            <div
                v-show="mobileMenuOpen"
                id="public-mobile-navigation"
                class="border-t border-stone-200 bg-white px-4 py-3 md:hidden"
            >
                <div class="mx-auto grid w-full max-w-7xl gap-2">
                    <Link
                        :href="home()"
                        class="rounded-md px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="mobileMenuOpen = false"
                    >
                        Home
                    </Link>
                    <Link
                        :href="shopIndex()"
                        class="rounded-md px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="mobileMenuOpen = false"
                    >
                        Shop
                    </Link>
                    <Link
                        href="/custom-lut"
                        class="rounded-md px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="mobileMenuOpen = false"
                    >
                        Create Your LUT
                    </Link>
                    <Link
                        v-if="user"
                        :href="dashboard()"
                        class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        @click="mobileMenuOpen = false"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            @click="mobileMenuOpen = false"
                        >
                            Login
                        </Link>
                        <Link
                            :href="register()"
                            class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            @click="mobileMenuOpen = false"
                        >
                            Register
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main id="main-content">
            <slot />
        </main>

        <footer class="border-t border-stone-200 bg-white">
            <div
                class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-8 text-sm text-stone-600 sm:px-6 md:grid-cols-[1fr_auto] lg:px-8"
            >
                <div>
                    <p class="font-semibold text-stone-950">LUT Web</p>
                    <p class="mt-2 max-w-xl leading-6">
                        A developing English-language marketplace for
                        downloadable LUT files.
                    </p>
                </div>
                <nav
                    aria-label="Footer navigation"
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 md:justify-end"
                >
                    <Link
                        :href="terms()"
                        class="rounded-sm underline-offset-4 hover:text-stone-950 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                    >
                        Terms
                    </Link>
                    <Link
                        :href="privacy()"
                        class="rounded-sm underline-offset-4 hover:text-stone-950 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                    >
                        Privacy
                    </Link>
                    <span>&copy; {{ currentYear }} LUT Web</span>
                </nav>
            </div>
        </footer>
    </div>
</template>
