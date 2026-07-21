<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AppIcon from '@/components/AppIcon.vue';
import AccountLayout from '@/layouts/AccountLayout.vue';

type PurchaseDetail = {
    id: string;
    name: string;
    style_name: string;
    order_number: string | null;
    order_url: string | null;
    amount: string | null;
    currency: 'EUR' | null;
    payment_status: string | null;
    fulfillment_status: string | null;
    license_version: string | null;
    version_label: string | null;
    transform_version: string | null;
    generator_version: string | null;
    parameter_hash: string;
    package_size_bytes: number | null;
    download_url: string | null;
    project_url: string | null;
};

defineProps<{
    purchase: PurchaseDetail;
}>();

const contents = [
    '17-point CUBE',
    '33-point CUBE',
    '65-point CUBE',
    'License PDF',
    'Installation Guide PDF',
    'README',
];

function packageSize(bytes: number | null): string {
    if (bytes === null || bytes <= 0) {
        return 'Package size unavailable';
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <AccountLayout :title="purchase.name">
        <section class="space-y-5">
            <div>
                <Link
                    href="/account/custom-luts/purchased"
                    class="inline-flex items-center gap-1.5 rounded-sm text-sm text-stone-600 underline-offset-4 hover:text-teal-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700"
                >
                    <AppIcon name="arrow-left" class="size-3.5" />
                    Back to purchased Custom LUTs
                </Link>
                <h1
                    class="mt-3 inline-flex items-center gap-2 text-2xl font-semibold text-stone-950"
                >
                    <AppIcon name="wand" class="size-5 text-teal-800" />
                    {{ purchase.name }}
                </h1>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    This purchase grants access to one exact immutable Custom
                    LUT package. Future edits to the project are separate
                    builds.
                </p>
            </div>

            <section class="rounded-lg border border-stone-200 bg-white p-5">
                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-stone-500">Order</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.order_number ?? 'Unknown' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Amount</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.amount ?? 'Unavailable' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Payment</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.payment_status ?? 'unknown' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Fulfillment</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.fulfillment_status ?? 'unknown' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Build</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.version_label ?? 'Build' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Style</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.style_name || 'Neutral' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Transform</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.transform_version ?? 'Unknown' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Generator</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.generator_version ?? 'Unknown' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Parameters</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.parameter_hash }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Package</dt>
                        <dd class="font-medium text-stone-900">
                            {{ packageSize(purchase.package_size_bytes) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">License version</dt>
                        <dd class="font-medium text-stone-900">
                            {{ purchase.license_version ?? 'Unknown' }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border border-stone-200 bg-white p-5">
                <h2
                    class="inline-flex items-center gap-2 text-base font-semibold text-stone-950"
                >
                    <AppIcon name="package" class="size-4 text-teal-800" />
                    Package contents
                </h2>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    <li
                        v-for="content in contents"
                        :key="content"
                        class="inline-flex items-center gap-2 rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-700"
                    >
                        <AppIcon
                            name="check-circle"
                            class="size-4 shrink-0 text-teal-800"
                        />
                        {{ content }}
                    </li>
                </ul>
            </section>

            <div class="flex flex-wrap gap-2">
                <a
                    v-if="purchase.download_url"
                    :href="purchase.download_url"
                    class="inline-flex items-center gap-2 rounded-md bg-stone-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="download" class="size-4" />
                    Download ZIP
                </a>
                <span
                    v-else
                    class="inline-flex items-center gap-2 rounded-md border border-stone-200 bg-stone-50 px-4 py-2.5 text-sm text-stone-600"
                >
                    <AppIcon name="alert-circle" class="size-4" />
                    Download unavailable
                </span>
                <Link
                    v-if="purchase.order_url"
                    :href="purchase.order_url"
                    class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="receipt" class="size-4" />
                    View Order
                </Link>
                <Link
                    v-if="purchase.project_url"
                    :href="purchase.project_url"
                    class="inline-flex items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                >
                    <AppIcon name="edit" class="size-4" />
                    Continue Editing Project
                </Link>
                <span
                    v-else
                    class="inline-flex items-center gap-2 rounded-md border border-stone-200 bg-stone-50 px-4 py-2.5 text-sm text-stone-600"
                >
                    <AppIcon name="trash" class="size-4" />
                    Project Deleted
                </span>
            </div>
        </section>
    </AccountLayout>
</template>
