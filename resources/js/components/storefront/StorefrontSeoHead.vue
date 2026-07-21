<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { StorefrontSeo } from '@/types/storefront';

const props = withDefaults(
    defineProps<{
        seo: StorefrontSeo;
        defaultOgType?: string;
        defaultTwitterCard?: string;
    }>(),
    {
        defaultOgType: 'website',
        defaultTwitterCard: 'summary',
    },
);

const image = computed(() => props.seo.og_image ?? props.seo.image ?? null);
const jsonLd = computed(() =>
    props.seo.json_ld ? JSON.stringify(props.seo.json_ld) : null,
);
const twitterCard = computed(
    () =>
        props.seo.twitter_card ??
        (image.value ? 'summary_large_image' : props.defaultTwitterCard),
);
</script>

<template>
    <Head :title="seo.title">
        <meta
            head-key="description"
            name="description"
            :content="seo.description"
        />
        <meta
            v-if="seo.robots"
            head-key="robots"
            name="robots"
            :content="seo.robots"
        />
        <link head-key="canonical" rel="canonical" :href="seo.canonical_url" />
        <meta
            head-key="og:title"
            property="og:title"
            :content="seo.og_title ?? seo.title"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="seo.og_description ?? seo.description"
        />
        <meta
            head-key="og:type"
            property="og:type"
            :content="seo.og_type ?? defaultOgType"
        />
        <meta
            v-if="image"
            head-key="og:image"
            property="og:image"
            :content="image"
        />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="twitterCard"
        />
        <script v-if="jsonLd" type="application/ld+json">
            {{ jsonLd }}
        </script>
    </Head>
</template>
