<script setup lang="ts">
import AccountPagination from '@/components/account/AccountPagination.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import SectionHeading from '@/components/ui/SectionHeading.vue';
import AccountLayout from '@/layouts/AccountLayout.vue';

type DownloadRow = {
    id: string;
    product_name: string;
    version: string | null;
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
            <SectionHeading
                as="h1"
                icon="download"
                eyebrow="History"
                title="Downloads"
            />

            <div
                v-if="downloads.data.length > 0"
                class="overflow-hidden rounded-lg border border-stone-200 bg-white"
            >
                <table class="w-full text-left text-sm">
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
                                <span class="block">
                                    {{ download.product_name }}
                                </span>
                                <span class="text-xs text-stone-500">
                                    {{ download.version ?? 'Version' }}
                                </span>
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
            </div>

            <EmptyState
                v-else
                icon="download"
                title="No download events yet."
                message="Download events will appear here."
            />

            <AccountPagination
                :prev-page-url="downloads.meta.prev_page_url"
                :next-page-url="downloads.meta.next_page_url"
                label="Downloads pagination"
            />
        </section>
    </AccountLayout>
</template>
