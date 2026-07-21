<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    limits: {
        maximum_projects: number;
        project_expiration_days: number;
        maximum_photos: number;
        photo_expiration_minutes: number;
    };
}>();

const form = useForm({});

function startProject(): void {
    form.post('/custom-lut');
}
</script>

<template>
    <PublicLayout>
        <Head title="Create Your LUT">
            <meta name="robots" content="noindex,nofollow" />
        </Head>

        <section class="border-b border-stone-200 bg-white">
            <div
                class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:px-8"
            >
                <div>
                    <p
                        class="inline-flex items-center gap-2 text-sm font-semibold text-teal-800"
                    >
                        <AppIcon name="wand" class="size-4" />
                        Custom LUT Wizard
                    </p>
                    <h1
                        class="mt-3 text-3xl font-semibold text-stone-950 sm:text-4xl"
                    >
                        Create a custom LUT draft.
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-base leading-7 text-stone-700"
                    >
                        Test one look on up to three watermarked photo previews,
                        generate controlled style variations, fine-tune the
                        result, and return to the draft later.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-not-allowed disabled:bg-stone-400"
                            :disabled="form.processing"
                            @click="startProject"
                        >
                            <AppIcon name="wand" class="size-4" />
                            Start a New LUT
                        </button>
                        <Link
                            href="/account/custom-luts"
                            class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            <AppIcon name="folder" class="size-4" />
                            View My Drafts
                        </Link>
                    </div>
                    <p v-if="form.hasErrors" class="mt-3 text-sm text-red-700">
                        A new draft could not be created.
                    </p>
                </div>

                <aside
                    class="rounded-lg border border-stone-200 bg-stone-50 p-4"
                >
                    <h2
                        class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                    >
                        <AppIcon name="clock" class="size-4 text-teal-800" />
                        Draft limits
                    </h2>
                    <dl class="mt-3 grid gap-3 text-sm text-stone-700">
                        <div>
                            <dt class="font-medium text-stone-950">
                                Active drafts
                            </dt>
                            <dd>Up to {{ limits.maximum_projects }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-stone-950">
                                Photos per draft
                            </dt>
                            <dd>Up to {{ limits.maximum_photos }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-stone-950">
                                Draft retention
                            </dt>
                            <dd>
                                {{ limits.project_expiration_days }} days after
                                editing
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-stone-950">
                                Photo retention
                            </dt>
                            <dd>
                                {{ limits.photo_expiration_minutes }} minutes
                                after upload
                            </dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </section>

        <section
            class="mx-auto grid w-full max-w-6xl gap-4 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8"
        >
            <article class="rounded-lg border border-stone-200 bg-white p-5">
                <span
                    class="grid size-9 place-items-center rounded-md bg-teal-50 text-teal-800"
                    aria-hidden="true"
                >
                    <AppIcon name="palette" class="size-5" />
                </span>
                <h2 class="mt-3 text-base font-semibold text-stone-950">
                    Start from a style
                </h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Choose an administrator-managed look with safe variation
                    ranges.
                </p>
            </article>
            <article class="rounded-lg border border-stone-200 bg-white p-5">
                <span
                    class="grid size-9 place-items-center rounded-md bg-teal-50 text-teal-800"
                    aria-hidden="true"
                >
                    <AppIcon name="image" class="size-5" />
                </span>
                <h2 class="mt-3 text-base font-semibold text-stone-950">
                    Preview immediately
                </h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Fine-tune locally with WebGL 2, with a Canvas compatibility
                    preview when needed.
                </p>
            </article>
            <article class="rounded-lg border border-stone-200 bg-white p-5">
                <span
                    class="grid size-9 place-items-center rounded-md bg-teal-50 text-teal-800"
                    aria-hidden="true"
                >
                    <AppIcon name="download" class="size-5" />
                </span>
                <h2 class="mt-3 text-base font-semibold text-stone-950">
                    Download later
                </h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Purchase and downloadable custom LUT files are coming soon.
                </p>
            </article>
        </section>
    </PublicLayout>
</template>
