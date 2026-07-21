<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AccountLayout from '@/layouts/AccountLayout.vue';

type DownloadRow = {
    id: string;
    product_name: string;
    order_number: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    status: string;
    status_label: string;
    ip_address: string | null;
    device: string;
};

type DownloadsPage = {
    data: DownloadRow[];
    meta: {
        prev_page_url?: string | null;
        next_page_url?: string | null;
        total?: number;
    };
};

defineProps<{
    downloads: DownloadsPage;
}>();
</script>

<template>
    <AccountLayout title="Downloads">
        <section class="space-y-5">
            <div>
                <p class="text-sm font-medium text-teal-800">History</p>
                <h1 class="mt-1 text-2xl font-semibold text-stone-950">
                    Downloads
                </h1>
            </div>

            <div
                class="overflow-hidden rounded-lg border border-stone-200 bg-white"
            >
                <table
                    v-if="downloads.data.length > 0"
                    class="w-full text-left text-sm"
                >
                    <thead
                        class="border-b border-stone-200 bg-stone-50 text-xs font-semibold text-stone-600 uppercase"
                    >
                        <tr>
                            <th scope="col" class="px-4 py-3">Item</th>
                            <th scope="col" class="px-4 py-3">Order</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">IP</th>
                            <th scope="col" class="px-4 py-3">Device</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        <tr
                            v-for="download in downloads.data"
                            :key="download.id"
                        >
                            <td class="px-4 py-3 font-medium text-stone-950">
                                {{ download.product_name }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ download.order_number ?? 'Unknown' }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ download.status_label }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ download.ip_address ?? 'Unknown' }}
                            </td>
                            <td class="px-4 py-3 text-stone-700">
                                {{ download.device || 'Unknown' }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="p-6 text-sm text-stone-600">
                    Download events will appear here.
                </div>
            </div>

            <nav
                v-if="
                    downloads.meta.prev_page_url || downloads.meta.next_page_url
                "
                aria-label="Downloads pagination"
                class="flex justify-between gap-3"
            >
                <Link
                    v-if="downloads.meta.prev_page_url"
                    :href="downloads.meta.prev_page_url"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Previous
                </Link>
                <span v-else />
                <Link
                    v-if="downloads.meta.next_page_url"
                    :href="downloads.meta.next_page_url"
                    class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    Next
                </Link>
            </nav>
        </section>
    </AccountLayout>
</template>
