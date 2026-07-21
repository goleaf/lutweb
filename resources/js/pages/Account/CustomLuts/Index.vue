<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';

import AccountLayout from '@/layouts/AccountLayout.vue';

interface AccountProject {
    id: string;
    name: string;
    style_name: string;
    updated_at: string | null;
    expires_at: string;
    active_photo_count: number;
    parameters_hash: string;
    continue_url: string;
    duplicate_url: string;
    delete_url: string;
}

defineProps<{
    projects: {
        data: AccountProject[];
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
}>();

const csrf =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

function post(url: string): void {
    router.post(url);
}

async function remove(project: AccountProject): Promise<void> {
    if (!window.confirm(`Delete ${project.name}?`)) {
        return;
    }

    await fetch(project.delete_url, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
    });

    router.reload({ only: ['projects'] });
}
</script>

<template>
    <AccountLayout title="Custom LUTs">
        <div class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-teal-800">Account</p>
                    <h1 class="mt-2 text-2xl font-semibold text-stone-950">
                        Custom LUTs
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-stone-600">
                        Continue editing saved drafts. Projects with expired
                        photos remain editable.
                    </p>
                </div>
                <Link
                    href="/custom-lut"
                    class="rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Create Your First Custom LUT
                </Link>
            </div>
        </div>

        <div v-if="projects.data.length > 0" class="grid gap-3">
            <article
                v-for="project in projects.data"
                :key="project.id"
                class="rounded-lg border border-stone-200 bg-white p-4 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-stone-950">
                            {{ project.name }}
                        </h2>
                        <dl
                            class="mt-2 grid gap-x-5 gap-y-1 text-sm text-stone-600 sm:grid-cols-2"
                        >
                            <div>
                                <dt class="inline font-medium text-stone-900">
                                    Style:
                                </dt>
                                <dd class="inline">{{ project.style_name }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-stone-900">
                                    Photos:
                                </dt>
                                <dd class="inline">
                                    {{ project.active_photo_count }}
                                </dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-stone-900">
                                    Updated:
                                </dt>
                                <dd class="inline">
                                    {{
                                        project.updated_at
                                            ? new Date(
                                                  project.updated_at,
                                              ).toLocaleDateString()
                                            : 'Never'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-stone-900">
                                    Expires:
                                </dt>
                                <dd class="inline">
                                    {{
                                        new Date(
                                            project.expires_at,
                                        ).toLocaleDateString()
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-stone-900">
                                    Hash:
                                </dt>
                                <dd class="inline font-mono">
                                    {{ project.parameters_hash.slice(0, 12) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="project.continue_url"
                            class="rounded-md bg-stone-950 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                        >
                            Continue Editing
                        </Link>
                        <button
                            type="button"
                            class="rounded-md border border-stone-300 px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                            @click="post(project.duplicate_url)"
                        >
                            Duplicate
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700"
                            @click="remove(project)"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </article>
        </div>
        <div
            v-else
            class="rounded-lg border border-dashed border-stone-300 bg-white p-8 text-center shadow-sm"
        >
            <h2 class="text-lg font-semibold text-stone-950">
                No custom LUT drafts yet.
            </h2>
            <p class="mt-2 text-sm text-stone-600">
                Create Your First Custom LUT
            </p>
            <Link
                href="/custom-lut"
                class="mt-4 inline-flex rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
            >
                Create Your First Custom LUT
            </Link>
        </div>
    </AccountLayout>
</template>
