<script lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, defineComponent, h } from 'vue';
import type { PropType } from 'vue';

import type { StorefrontSeo } from '@/types/storefront';

export default defineComponent({
    name: 'StorefrontSeoHead',
    props: {
        seo: {
            type: Object as PropType<StorefrontSeo>,
            required: true,
        },
        defaultOgType: {
            type: String,
            default: 'website',
        },
        defaultTwitterCard: {
            type: String,
            default: 'summary',
        },
    },
    setup(props) {
        const image = computed(
            () => props.seo.og_image ?? props.seo.image ?? null,
        );
        const jsonLd = computed(() =>
            props.seo.json_ld ? JSON.stringify(props.seo.json_ld) : null,
        );
        const twitterCard = computed(
            () =>
                props.seo.twitter_card ??
                (image.value
                    ? 'summary_large_image'
                    : props.defaultTwitterCard),
        );

        return () =>
            h(
                Head,
                { title: props.seo.title },
                {
                    default: () => [
                        h('meta', {
                            'head-key': 'description',
                            name: 'description',
                            content: props.seo.description,
                        }),
                        props.seo.robots
                            ? h('meta', {
                                  'head-key': 'robots',
                                  name: 'robots',
                                  content: props.seo.robots,
                              })
                            : null,
                        h('link', {
                            'head-key': 'canonical',
                            rel: 'canonical',
                            href: props.seo.canonical_url,
                        }),
                        h('meta', {
                            'head-key': 'og:title',
                            property: 'og:title',
                            content: props.seo.og_title ?? props.seo.title,
                        }),
                        h('meta', {
                            'head-key': 'og:description',
                            property: 'og:description',
                            content:
                                props.seo.og_description ??
                                props.seo.description,
                        }),
                        h('meta', {
                            'head-key': 'og:type',
                            property: 'og:type',
                            content: props.seo.og_type ?? props.defaultOgType,
                        }),
                        image.value
                            ? h('meta', {
                                  'head-key': 'og:image',
                                  property: 'og:image',
                                  content: image.value,
                              })
                            : null,
                        h('meta', {
                            'head-key': 'twitter:card',
                            name: 'twitter:card',
                            content: twitterCard.value,
                        }),
                        jsonLd.value
                            ? h(
                                  'script',
                                  {
                                      'head-key': 'json-ld',
                                      type: 'application/ld+json',
                                  },
                                  jsonLd.value,
                              )
                            : null,
                    ],
                },
            );
    },
});
</script>
