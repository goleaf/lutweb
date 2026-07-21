<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import { index as shopIndex } from '@/routes/shop';
</script>

<template>
    <section class="border-b border-stone-200 bg-white">
        <div
            class="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[minmax(0,1fr)_24rem] lg:px-8 lg:py-20"
        >
            <div class="flex flex-col justify-center">
                <p
                    class="inline-flex items-center gap-2 text-sm font-semibold tracking-wide text-teal-800 uppercase"
                >
                    <AppIcon name="palette" class="size-4" />
                    LUT Web
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
                        :href="shopIndex()"
                        class="inline-flex items-center gap-2 rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="shop" class="size-4" />
                        Explore LUTs
                    </Link>
                    <Link
                        href="/custom-lut"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                    >
                        <AppIcon name="wand" class="size-4" />
                        Create Your LUT
                    </Link>
                </div>
            </div>

            <div
                class="grid content-center gap-4"
                aria-label="Editorial LUT preview"
            >
                <div
                    class="overflow-hidden rounded-lg border border-stone-200 bg-stone-950"
                >
                    <div class="grid aspect-[4/3] grid-cols-2">
                        <div
                            class="bg-[linear-gradient(160deg,#292524,#57534e_45%,#d6d3d1)]"
                            aria-label="Original preview tones"
                            role="img"
                        />
                        <div
                            class="bg-[linear-gradient(160deg,#0f172a,#0f766e_45%,#f59e0b)]"
                            aria-label="LUT preview tones"
                            role="img"
                        />
                    </div>
                    <div class="grid grid-cols-6 gap-1 p-4" aria-hidden="true">
                        <span class="h-2 rounded-full bg-stone-500" />
                        <span class="h-2 rounded-full bg-teal-600" />
                        <span class="h-2 rounded-full bg-amber-500" />
                        <span class="h-2 rounded-full bg-rose-500" />
                        <span class="h-2 rounded-full bg-sky-600" />
                        <span class="h-2 rounded-full bg-stone-300" />
                    </div>
                </div>
                <p
                    class="flex items-start gap-2 text-sm leading-6 text-stone-600"
                >
                    <AppIcon
                        name="check-circle"
                        class="mt-1 size-4 shrink-0 text-teal-800"
                    />
                    <span>
                        Public browsing, checkout, downloads, photo testing, and
                        custom LUT drafts are available. Custom LUT purchase and
                        download are coming soon.
                    </span>
                </p>
            </div>
        </div>
    </section>
</template>
