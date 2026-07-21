<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import AccountLayout from '@/layouts/AccountLayout.vue';
import type { Auth } from '@/types/auth';

const props = defineProps<{
    counts?: {
        ready_made_luts: number;
        purchased_custom_luts: number;
        active_custom_lut_drafts: number;
    };
    recent_custom_lut_project?: {
        name: string;
        updated_at: string | null;
        continue_url: string;
    } | null;
}>();

const page = usePage<{ auth: Auth }>();
const user = page.props.auth.user;
</script>

<template>
    <AccountLayout title="Dashboard">
        <div
            class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm sm:p-6"
        >
            <p
                class="inline-flex items-center gap-2 text-sm font-medium text-teal-800"
            >
                <AppIcon name="dashboard" class="size-4" />
                Dashboard
            </p>
            <h1 class="mt-2 text-2xl font-semibold text-stone-950">
                Hello, {{ user?.name }}.
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-stone-600">
                Your account is ready for LUT purchases, free claims, secure
                downloads, and order history.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="package" class="size-4 text-teal-800" />
                    Ready-Made LUTs
                </h2>
                <p class="mt-2 text-2xl font-semibold text-stone-950">
                    {{ props.counts?.ready_made_luts ?? 0 }}
                </p>
                <Link
                    href="/account/luts"
                    class="mt-4 inline-flex items-center gap-2 rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="download" class="size-4" />
                    My LUTs
                </Link>
            </div>

            <div
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="wand" class="size-4 text-teal-800" />
                    Purchased Custom LUTs
                </h2>
                <p class="mt-2 text-2xl font-semibold text-stone-950">
                    {{ props.counts?.purchased_custom_luts ?? 0 }}
                </p>
                <Link
                    href="/account/custom-luts/purchased"
                    class="mt-4 inline-flex items-center gap-2 rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="receipt" class="size-4" />
                    Purchases
                </Link>
            </div>

            <div
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="wand" class="size-4 text-teal-800" />
                    Active Custom LUT Drafts
                </h2>
                <p class="mt-2 text-2xl font-semibold text-stone-950">
                    {{ props.counts?.active_custom_lut_drafts ?? 0 }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="clock" class="size-4 text-teal-800" />
                    Recent Custom LUT
                </h2>
                <p class="mt-2 text-sm text-stone-600">
                    {{
                        props.recent_custom_lut_project?.name ??
                        'No active draft yet.'
                    }}
                </p>
                <Link
                    v-if="props.recent_custom_lut_project"
                    :href="props.recent_custom_lut_project.continue_url"
                    class="mt-4 inline-flex items-center gap-2 rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="edit" class="size-4" />
                    Continue Editing
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon
                        :name="
                            user?.email_verified_at
                                ? 'check-circle'
                                : 'alert-circle'
                        "
                        class="size-4 text-teal-800"
                    />
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
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="mail" class="size-4 text-teal-800" />
                    Account email
                </h2>
                <p class="mt-2 text-sm break-words text-stone-600">
                    {{ user?.email }}
                </p>
            </div>

            <div
                class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="inline-flex items-center gap-2 text-sm font-semibold text-stone-950"
                >
                    <AppIcon name="wand" class="size-4 text-teal-800" />
                    Custom LUTs
                </h2>
                <p class="mt-2 text-sm text-stone-600">
                    Continue saved drafts or start a new custom LUT preview.
                </p>
                <Link
                    href="/account/custom-luts"
                    class="mt-4 inline-flex items-center gap-2 rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="wand" class="size-4" />
                    View Custom LUTs
                </Link>
            </div>
        </div>
    </AccountLayout>
</template>
